/**
 * Album Importer — admin UI
 *
 * 三块：① 目录下钻选择（Ajax browse）② 扫描选中目录 ③ 分批导入。
 * 视觉沿用插件既有类（w2p-btn / w2p-badge），进度与确认走全局 window.w2p API，不另造组件。
 *
 * @package WP_Genius
 * @subpackage Modules/AlbumImporter
 */
(function ($) {
	'use strict';

	var P = window.w2pAlbumParams || {};
	var i18n = P.i18n || {};

	var rows = [];
	var current = { path: '', parent: '', root: P.browseRoot || '' };
	var importing = false;
	var stopRequested = false;

	/**
	 * 并发路数 —— **保持 1，别调大**。
	 *
	 * 实测（2026-10-02）：改成 2 之后，FPM 日志出现
	 *   `server reached pm.max_children setting (6)` 与 `executing too slow (30s)`，
	 * 单张耗时反而从 0.97s 涨到约 3s。说明瓶颈是**共享 I/O**（缩略图编解码 + MinIO 上传带宽），
	 * 并发只是把同一份带宽切开，还额外占满只有 6 个子进程的池子 —— 池子一满，
	 * 新的 Ajax 请求直接失败，前端就会「每行都显示失败」。
	 * 要提速得减少单张工作量（少生成几个尺寸 / 延后卸载），不是加并发。
	 */
	var CONCURRENCY = 1;

	/**
	 * 单批请求的客户端超时（毫秒）。
	 *
	 * 一批 5 张真图要 30~40s（缩略图编码 + MinIO 上传），这里给足约 6 倍余量再判超时。
	 *
	 * 为什么必须有：jQuery 的 `$.ajax` **默认不超时**。一旦 FPM 子进程被占满、
	 * 请求挂在池子里，前端就永久停在 importing 状态（导航被冻结、按钮禁用）——
	 * 这正是用户看到的「卡死」。给一个有界的超时，UI 最差也能自己恢复。
	 *
	 * 超时后重发同一个 offset 是安全的：服务端以**源图路径**为幂等键（见 import_one_image），
	 * 已入库的那几张会被复用，不会重复入库、也不会往正文追加重复 `<img>`。
	 */
	var BATCH_TIMEOUT = 240000;

	/** 单批失败/超时后的自动重试次数。服务端幂等，重试安全。 */
	var BATCH_RETRY = 2;

	/** 重试前的退避（给 FPM 池子腾出子进程的时间）。 */
	var BATCH_RETRY_DELAY = 3000;

	/**
	 * 取国际化文案并按顺序替换 %1$s / %2$s / %s 占位符。
	 *
	 * @param {string}    key      键。
	 * @param {string}    fallback 兜底文案。
	 * @param {...string} values   占位符值。
	 * @return {string}
	 */
	function t(key, fallback) {
		var out = i18n[key] || fallback || key;
		var values = Array.prototype.slice.call(arguments, 2);

		values.forEach(function (value, index) {
			out = out.replace('%' + (index + 1) + '$s', value);
		});

		return out.replace('%s', values.length ? values[0] : '');
	}

	/**
	 * 发送 Ajax 请求（自动带上 nonce）。
	 *
	 * @param {Object} data    载荷。
	 * @param {number} timeout 可选，毫秒；0 或缺省 = 不限时（浏览/扫描/建文章这类短请求沿用旧行为）。
	 * @return {jqXHR}
	 */
	function request(data, timeout) {
		return $.ajax({
			url: P.ajaxUrl,
			type: 'POST',
			data: $.extend({ nonce: P.nonce }, data),
			timeout: timeout || 0
		});
	}

	/**
	 * 生成一个徽章节点（克隆模板，不拼字符串）。
	 *
	 * @param {jQuery} $container 容器。
	 * @param {string} text       文案。
	 * @param {string} cls        附加类名。
	 */
	function badge($container, text, cls) {
		var tpl = document.getElementById('w2p-album-badge-tpl');
		if (!tpl) {
			$container.append($('<span>').text(text));
			return;
		}
		var node = tpl.content.firstElementChild.cloneNode(true);
		if (cls) {
			node.classList.add(cls);
		}
		node.textContent = text;
		$container.append(node);
	}

	/**
	 * 判定一行状态。
	 *
	 * @param {Object} row 候选数据。
	 * @return {string}
	 */
	function rowStatus(row) {
		// 已完成的重复导入才跳过；中断过的（state 不是 done）应当允许续跑。
		if (row.existing_id) {
			return row.existing_state === 'done' ? 'exists' : 'resume';
		}
		if (row.image_count === 0) {
			return 'noimage';
		}
		// 层级可疑：结构性目录名 / 疑似集合层 / 多个图片层目录 / 图数触顶 —— 一律需人工确认。
		if (row.structural || row.suspected_collection || row.ambiguous_layers || row.too_large) {
			return 'review';
		}
		if (row.warnings && row.warnings.length) {
			return 'warning';
		}
		return 'new';
	}

	/**
	 * 是否默认勾选。
	 *
	 * 只有「新增」与「可续跑」默认勾；「有告警」「需人工确认」**不**默认勾。
	 * 实测依据：默认浏览根下混着「老司机种子合集预览包」这种内含 3532 张 webp 预览图的伪图集
	 * （无视频文件，任何格式检测都拦不住）。若告警行默认勾选，一次「全选导入」就会误建一篇 3627 张图的文章。
	 *
	 * @param {Object} row 候选数据。
	 * @return {boolean}
	 */
	function isImportable(row) {
		var status = rowStatus(row);
		return status === 'new' || status === 'resume';
	}

	/**
	 * 刷新底部导入按钮的可用状态。
	 */
	function refreshImportButton() {
		var count = $('#w2p-album-rows .w2p-album-row-check:checked:not(:disabled)').length;
		$('#w2p-album-import-btn').prop('disabled', importing || count === 0);
	}

	/**
	 * 渲染工作室输入框。
	 *
	 * 用 `<datalist>` 而不是 `<select>`：原生控件既能看到既有词条、又能直接输入新名字，
	 * 而且是服务端渲染好的，JS 只需赋值（不必在脚本里拼 HTML）。
	 * 预填规则：命中词条就填规范名；没命中就填解析出来的原始串（服务端会按「命中即用，不中即新建」处理）。
	 *
	 * @param {jQuery} $cell 单元格。
	 * @param {Object} row   候选数据。
	 */
	function renderStudioInput($cell, row) {
		var matched = !!(row.studio_term && row.studio_term.id);
		var value = matched ? row.studio_term.name : (row.studio || '');

		var $input = $('<input>', {
			type: 'text',
			class: 'w2p-album-input w2p-album-input-studio',
			list: 'w2p-album-studio-list',
			autocomplete: 'off',
			spellcheck: false
		});

		$input.val(value);

		if (!matched && value) {
			// 未命中：视觉上标出「这个词条库里还没有，导入时会新建」。
			$input.addClass('w2p-album-input-newterm');
			$input.attr('title', t('studioCreate', '＋ Create "%s"', value));
		} else if (!value) {
			$input.attr('title', t('studioNone', '— no studio —'));
		}

		$cell.empty().append($input);
	}

	/**
	 * 追加一个模特标签。
	 *
	 * kind 三种：
	 *  · 'linked' —— 命中既有词条，留用即关联；
	 *  · 'create' —— 未命中但已确认要新建；
	 *  · 'desc'   —— 未命中且被判定为描述，默认**不建**（点一下才转成 create）。
	 *
	 * @param {jQuery} $cell 容器。
	 * @param {string} name  模特名。
	 * @param {string} kind  类型。
	 */
	function appendModelTag($cell, name, kind) {
		var tpl = document.getElementById('w2p-album-tag-tpl');

		if (!tpl) {
			return;
		}

		var node = tpl.content.firstElementChild.cloneNode(true);
		var isDesc = ('desc' === kind);

		node.setAttribute('data-name', name);
		node.setAttribute('data-kind', kind);
		node.classList.add(isDesc ? 'w2p-album-tag-muted' : 'w2p-album-tag-ok');
		node.setAttribute(
			'title',
			isDesc
				? t('tagDesc', 'Looks like a description, not a model. Click to create it as a model anyway.')
				: ('create' === kind ? t('tagWillCreate', 'Will be created as a new model.') : t('tagLinked', 'Linked to an existing model.'))
		);

		node.querySelector('.w2p-album-tag-label').textContent = name;
		node.querySelector('.w2p-album-tag-x').setAttribute('title', t('tagRemove', 'Remove'));

		$cell.append(node);
	}

	/**
	 * 读取一行最终要提交的模特名。
	 *
	 * 只收「命中留用」（linked）与「已确认新建」（create）的标签；
	 * 被 × 删掉的、以及仍停留在「描述」（desc）状态的都不提交 —— 后者是防批量污染词条库的关键。
	 *
	 * @param {jQuery} $tr 行。
	 * @return {Array}
	 */
	function collectModelNames($tr) {
		var names = [];

		$tr.find('.w2p-album-tag').each(function () {
			var $tag = $(this);
			var kind = $tag.attr('data-kind');
			if ('linked' === kind || 'create' === kind) {
				names.push($tag.attr('data-name'));
			}
		});

		return names;
	}

	/**
	 * 导入进行中冻结导航，避免中途换目录导致状态错乱。
	 *
	 * @param {boolean} frozen 是否冻结。
	 */
	function setNavFrozen(frozen) {
		$('#w2p-album-up-btn, #w2p-album-open-btn, #w2p-album-scan-btn, #w2p-album-path').prop('disabled', frozen);
		$('#w2p-album-select-new-btn, #w2p-album-check-all').prop('disabled', frozen || rows.length === 0);
		$('#w2p-album-browser').toggleClass('w2p-album-browser-frozen', frozen);
	}

	/* ------------------------------------------------------------------ 目录浏览 */

	/**
	 * 渲染子目录列表。
	 *
	 * @param {Object} data browse 返回的数据。
	 */
	function renderBrowser(data) {
		var $box = $('#w2p-album-browser');
		var tpl = document.getElementById('w2p-album-dir-tpl');

		$box.empty();

		if (!data.subdirs || !data.subdirs.length || !tpl) {
			$box.append($('<p class="w2p-album-browser-empty"></p>').text(t('emptyDir', 'No subdirectories here.')));
			return;
		}

		data.subdirs.forEach(function (dir) {
			var node = tpl.content.firstElementChild.cloneNode(true);
			node.setAttribute('data-path', dir.path);
			node.querySelector('.w2p-album-dir-name').textContent = dir.name;
			$box.append(node);
		});
	}

	/**
	 * 打开一个目录（列出它的子目录）。
	 *
	 * @param {string}  path    目录路径；空串表示回到浏览根。
	 * @param {boolean} silent  是否静默（不显示 loading）。
	 */
	function doBrowse(path, silent) {
		var $box = $('#w2p-album-browser');

		if (!silent) {
			$box.addClass('w2p-album-browser-loading');
			$('#w2p-album-scope-hint').text(t('browsing', 'Opening directory...'));
		}

		request({ action: 'w2p_album_browse', path: path || '' })
			.done(function (res) {
				if (!res || !res.success) {
					window.w2p.toast((res && res.data) || t('browseFailed', 'Failed to open the directory.'), 'error');
					return;
				}

				var data = res.data;

				current.path = data.path;
				current.parent = data.parent || '';
				current.root = data.root || current.root;

				$('#w2p-album-path').val(data.path);
				$('#w2p-album-up-btn').prop('disabled', importing || !data.parent);

				renderBrowser(data);

				if (data.scope === 'collection') {
					$('#w2p-album-scope-hint').text(t('scopeCollection', 'This folder will be expanded into %s photo sets.', (data.subdirs || []).length));
				} else {
					$('#w2p-album-scope-hint').text(t('scopeSingle', 'This folder will be treated as ONE photo set.'));
				}
			})
			.fail(function () {
				window.w2p.toast(t('browseFailed', 'Failed to open the directory.'), 'error');
				$('#w2p-album-scope-hint').text('');
			})
			.always(function () {
				$box.removeClass('w2p-album-browser-loading');
			});
	}

	/* ------------------------------------------------------------------ 结果渲染 */

	/**
	 * 渲染待导入列表。
	 */
	function renderRows() {
		var $tbody = $('#w2p-album-rows');
		var tpl = document.getElementById('w2p-album-row-tpl');

		$tbody.empty();

		if (!tpl) {
			return;
		}

		$.each(rows, function (index, row) {
			var node = tpl.content.firstElementChild.cloneNode(true);
			var $tr = $(node);

			$tr.attr('data-index', index);
			// 目录名在窄列里被省略号截断，title 属性保留完整名供悬停查看。
			$tr.find('.w2p-album-dirname').text(row.dirname).attr('title', row.dirname);
			$tr.find('.w2p-album-input-title').val(row.title);
			$tr.find('.w2p-album-input-number').val(row.number);
			// 界面显示/输入用 yyyy.mm.dd；提交后由服务端归一化回无点 Ymd 底库格式。
			$tr.find('.w2p-album-input-date').val(row.date_display || row.date || '');
			$tr.find('.w2p-album-cell-count').text(row.image_count);

			// 工作室：可编辑输入框（datalist 给既有词条候选），命中即用、不中即新建。
			renderStudioInput($tr.find('.w2p-album-cell-studio'), row);

		// 模特：标签 + × 删除。
		// 命中的（绿实线）留用即关联；未命中且判定为描述的（灰虚线）默认不建，点一下才「将新建」。
		var $models = $tr.find('.w2p-album-cell-models');
		var modelCount = 0;
		$.each(row.models || [], function (i, name) {
			appendModelTag($models, name, 'linked');
			modelCount++;
		});
		$.each(row.models_new || [], function (i, name) {
			appendModelTag($models, name, 'create');
			modelCount++;
		});
		$.each(row.models_desc || [], function (i, name) {
			appendModelTag($models, name, 'desc');
			modelCount++;
		});
		if (modelCount === 0) {
			$models.text('—');
		}

			// 状态
			var $status = $tr.find('.w2p-album-cell-status');
			var status = rowStatus(row);
			if (status === 'exists') {
				badge($status, t('statusExists', 'Exists'), 'w2p-album-tag-muted');
			} else if (status === 'resume') {
				badge($status, t('statusResume', 'Resume'), 'w2p-album-tag-warn');
			} else if (status === 'noimage') {
				badge($status, t('statusNoImage', 'No images'), 'w2p-album-tag-err');
			} else if (status === 'review') {
				badge($status, t('statusReview', 'Needs review'), 'w2p-album-tag-warn');
			} else if (status === 'warning') {
				badge($status, t('statusWarning', 'Warnings'), 'w2p-album-tag-warn');
			} else {
				badge($status, t('statusNew', 'New'), 'w2p-album-tag-ok');
			}

			if (row.warnings && row.warnings.length) {
				$status.attr('title', row.warnings.join(' '));
			}

			// 丢弃：只有「本模组已经建过文章」的行才有意义（中断遗留的、以及已导完的都能清）。
			renderDiscardButton($tr, row);

			var $check = $tr.find('.w2p-album-row-check');
			if (isImportable(row)) {
				$check.prop('checked', true);
			} else {
				$check.prop('checked', false).prop('disabled', true);
			}

			$tbody.append($tr);
		});

		$('#w2p-album-empty').prop('hidden', rows.length > 0);
		$('#w2p-album-select-new-btn').prop('disabled', importing || rows.length === 0);
		$('#w2p-album-check-all').prop('disabled', importing || rows.length === 0);
		refreshImportButton();
	}

	/**
	 * 渲染「丢弃」按钮。
	 *
	 * 两条路径：本模组已经建过文章（existing_id），或者本轮刚创建成功（$tr.data('album-id')）。
	 * 没有记录就不显示——源目录没被碰过，不需要清理。
	 *
	 * @param {jQuery} $tr  行。
	 * @param {Object} row  候选数据。
	 */
	function renderDiscardButton($tr, row) {
		var tpl = document.getElementById('w2p-album-discard-tpl');
		var $cell = $tr.find('.w2p-album-cell-action');

		$cell.empty();

		if (!tpl) {
			return;
		}

		var albumId = $tr.data('album-id') || row.existing_id || 0;

		if (!albumId) {
			return;
		}

		var node = tpl.content.firstElementChild.cloneNode(true);

		node.setAttribute('data-album-id', albumId);
		node.querySelector('.w2p-album-discard-label').textContent = t('discard', 'Discard');

		$cell.append(node);
	}

	/**
	 * 进度条显示/更新。
	 *
	 * @param {string} statusText 左侧文案（图集进度）。
	 * @param {string} countText  右侧文案（当前图册的图片进度）。
	 * @param {number} percent    百分比 0-100。
	 */
	function setProgress(statusText, countText, percent) {
		$('#w2p-album-progress').prop('hidden', false);
		$('#w2p-album-progress-status').text(statusText || '');
		$('#w2p-album-progress-count').text(countText || '');
		$('#w2p-album-progress-bar')[0].style.setProperty('--w2p-album-progress', Math.max(0, Math.min(100, percent)) + '%');
	}

	/**
	 * 隐藏进度条。
	 */
	function hideProgress() {
		$('#w2p-album-progress').prop('hidden', true);
	}

	/* ------------------------------------------------------------------ 扫描 */

	/**
	 * 扫描当前选中的目录。
	 */
	function doScan() {
		var path = $.trim($('#w2p-album-path').val());

		if (!path) {
			window.w2p.toast(t('browseFailed', 'Failed to open the directory.'), 'warning');
			return;
		}

		$('#w2p-album-scan-btn').prop('disabled', true);
		$('#w2p-album-scan-summary').text(t('scanning', 'Scanning...'));
		hideProgress();

		request({ action: 'w2p_album_scan', path: path })
			.done(function (res) {
				if (!res || !res.success) {
					window.w2p.toast((res && res.data) || t('scanFailed', 'Scan failed.'), 'error');
					$('#w2p-album-scan-summary').text('');
					return;
				}

				rows = res.data.rows || [];
				renderRows();

				if (rows.length === 0) {
					$('#w2p-album-scan-summary').text(t('scanEmpty', 'No photo sets found in this folder.'));
					return;
				}

				if (res.data.scope === 'collection') {
					$('#w2p-album-scan-summary').text(t('scopeCollection', 'This folder will be expanded into %s photo sets.', rows.length));
				} else {
					$('#w2p-album-scan-summary').text(t('scopeSingle', 'This folder will be treated as ONE photo set.'));
				}
			})
			.fail(function () {
				window.w2p.toast(t('scanFailed', 'Scan failed.'), 'error');
				$('#w2p-album-scan-summary').text('');
			})
			.always(function () {
				$('#w2p-album-scan-btn').prop('disabled', importing);
			});
	}

	/* ------------------------------------------------------------------ 导入 */

	/**
	 * 收集当前勾选行的提交载荷。
	 *
	 * @param {jQuery} $tr 行。
	 * @param {Object} row 对应数据。
	 * @return {Object}
	 */
	function buildPayload($tr, row) {
		return {
			action: 'w2p_album_create',
			abs_path: row.abs_path,
			title: $tr.find('.w2p-album-input-title').val(),
			number: $tr.find('.w2p-album-input-number').val(),
			// 界面是 yyyy.mm.dd，服务端统一归一化回无点 Ymd 底库格式（带点写库会毁字段）。
			date: $tr.find('.w2p-album-input-date').val(),
			studio: $tr.find('.w2p-album-input-studio').val() || '',
			human_names: JSON.stringify(collectModelNames($tr))
		};
	}

	/**
	 * 导入单个图集：建文章 + 循环分批入库。
	 *
	 * @param {jQuery}   $tr            行。
	 * @param {Object}   row            数据。
	 * @param {Function} reportProgress 回调 (done, total, title)。
	 * @return {jQuery.Deferred} resolve 时给服务端最后一批的返回（含 finalized.move）。
	 */
	function importOneRow($tr, row, reportProgress) {
		var dfd = $.Deferred();

		$tr.addClass('w2p-album-row-importing');

		request(buildPayload($tr, row))
			.done(function (res) {
				if (!res || !res.success) {
					dfd.reject((res && res.data) || t('importFailed', 'Import failed.'));
					return;
				}

				var albumId = res.data.album_id;
				var total = res.data.total || 0;
				var srcDir = res.data.src_dir;
				var offset = 0;

				$tr.data('album-id', albumId);
				reportProgress(0, total, row.title);

				var attempts = 0;

				function nextBatch() {
					// 停止：当前批不打断，但不再发起下一批。这一册会停在 importing，
					// 重新扫描时显示为 Resume，点一下即可续跑补齐。
					if (stopRequested) {
						dfd.reject('stopped');
						return;
					}

					request({
						action: 'w2p_album_import_batch',
						album_id: albumId,
						src_dir: srcDir,
						// ⚠️ 字段名必须是 w2p_offset，不能叫 offset：
						// us-core 的 US_Filter_Indexer 会读 $_POST['offset'] 当自己全站重建索引的游标。
						w2p_offset: offset
					}, BATCH_TIMEOUT)
						.done(function (batch) {
							if (batch && batch.success) {
								attempts = 0;

								var data = batch.data;
								offset = data.next_offset;

								reportProgress(offset, data.total, row.title);

								if (data.done) {
									dfd.resolve(data);
									return;
								}

								nextBatch();
								return;
							}

							// 服务端明确回了业务错误（不是网络/池子问题）→ 不重试。
							dfd.reject((batch && batch.data) || t('importFailed', 'Import failed.'));
						})
						.fail(function (xhr, textStatus) {
							// 超时 / 网络抖动 / FPM 池子打满：请求拿不到响应，但服务端很可能已经写进去了。
							// 服务端以源图路径为幂等键，重发同一个 offset 是安全的：已入库的会被复用，
							// 不会重复入库、也不会往正文追加重复 <img>。等一会儿再重试，给池子腾出子进程。
							if (attempts < BATCH_RETRY) {
								attempts++;
								window.setTimeout(nextBatch, BATCH_RETRY_DELAY);
								return;
							}

							dfd.reject(
								'timeout' === textStatus
									? t('importTimeout', 'The server did not respond in time. Sets already imported are kept — scan again to resume the rest.')
									: t('importFailed', 'Import failed.')
							);
						});
				}

				if (total === 0) {
					dfd.resolve({ album_id: albumId, total: 0, done: true, failed: [], finalized: null });
					return;
				}

				nextBatch();
			})
			.fail(function () {
				dfd.reject(t('importFailed', 'Import failed.'));
			});

		return dfd;
	}

	/**
	 * 导入入口：按 CONCURRENCY 并发跑队列。
	 *
	 * 进度口径（用户明确要求）：
	 *  · 左侧 = 已完成图集数 / 图集总数；
	 *  · 右侧 = 当前正在导入的那一册的 已完成图片 / 该册图片总数；
	 *  · 进度条 = (已完成图集 + 当前册内完成比例) / 图集总数 —— 既平滑又与左侧口径一致。
	 */
	function doImport() {
		var $checked = $('#w2p-album-rows .w2p-album-row-check:checked:not(:disabled)');

		if (!$checked.length) {
			window.w2p.toast(t('nothingSelected', 'Please select at least one set.'), 'warning');
			return;
		}

		window.w2p.confirm(t('confirmImport', 'Import the selected sets now?'), function () {
			var queue = [];

			$checked.each(function () {
				var $tr = $(this).closest('.w2p-album-row');
				var row = rows[$tr.data('index')];
				if (row) {
					queue.push({ $tr: $tr, row: row });
				}
			});

			importing = true;
			stopRequested = false;
			setNavFrozen(true);
			$('#w2p-album-stop-btn').prop('hidden', false).prop('disabled', false);

			var totalSets = queue.length;
			var doneSets = 0;
			var active = {};
			var seq = 0;
			var running = 0;
			var failures = [];
			var lastMove = '';

			function refreshProgress() {
				var latestKey = null;
				var latestSeq = -1;

				$.each(active, function (key, info) {
					if (info.seq > latestSeq) {
						latestSeq = info.seq;
						latestKey = key;
					}
				});

				var fraction = 0;
				var currentText = '';

				if (null !== latestKey) {
					var info = active[latestKey];
					fraction = info.total > 0 ? (info.done / info.total) : 0;
					currentText = t('progressCurrent', 'Current: %1$s — %2$s / %3$s', info.title, info.done, info.total);
				}

				var overall = totalSets > 0 ? ((doneSets + fraction) / totalSets) * 100 : 0;

				setProgress(
					t('progressSets', 'Sets: %1$s / %2$s', doneSets, totalSets),
					currentText,
					overall
				);
			}

			function finish() {
				importing = false;
				stopRequested = false;
				setNavFrozen(false);
				$('#w2p-album-stop-btn').prop('hidden', true);
				refreshImportButton();
				refreshProgress();

				if (failures.length) {
					window.w2p.toast(
						t('importDone', 'Import complete.') + ' ' + t('importFailedCount', '%s failed.', failures.length),
						'warning'
					);
				} else {
					window.w2p.toast(t('importDone', 'Import complete.'), 'success');
				}
			}

			function runOne(item) {
				var $tr = item.$tr;
				var row = item.row;
				var key = 'k' + (seq++);

				active[key] = {
					title: row.title || row.dirname,
					done: 0,
					total: row.image_count || 0,
					seq: seq
				};
				refreshProgress();

				return importOneRow($tr, row, function (done, total, title) {
					active[key] = { title: title, done: done, total: total, seq: ++seq };
					refreshProgress();
				})
					.done(function (data) {
						delete active[key];
						doneSets++;

						$tr.removeClass('w2p-album-row-importing').addClass('w2p-album-row-done');
						$tr.find('.w2p-album-cell-status').empty();
						badge($tr.find('.w2p-album-cell-status'), '✓ ' + (data.total || 0), 'w2p-album-tag-ok');
						$tr.find('.w2p-album-row-check').prop('checked', false).prop('disabled', true);
						$tr.data('album-id', data.album_id);

						// 源图移动结果：成功给出路径，失败给出提示（都不影响「已导入」这个事实）。
						if (data.finalized && data.finalized.move) {
							if (data.finalized.move.moved) {
								lastMove = t('sourceMoved', 'Source folder moved to %s', data.finalized.move.path);
							} else {
								lastMove = t('sourceMoveFailed', 'Imported, but the source folder could not be moved.');
							}
							$tr.find('.w2p-album-cell-status').attr('title', lastMove);
						}

						renderDiscardButton($tr, row);

						if (data.failed && data.failed.length) {
							failures.push({ dir: row.dirname, files: data.failed });
						}
					})
					.fail(function (message) {
						delete active[key];

						if ('stopped' === message) {
							$tr.removeClass('w2p-album-row-importing');
							return;
						}

						failures.push({ dir: row.dirname, message: message });
						$tr.removeClass('w2p-album-row-importing').addClass('w2p-album-row-failed');
						$tr.find('.w2p-album-cell-status').empty();
						badge($tr.find('.w2p-album-cell-status'), '✗', 'w2p-album-tag-err');
					});
			}

			function pump() {
				if (stopRequested) {
					if (running === 0) {
						window.w2p.toast(t('stopped', 'Stopped.'), 'warning');
						finish();
					}
					return;
				}

				while (running < CONCURRENCY && queue.length) {
					running++;
					runOne(queue.shift()).always(function () {
						running--;
						refreshProgress();
						pump();
					});
				}

				if (running === 0 && !queue.length) {
					finish();
				}
			}

			pump();
		});
	}

	/* ------------------------------------------------------------------ 事件绑定 */

	/**
	 * 绑定事件。
	 */
	function bind() {
		// 目录下钻
		$(document).on('click', '.w2p-album-dir-item', function () {
			if (importing) {
				window.w2p.toast(t('navFrozen', 'Please wait for the import to finish.'), 'warning');
				return;
			}
			doBrowse($(this).attr('data-path'));
		});

		$(document).on('click', '#w2p-album-up-btn', function () {
			if (importing || !current.parent) {
				return;
			}
			doBrowse(current.parent);
		});

		$(document).on('click', '#w2p-album-open-btn', function () {
			if (importing) {
				return;
			}
			doBrowse($.trim($('#w2p-album-path').val()));
		});

		// 路径框回车 = 打开
		$(document).on('keydown', '#w2p-album-path', function (e) {
			if (13 === e.which) {
				e.preventDefault();
				$('#w2p-album-open-btn').trigger('click');
			}
		});

		// 扫描
		$(document).on('click', '#w2p-album-scan-btn', function () {
			if (!importing) {
				doScan();
			}
		});

		// 模特标签：× 删除（剪掉这个识别到的模特）
		$(document).on('click', '.w2p-album-tag-x', function (e) {
			e.preventDefault();
			e.stopPropagation();
			if (importing) {
				return;
			}
			$(this).closest('.w2p-album-tag').remove();
		});

		// 模特标签：未命中的点标签本体，在「描述（不建）」与「将新建」之间切换
		$(document).on('click', '.w2p-album-tag', function (e) {
			if (importing || $(e.target).hasClass('w2p-album-tag-x')) {
				return;
			}

			var $tag = $(this);
			var kind = $tag.attr('data-kind');

			if ('desc' === kind) {
				$tag.attr('data-kind', 'create')
					.removeClass('w2p-album-tag-muted')
					.addClass('w2p-album-tag-ok')
					.attr('title', t('tagWillCreate', 'Will be created as a new model.'));
			} else if ('create' === kind) {
				$tag.attr('data-kind', 'desc')
					.removeClass('w2p-album-tag-ok')
					.addClass('w2p-album-tag-muted')
					.attr('title', t('tagDesc', 'Looks like a description, not a model. Click to create it as a model anyway.'));
			}
		});

		// 丢弃一条记录（中断遗留的、或已导完的都能清；源目录不动）
		$(document).on('click', '.w2p-album-discard-btn', function () {
			var $btn = $(this);
			var albumId = parseInt($btn.attr('data-album-id'), 10);

			if (!albumId) {
				return;
			}

			window.w2p.confirm(t('discardConfirm', 'Delete this album record and all images it imported?'), function () {
				$btn.prop('disabled', true);

				request({ action: 'w2p_album_discard', album_id: albumId })
					.done(function (res) {
						if (!res || !res.success) {
							window.w2p.toast((res && res.data) || t('discardFailed', 'Failed to discard the album.'), 'error');
							$btn.prop('disabled', false);
							return;
						}

						window.w2p.toast(t('discarded', 'Album record discarded.'), 'success');

						var $tr = $btn.closest('.w2p-album-row');
						var row = rows[$tr.data('index')];

						// 记录已清掉 → 该行回到「未导入」：重新勾选、状态回到 New、去掉丢弃按钮
						if (row) {
							row.existing_id = 0;
							row.existing_state = '';
						}
						$tr.removeData('album-id');
						$tr.find('.w2p-album-row-check').prop('checked', true).prop('disabled', false);
						$tr.find('.w2p-album-cell-status').empty();
						badge($tr.find('.w2p-album-cell-status'), t('statusNew', 'New'), 'w2p-album-tag-ok');
						$tr.find('.w2p-album-cell-action').empty();

						refreshImportButton();
					})
					.fail(function () {
						window.w2p.toast(t('discardFailed', 'Failed to discard the album.'), 'error');
						$btn.prop('disabled', false);
					});
			});
		});

		// 停止导入（跑完当前批就停；已完成的图集保留）
		$(document).on('click', '#w2p-album-stop-btn', function () {
			if (!importing || stopRequested) {
				return;
			}

			// 注意：window.w2p.confirm(message, onConfirm) 的回调不带参数、this 也不是按钮，
			// 所以先把按钮存下来，别在回调里用 $(this)。
			var $btn = $(this);

			window.w2p.confirm(t('stopConfirm', 'Stop importing?'), function () {
				stopRequested = true;
				$btn.prop('disabled', true);
			});
		});

		// 勾选
		$(document).on('change', '#w2p-album-check-all', function () {
			var checked = $(this).prop('checked');
			$('#w2p-album-rows .w2p-album-row-check:not(:disabled)').prop('checked', checked);
			refreshImportButton();
		});

		$(document).on('change', '.w2p-album-row-check', function () {
			var $all = $('#w2p-album-rows .w2p-album-row-check:not(:disabled)');
			var $checked = $all.filter(':checked');
			$('#w2p-album-check-all').prop('checked', $all.length > 0 && $all.length === $checked.length);
			refreshImportButton();
		});

		$(document).on('click', '#w2p-album-select-new-btn', function () {
			if (importing) {
				return;
			}
			$('#w2p-album-rows .w2p-album-row-check').each(function () {
				var $check = $(this);
				if ($check.prop('disabled')) {
					return;
				}
				var $tr = $check.closest('.w2p-album-row');
				var row = rows[$tr.data('index')];
				$check.prop('checked', !!row && rowStatus(row) === 'new');
			});
			$('#w2p-album-check-all').prop('checked', false);
			refreshImportButton();
		});

		// 导入
		$(document).on('click', '#w2p-album-import-btn', function () {
			if (!importing) {
				doImport();
			}
		});

		// 首次加载：列出浏览起点的子目录（挂载不可读时 Open 是禁用的，直接跳过）
		if (!$('#w2p-album-open-btn').prop('disabled')) {
			doBrowse('');
		}
	}

	$(bind);
})(jQuery);
