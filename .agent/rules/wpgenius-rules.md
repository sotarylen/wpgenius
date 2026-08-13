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
- **工程**：严格遵循 SOLID / DRY 原则，禁止重复造轮子。

## 2. 项目特定规则 (Project Rules)
**架构与规范**：
- **目录分层**：
  - `includes/`: 纯 PHP 逻辑 (Class/Function)。
  - `templates/`: HTML 结构 / 视图文件。
- **命名规范**：全局函数/类/CSS 类必须以 `w2p_` 或 `wpg_` 开头。
- **测试环境**：
  - URL: `https://web.sotarylen.com/wp-admin` (sotary / rainman)
  - CLI: `docker exec -it php_wp sh 'cd web && wp <command>'`
- **国际化**：可见文本使用 `__( 'text', 'wp-genius' )`。

## 3. 负向约束 (Negative Constraint)
**禁止事项**：
- **禁止**在 PHP/JS 中写内联样式 (`style="..."`)。
- **禁止**在 JS 中拼接复杂的 HTML 字符串（应使用 `template` 或克隆节点）。
- **禁止**保留未引用的“僵尸代码”或测试代码。
- **禁止**在 JS 中忽略 `undefined` / `null` 检查。

## 4. 范例 (Few-Shot Examples)

**Bad (错误示范)**:
```php
// 没有前缀，没有转义，直接拼接 HTML
function show_title($title) {
    echo "<h1 style='color:red'>" . $title . "</h1>"; 
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
}

// 安全的 SQL 查询
$wpdb->get_results( 
    $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}table WHERE id = %d", $id ) 
);
```