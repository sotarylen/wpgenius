---
trigger: always_on
---

# WordPress 开发核心规则 (Workspace Rules)

## 0. 测试环境
- 项目测试地址：https://web.sotarylen.com/wp-admin，账号sotary密码rainman
- WP CLI运行环境：docker exec -it php_wp sh 'cd web && wp cli命令行'

## 1. 目录结构约束 (Architecture)
- **逻辑层**：所有 Class、Method、Function 必须归档至 `includes/`。
- **表现层**：所有 HTML 结构必须存放在 `templates/`，严禁在逻辑文件中硬编码大量 HTML。
- **代码整洁**：严禁在 JS 中通过字符串拼接输出复杂的 HTML 结构。

## 2. 杜绝冗余
- **杜绝冗余函数**：在编写新逻辑前，必须先全面检索现有代码库。严禁在无视现有函数的情况下，创建功能重复或相似的新方法。
- **强制清理逻辑**：如果因调试或重构需要创建了新的方法、类或文件，**必须同步删除**已废弃的旧版本代码。严禁在项目中留下任何未被引用的冗余代码、测试残留或“僵尸”方法。

## 3. 样式管理准则 (Styling & CSS)
- **样式零容忍**：严禁在 PHP/JS 文档中使用内嵌 CSS（如 `style="..."`）。所有样式必须通过 `class` 属性实现。
- **CSS 资产复用**：修改 UI 前，**必须**先检索 `assets/css/core.css`。
  - 仅在 `core.css` 无对应样式且无法通过现有类组合实现时，方可创建新 Class。
  - 严禁创建重复或功能重叠的 CSS 类。

## 4. WP 安全与国际化 (Security & i18n)
- **防御性编程**：外部输入必过 `sanitize_text_field()`/`absint()`；敏感操作必带 `wp_verify_nonce()`。
- **数据库规范**：统一使用 `$wpdb->prepare()`，严禁 SQL 字符串拼接。
- **输出转义**：输出变量必须包裹在 `esc_html()`, `esc_attr()` 或 `wp_kses()` 中。
- **全球化**：所有可见文本强制使用 `__( 'text', 'wp-genius' )`，Text Domain 锁定为 `wp-genius`。

## 5. 命名冲突防护
- **项目前缀**：所有全局函数、类名及 CSS 类必须统一添加 `w2p_` 或 `wpg_` 前缀。

## 6. 前后端逻辑一致性 (Data Binding)
- **字段确认**：修改前端表单或 AJAX 请求时，**必须**同步核对后端接收的 `$_POST/$_GET` 键名和数据类型。
- **空值处理**：在 JS 中获取输入值时，必须考虑到 DOM 元素不存在或 ID 变更的情况，严禁无视 `undefined/null` 直接提交。
- **框架兼容**：若使用 CSF 或 ACF 等框架，必须遵循框架的字段命名规则（如 `opt_name[field_id]`），严禁凭感觉猜测 ID 或 Name 属性。