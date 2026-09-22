---
trigger: always_on
---

# WordPress 开发核心规则 (Workspace Rules)

## 1. 全局硬约束 (Global Hard Constraints)
**违反即拒绝**：以下规则是不可逾越的底线。
- **语言**：所有文档、注释、思维链、对话回复**必须**使用**简体中文**。
- **安全**：
  - 严禁硬编码 API Key / 密码。
  - 外部输入**必须**经过 `sanitize_*` 处理。
  - 数据库操作**必须**使用 `$wpdb->prepare()`。
  - 敏感操作**必须**验证 Nonce (`wp_verify_nonce`)。
- **SubAgent 审查与校验闭环 (Mandatory SubAgent Review & Verify)**：

  **四步强制流程，顺序不可换、不可合并、不可跳**：

  ```
  ① 前置审查（审查子代理 Reviewer） → ② 执行改动 → ③ QA 子代理校验（编码 + 执行结果） → ④ 交付
  ```

  只做静态检查就宣称完成，视为违规。

  - **① 任务前置审查（审查子代理 Reviewer）**：接收任务后、动手前，**必须立即创建审查子代理**。主 Agent 先把计划/方案与将要改动的代码提交给它，由它调用 `ponytail` 技能审查（决策阶梯：是否必要 YAGNI → 现有复用 → 标准库 → 平台原生 → 已装依赖 → 一行 → 最小实现）。**只有审查通过后，主 Agent 才能开始执行。**
    - **启动方式**：用 `Agent` 工具、`subagent_type: general-purpose`（独立上下文，不污染主上下文）。该类型具备写权限，**"只读"是提示词级约束**，提示词中必须注明。
    - **ponytail 位置**：全局版 `/Users/sotary/.agents/skills/ponytail/SKILL.md`（含完整 Intensity / Output 章节，**以它为准**，不要满盘搜索）；项目内 `.agent/skills/ponytail/SKILL.md` 为精简副本。
    - **强度**：默认 full。需调整时在审查开场声明 `ponytail: lite|full|ultra`。
    - **先理解再偷懒**：必须先把真实调用链追到端到端清楚（改哪个文件、谁调它、还有谁受影响）。阶梯缩短的是**方案**，绝不是**阅读量**。
    - **修 bug 修根因**：动手前 grep 待改函数的**所有**调用方，在共同入口处一次修掉。只补 ticket 点名的那条路径，兄弟调用方照样是坏的。
    - **审查结论必须逐条落到结论**并写进执行说明，不许只在脑子里过。
    - 审查结论与用户原始需求冲突时（例如数学上互斥），**停下来提决策点**，不要默默替用户选一个执行。
  - **② 执行改动**：按审查结论改，最小 Diff、最少文件。改的过程中发现审查结论错了 → 回 ① 重审，**禁止边写边堆**。
  - **③ 执行后置校验（QA 子代理 Verifier）**：改动完成后**必须另起一个独立上下文的 QA 子代理**（**不得复用 ① 的审查子代理**，两者职责分离）复核，同时校验**两个维度**：
    - **启动方式**：用 `Agent` 工具、`subagent_type: general-purpose`（独立上下文）。注意该类型**具备写权限**，下面的"只读"是**提示词级约束、不是工具级强制**——所以提示词第一行必须写死只读要求。
    - **A. 编码质量（静态门禁，真跑，不许目测）**：

      ```bash
      cd /Users/sotary/Sites/web/wp-content/plugins/wpgenius   # 以下命令均在插件根目录执行
      vendor/bin/phpcs --standard=phpcs.xml.dist <改动文件>     # PHP 规范
      /opt/homebrew/bin/php -l <改动文件>                       # PHP 语法（全局 php 8.5.10）
      /Users/sotary/.workbuddy/binaries/node/versions/22.22.2-3/bin/node --check <改动.js>   # JS 语法
      ```

      注：`~/.workbuddy/binaries/` 下**只有 node 和 python，没有 php**，别去那儿找；wp-cli 在 `/usr/local/bin/wp`。只报**本次改动引入**的问题，既有告警必须标注“非本次引入”。
    - **B. 执行结果（行为验证，必须有可复现的客观证据）**：不接受“看起来对了”，要拿到断言结果、渲染/DOM 坐标、DB 直查、接口返回等**原始输出**；非平凡逻辑（分支、循环、解析、金额/安全路径）必须留下**一个可跑的检查**（自检页 / `assert` 自检脚本 / 小测试），要求是「逻辑坏了它会变红」；有降级路径的必须验证**降级不劣于现状**；能在真实环境（本地 OrbStack LNMP / `192.168.10.100`）跑就必须真跑，跑不了要在交付时**明说哪块没验、为什么**。
    - **QA 子代理的输入**必须是：**文件路径 + 可执行的校验命令 + 需求原文**。禁止给“我改好了你帮我确认下”这类输入——那是让子代理复述主代理的结论，等于没校验。
    - **QA 子代理只读（提示词级约束）**：只允许读文件 + 跑命令，**不得修改任何文件**；发现 FAIL 只回报，由主 Agent 回 ② 修。**严禁"顺手帮忙修一下"**——那会污染独立校验的意义。
    - **产出格式**：逐项 PASS / FAIL + 证据（命令 + 原始输出片段）。出现任何 FAIL → 回 ② 修 → **重跑 ③**，直到 PASS 或明确交接未决项。
  - **④ 交付**：交付内容必须包含——改动清单（哪些文件、改了什么）；**QA 结论（PASS / FAIL 明细，FAIL 不许藏）**；未验证的部分 + 原因；遗留决策点；提交状态（是否已 commit、是否已 push）。
  - **豁免（不必走全流程）**：纯问答、纯调研、纯文档/报告、只读排查与给建议、用户明确要求“直接做 / 别审查”、以及单行文案/注释/错别字这类微改。拿不准 → 按流程走，宁可多一步。
