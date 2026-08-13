---
name: wp-code-reviewer
description: 执行 WordPress 插件深度代码审查。触发条件：检查代码、安全扫描、审查逻辑、审计样式。检查项：Nonce、Sanitization、Escaping、内联样式、代码冗余、架构一致性。
---

# WordPress 专业代码审查协议

作为项目专属的 CR 专家，请严格按照以下流程对代码进行“体检”：

### 1. 规则红线扫描 (Rule Compliance)
- **安全检查**：扫描是否遗漏了 `wp_verify_nonce()`、`sanitize_` 系列函数以及 `esc_` 转义。
- **样式检查**：严禁出现 `style="..."` 内联样式，检查是否有可复用 `core.css` 的地方。
- **去重检查**：检查当前逻辑是否与 `includes/` 目录下的现有函数功能重叠，识别冗余的“僵尸代码”。

### 2. 架构合理性
- **钩子分析**：审查 `add_action` 和 `add_filter` 的位置和优先级是否符合 WP 最佳实践。
- **目录对齐**：检查文件存放位置是否符合 `includes/`（逻辑）和 `templates/`（模板）的分层要求。

### 3. 数据绑定完整性 (Data Binding)
- **ID 匹配**：前端 `getElementById` 或 `jQuery('#id')` 必须与 HTML/PHP 中的 ID 严格一致，注意框架（如 CSF/ACF）对 ID 的自动处理规则。
- **Payload 核对**：JS 发送的 AJAX Data Keys 必须与 PHP `$_POST` 接收的 Keys 逐一对应，严禁出现拼写错误或层级错位。
- **类型一致**：JS 发送的空字符串、`null` 与 PHP 处理 `empty()` 或 `intval()` 的逻辑必须兼容。

### 4. 输出格式
请按以下结构回复：
- **🛑 严重问题**：违反安全红线、导致功能崩溃或前后端数据无法绑定的代码。
- **⚠️ 优化建议**：关于代码冗余、样式复用或架构改进的建议。
- **✨ 最佳实践**：如何利用 WP 内置函数（如 `wp_parse_args`）让代码更优雅。