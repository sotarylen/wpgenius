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
- **SubAgent 审核员与交付闭环 (Mandatory SubAgent Auditor)**：
  - **任务前置审查**：接收或处理任意任务时，**必须同步创建 SubAgent**（角色为“审核员”）。主 Agent 在动手前将计划/方案提交给审核员，审核员调用 `ponytail` 技能（严格遵循 Ponytail 决策阶梯：是否必要 YAGNI -> 现有复用 -> 原生支持 -> 单行最简 -> 最小 Diff）进行审查。**只有审核员审查通过后，主 Agent 才能开始执行。**
  - **执行后置评估**：执行与测试完成后，必须将最终处理结果（改动 Diff、验证情况）提交给审核员 SubAgent 进行评估验收，**审核员评估通过后方可向用户交付**。
- **工程与 Ponytail 准则**：
  - 严格遵循 SOLID / DRY 原则，杜绝代码膨胀与多余抽象；修复必须定位根因（Root-cause），严禁为修症状而到处盲目堆砌补丁。
  - 严禁引入未使用的多余脚手架或僵尸代码。

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
- **禁止**未经审核员 SubAgent 执行 Ponytail 审查通过即开始编码/执行。
- **禁止**未经审核员 SubAgent 评估通过即直接向用户交付。
- **禁止**在 PHP/JS 中写内联样式 (`style="..."`)。
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