- **工程与 Ponytail 准则**：
  - 严格遵循 SOLID / DRY 原则，杜绝代码膨胀与多余抽象；修复必须定位根因（Root-cause），严禁为修症状而到处盲目堆砌补丁。
  - 严禁引入未使用的多余脚手架或僵尸代码。
  - **不做未被要求的抽象**：单实现的接口、单产品的工厂、永不变化的配置项；删除优于新增，无聊优于聪明。
  - **Ponytail 输出格式**：代码在前，其后最多三行说明（跳过了什么、什么时候该补），模式 `[code] → skipped: [X], add when [Y].`；解释比代码长就删解释。用户明确要的报告/讲解不算债，照给。
  - **有意简化的角落**用 `ponytail:` 注释标注天花板与升级路径。
  - **不许偷懒的（Never lazy about）**：信任边界的输入校验、防数据丢失的错误处理、安全措施、无障碍基础、用户明确要求的东西。**用户坚持要全量实现 → 照做，不再争辩。**

## 2. 项目特定规则 (Project Rules)
**架构与规范**：
- **国际化与文本规范 (i18n & Text)**：
  - **i18n 包裹与英文源码**：所有可见字符串（面向用户的 UI 文本、通知、错误提示等）**必须**使用国际化函数（如 `__( '...', 'wp-genius' )`, `esc_html__( '...', 'wp-genius' )`, JS 端 `wp.i18n.__` 等）并指定 textdomain `'wp-genius'`；**源码中的原始字符串必须使用英文**，严禁在源码中硬编码非英文字符串作为原始文本。
  - **描述性文本简短易懂**：所有提示信息、说明文案及描述性文本尽量**简短易懂**，直奔主题，避免冗长晦涩。
- **目录分层**：
  - `includes/`: 纯 PHP 逻辑 (Class/Function)。
  - `templates/` / `views/`: HTML 结构 / 视图文件。
- **命名规范**：全局函数/类/CSS 类必须以 `w2p_` 或 `wpg_` 开头。
- **测试环境**：
  - URL: `https://web.sotarylen.com/wp-admin` (sotary / rainman)
  - CLI: `docker exec -it php_wp sh 'cd web && wp <command>'`
- **部署环境（调试前必读）**：
  - WordPress 部署在本机 **OrbStack 的 LNMP 容器**中；编排文件位于 `/Users/sotary/dev.localized/lnmp`（容器定义/端口/挂载都在此查看）。
  - `web.sotarylen.com` 为**纯本机域名**，通过本机 **hosts 文件** 解析到本地（无公网 DNS/解析记录）。
  - 涉及容器/环境排查时，先读 `/Users/sotary/dev.localized/lnmp` 下的编排与配置。
- **自动执行与确认策略 (Auto-Execution)**：
  - 在当前工作区、关联目录及本地 OrbStack 测试环境中，主动自主执行代码修改、语法 lint、测试脚本及环境排查命令，最小化手动确认打断。
  - 仅在遇到高危破坏性操作（如清空全库数据、不可逆删除外部核心配置）时才请求确认。

## 3. 负向约束 (Negative Constraint)
**禁止事项**：
- **禁止**在源码中直接硬编码非英文字符串作为可见文本（未包裹 i18n 或源码原始文本为非英文）。
- **禁止**未经 ① 审查子代理（Reviewer）执行 Ponytail 审查通过即开始编码/执行。
- **禁止**未经 ③ QA 子代理（Verifier）校验通过即直接向用户交付。
- **禁止**把 ③ 并进 ②，或只做静态门禁（A 维度）就宣称完成。
- **禁止**在 PHP/JS 中写内联样式 (`style="..."`)。需要随数据变化的动态样式（如列数、尺寸）**必须**通过 `wp_add_inline_style()` 注入 CSS 自定义属性，或改用类名/属性选择器，不得写在 HTML 的 `style` 属性里。
- **禁止**在 JS 中拼接复杂的 HTML 字符串（应使用 `template` 或克隆节点）。
- **禁止**保留未引用的“僵尸代码”或测试代码。
- **禁止**在 JS 中忽略 `undefined` / `null` 检查。

## 4. 范例 (Few-Shot Examples)

**Bad (错误示范)**:
```php
// 错误：中文直接写在源码中，没有前缀，没有转义，内联样式
function show_title($title) {
    echo "<h1 style='color:red'>" . $title . "</h1>"; 
    echo '<p>这是一个没有国际化包裹的中文描述</p>';
}
// SQL 注入风险
$wpdb->query("SELECT * FROM table WHERE id = $id");
```

**Good (正确示范)**:
```php
/**
 * 显示标题
 * @param string $title 标题文本
 */
function wpg_show_title( $title ) {
    // 1. 前缀 wpg_
    // 2. 转义输出 esc_html
    // 3. 类名代替内联样式
    echo '<h1 class="wpg-title">' . esc_html( $title ) . '</h1>';
    
    // 4. i18n 规范：原始英文字符串 + textdomain + 简短易懂
    echo '<p class="wpg-desc">' . esc_html__( 'Chapter import completed.', 'wp-genius' ) . '</p>';
}

// 安全的 SQL 查询
$wpdb->get_results( 
    $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}table WHERE id = %d", $id ) 
);
```