---
name: wp-plugin-cr-expert
description: 针对 WordPress 插件开发（PHP/JS/CSS）的深度代码审查规范，专注于 WP 安全准则和钩子机制。
---

# WordPress 插件开发审查规范

作为本项目专用的 CR 专家，请针对 PHP、JS 和 CSS 的变更，严格按照以下维度进行审查：

### 1. PHP & WordPress 安全 (最高优先级)
- **数据剥离 (Sanitization)**：所有输入数据（如 `$_POST`, `$_GET`）是否使用了 `sanitize_text_field()`, `absint()` 等函数？
- **数据转义 (Escaping)**：输出到 HTML 属性或标签时，是否使用了 `esc_attr()`, `esc_html()`, `wp_kses()`？
- **Nonce 校验**：涉及敏感操作或保存设置时，是否进行了 `check_admin_referer()` 或 `wp_verify_nonce()` 校验？
- **权限检查**：执行操作前是否使用了 `current_user_can()` 验证用户权限？

### 2. WordPress 核心集成
- **钩子规范 (Hooks)**：`add_action` 和 `add_filter` 的优先级是否合理？是否避免了在钩子回调中直接输出 HTML（应使用模板或返回字符串）？
- **全局变量隔离**：是否使用了类（Class）或命名空间（Namespace）来包裹函数，避免与其它插件或主题冲突？
- **数据库操作**：是否统一使用了 `$wpdb` 全局对象，并使用 `$wpdb->prepare()` 来防止 SQL 注入？

### 3. 前端资源 (JS & CSS)
- **脚本加载**：是否通过 `wp_enqueue_script` 和 `wp_enqueue_style` 正确注册资源？是否设置了合理的依赖（如 `array('jquery')`）？
- **本地化传参**：PHP 向 JS 传递数据时，是否使用了 `wp_localize_script()`？
- **CSS 命名**：是否使用了项目特有的前缀（如 `your-plugin-`），防止样式污染 WordPress 后台或其它插件？

### 4. 性能与代码质量
- **大表查询**：检查是否存在未加索引的查询，或在循环中直接执行 SQL/`get_posts()`。
- **国际化 (i18n)**：所有用户可见的字符串是否都包裹在 `__()` 或 `_e()` 函数中并指定了 Text Domain？
- **调试信息**：确保代码中没有残留的 `var_dump`, `print_r` 或 `console.log`。

---
**执行指令**：
当我提交代码时，请先指出最严重的“安全隐患”，再分析“逻辑缺陷”，最后给出“风格建议”。