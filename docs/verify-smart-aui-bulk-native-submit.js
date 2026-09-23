/**
 * 自检：Smart AUI 批量编辑「恢复原生提交」是否把「已处理」标记带给后端。
 *
 * 背景：批量编辑的原生提交（#posts-filter，method=get）会触发 wp_insert_post_data，
 * 若不带 w2p_smart_aui_processed 标记，服务端会对每篇选中文章整篇重抓外链图
 * （每张死链 3 次重试、每篇触发 2 遍），批量一大就把 PHP 拖到 502。
 *
 * 断言：逻辑坏了会变红。
 *
 * 运行：
 *   NODE_PATH=/Users/sotary/.workbuddy/binaries/node/workspace/node_modules \
 *   /Users/sotary/.workbuddy/binaries/node/versions/22.22.2-3/bin/node docs/verify-smart-aui-bulk-native-submit.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const ROOT = path.resolve(__dirname, '..');
const JQUERY = '/Users/sotary/.workbuddy/binaries/node/workspace/node_modules/jquery/dist/jquery.js';
const PLUGIN_JS = path.join(ROOT, 'includes/modules/smart-aui/assets/js/smart-aui-ui.js');

/**
 * Fixture 结构照抄核心：
 *  - wp-admin/edit.php:488        <form id="posts-filter" method="get">
 *  - wp-admin/includes/class-wp-posts-list-table.php:1083   checkbox name="post[]"
 *  - class-wp-posts-list-table.php:2214                     submit_button(..., 'bulk_edit')
 *  - wp-admin/js/inline-edit-post.js:201  核心把 #bulk-edit 整行搬进 table.widefat tbody，
 *    所以点 #bulk_edit 时它已属于 #posts-filter。
 */
const FIXTURE = `
<form id="posts-filter" method="get">
  <input type="hidden" name="post_status" class="post_status_page" value="all" />
  <input type="hidden" name="post_type" class="post_type_page" value="post" />
  <table class="wp-list-table widefat">
    <tbody id="the-list">
      <tr><th class="check-column"><input type="checkbox" name="post[]" value="101" checked /></th></tr>
      <tr><th class="check-column"><input type="checkbox" name="post[]" value="102" checked /></th></tr>
      <tr id="bulk-edit" class="inline-edit-row bulk-edit-row" style="display:table-row">
        <td class="colspanchange">
          <div class="inline-edit-wrapper">
            <fieldset class="inline-edit-col-right">
              <div class="inline-edit-col">
                <label>
                  <span class="title">Status</span>
                  <select name="_status">
                    <option value="-1">&mdash; No Change &mdash;</option>
                    <option value="publish">Published</option>
                  </select>
                </label>
              </div>
            </fieldset>
            <div class="submit inline-edit-save">
              <input type="submit" name="bulk_edit" id="bulk_edit" class="button button-primary" value="Update" />
              <button type="button" class="button cancel">Cancel</button>
              <input type="hidden" name="post_view" value="list" />
              <input type="hidden" name="screen" value="edit-post" />
            </div>
          </div>
        </td>
      </tr>
    </tbody>
  </table>
</form>
<div id="w2p-smart-aui-backdrop" class="w2p-hidden"></div>
`;

const FLAG_SELECTOR = 'input[name="w2p_smart_aui_processed"]';

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * 起一个装了 jQuery + 插件脚本的窗口。
 *
 * @param {Object} settings 传给 w2pSmartAuiParams.settings 的模块设置。
 * @return {Promise<Object>} 测试句柄。
 */
async function boot(settings) {
    const dom = new JSDOM(FIXTURE, { runScripts: 'outside-only', url: 'https://web.sotarylen.com/wp-admin/edit.php' });
    const { window } = dom;

    window.eval(fs.readFileSync(JQUERY, 'utf8'));
    const $ = window.jQuery;

    window.w2pSmartAuiParams = {
        ajax_url: '/wp-admin/admin-ajax.php',
        nonce: 'test-nonce',
        settings: settings,
        i18n: new Proxy({}, { get: (t, k) => String(k) })
    };

    const ajaxCalls = [];
    $.ajax = function (opts) {
        ajaxCalls.push(opts.data && opts.data.action);
        const action = opts.data && opts.data.action;
        const payload = action === 'w2p_smart_aui_get_post_details'
            ? { success: true, data: { post_title: 'Probe', post_content: '<p>no external media</p>' } }
            : { success: true, data: {} };
        setTimeout(() => { if (opts.success) { opts.success(payload); } }, 0);
        return { done: () => { }, fail: () => { } };
    };

    window.eval(fs.readFileSync(PLUGIN_JS, 'utf8'));
    await sleep(60);

    let submits = 0;
    const form = window.document.getElementById('posts-filter');
    form.addEventListener('submit', (e) => { e.preventDefault(); submits++; });

    return { dom, window, $, ajaxCalls, countSubmits: () => submits };
}

const results = [];
function check(name, pass, detail) {
    results.push({ name, pass, detail });
    console.log(`${pass ? 'PASS' : 'FAIL'}  ${name}${detail ? '  -- ' + detail : ''}`);
}

(async function run() {
    // ---------------------------------------------------------------
    // T1 批量编辑 + 有进度条：队列跑完后必须把「已处理」标记写进 #posts-filter
    // ---------------------------------------------------------------
    {
        const t = await boot({ show_progress_ui: true, concurrent_threads: 4 });
        t.$('#bulk_edit').trigger('click');
        await sleep(1700);

        const form = t.window.document.getElementById('posts-filter');
        const flags = form.querySelectorAll(FLAG_SELECTOR);
        const value = flags.length ? flags[0].value : null;
        check(
            'T1 批量+进度条：恢复原生提交时带上 w2p_smart_aui_processed=1',
            flags.length === 1 && value === '1',
            `命中 ${flags.length} 个，值=${value}`
        );
        check(
            'T1 批量+进度条：原生提交确实发生了一次',
            t.countSubmits() === 1,
            `submit 次数=${t.countSubmits()}`
        );
        check(
            'T1 批量+进度条：按钮打上了 data 标记（不会被二次拦截）',
            t.$('#bulk_edit').data('smart-aui-processed') === true,
            String(t.$('#bulk_edit').data('smart-aui-processed'))
        );
        t.dom.window.close();
    }

    // ---------------------------------------------------------------
    // T2 回归护栏：关闭进度条时 processBulkWithoutProgress 是服务端抓图模式，
    //    绝不能带标记，否则外链图一张都不会被迁移。
    // ---------------------------------------------------------------
    {
        const t = await boot({ show_progress_ui: false, concurrent_threads: 4 });
        t.$('#bulk_edit').trigger('click');
        await sleep(300);

        const flags = t.window.document.querySelectorAll(FLAG_SELECTOR);
        check(
            'T2 批量+无进度条：不得写标记（服务端仍需兜底抓图）',
            flags.length === 0,
            `命中 ${flags.length} 个`
        );
        check(
            'T2 批量+无进度条：原生提交照常发生',
            t.countSubmits() === 1,
            `submit 次数=${t.countSubmits()}`
        );
        t.dom.window.close();
    }

    // ---------------------------------------------------------------
    // T3 幂等：同一表单重复恢复提交，只允许一个标记字段
    // ---------------------------------------------------------------
    {
        const t = await boot({ show_progress_ui: true, concurrent_threads: 4 });
        const api = t.window.W2P_SmartAUI_Progress;
        api.resumeNativeSubmit(t.$('#bulk_edit'));
        api.resumeNativeSubmit(t.$('#bulk_edit'));

        const flags = t.window.document.querySelectorAll(FLAG_SELECTOR);
        check('T3 幂等：重复调用只插一个标记字段', flags.length === 1, `命中 ${flags.length} 个`);
        t.dom.window.close();
    }

    // ---------------------------------------------------------------
    // T4 防御：按钮不在任何表单里时不抛异常
    // ---------------------------------------------------------------
    {
        const t = await boot({ show_progress_ui: true, concurrent_threads: 4 });
        let threw = null;
        try {
            t.$('#w2p-smart-aui-backdrop').append('<button id="orphan-btn" />');
            t.window.W2P_SmartAUI_Progress.resumeNativeSubmit(t.$('#orphan-btn'));
        } catch (e) {
            threw = e.message;
        }
        check('T4 防御：孤儿按钮调用不抛异常', threw === null, threw || 'ok');
        t.dom.window.close();
    }

    // ---------------------------------------------------------------
    const failed = results.filter((r) => !r.pass);
    console.log(`\n${results.length - failed.length}/${results.length} 通过`);
    process.exit(failed.length === 0 ? 0 : 1);
})().catch((e) => {
    console.error('自检脚本自身出错：', e);
    process.exit(2);
});
