---
name: wp-security-hardening
description: WordPress 插件安全加固执行手册。触发条件：修复安全漏洞、加固输入验证、清理卸载逻辑、处理 nonce/权限/转义。动作：代码修复、模式标准化、uninstall.php 创建。
---

# WordPress 插件安全加固执行手册

当收到安全加固指令时，请严格按照以下标准模式执行修复。

---

## 1. 数据剥离 (Sanitization) — 输入清洗

**原则：** 所有来自用户的数据在使用前必须清洗。

### 标准模式

```php
// 文本字段
$name = sanitize_text_field( wp_unslash( $_POST['name'] ) );

// 整数 ID
$post_id = absint( $_POST['post_id'] );

// 邮箱
$email = sanitize_email( wp_unslash( $_POST['email'] ) );

// URL
$url = esc_url_raw( wp_unslash( $_POST['url'] ) );

// 多行文本
$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ) );

// 数组（递归清洗）
$options = map_deep( wp_unslash( $_POST['options'] ), 'sanitize_text_field' );
```

### 必须清洗的超全局变量
- `$_POST`, `$_GET`, `$_REQUEST`, `$_SERVER`, `$_COOKIE`

### 禁止操作
- 禁止直接使用 `$_POST['key']` 赋值给变量
- 禁止将未清洗数据直接传入 `$wpdb->query()`
- 禁止将未清洗数据用于文件路径拼接

---

## 2. 数据转义 (Escaping) — 输出清洗

**原则：** 所有输出到 HTML/属性/JS 的数据必须转义。

### 标准模式

```php
// HTML 内容
echo esc_html( $title );

// HTML 属性
echo '<input value="' . esc_attr( $value ) . '">';

// URL
echo '<a href="' . esc_url( $link ) . '">';

// JS 字符串
echo '<script>var data = ' . wp_json_encode( $data ) . ';</script>';

// HTML 块（允许的标签）
echo wp_kses_post( $content );
```

### 转义函数选择表

| 输出位置 | 使用函数 |
|----------|----------|
| HTML 标签内容 | `esc_html()` |
| HTML 属性值 | `esc_attr()` |
| URL | `esc_url()` |
| JavaScript | `esc_js()` |
| SQL 查询 | `$wpdb->prepare()` |
| 富文本内容 | `wp_kses_post()` |
| 电话号码等 | `esc_textarea()` |

---

## 3. Nonce 校验标准模式

**原则：** 所有状态变更操作必须校验 nonce。

### 标准模式

```php
// 生成 nonce（在表单/链接中）
$nonce = wp_create_nonce( 'w2p_action_name' );
echo '<input type="hidden" name="w2p_nonce" value="' . esc_attr( $nonce ) . '">';

// 校验 nonce（在处理函数中）
// 方式一：wp_verify_nonce
if ( ! isset( $_POST['w2p_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['w2p_nonce'] ), 'w2p_action_name' ) ) {
    wp_die( esc_html__( 'Security check failed.', 'wp-genius' ) );
}

// 方式二：check_admin_referer（推荐用于 admin_post_）
check_admin_referer( 'w2p_action_name' );
```

### Nonce 命名规范
- 格式：`w2p_{模块}_{动作}`
- 示例：`w2p_smart_aui_download`, `w2p_auto_publish_run`

### 必须校验 nonce 的场景
- `admin_post_` 处理函数
- `wp_ajax_` AJAX 处理函数
- 表单提交处理
- 设置保存
- 删除操作

---

## 4. 权限检查标准模式

**原则：** 先 nonce，再 capability。

### 标准模式

```php
// AJAX 处理函数
public function handle_ajax_action() {
    // 1. 先校验 nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'w2p_action' ) ) {
        wp_send_json_error( [ 'message' => __( 'Security check failed.', 'wp-genius' ) ], 403 );
        exit;
    }

    // 2. 再检查权限
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ], 403 );
        exit;
    }

    // 3. 执行操作
    // ...
}

// admin_post 处理函数
public function handle_admin_post() {
    check_admin_referer( 'w2p_action' ); // 同时验证 nonce

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have permission.', 'wp-genius' ) );
    }

    // ...
}
```

### 常用 Capability 映射

| 操作类型 | 最低 Capability |
|----------|-----------------|
| 插件设置管理 | `manage_options` |
| 文章编辑 | `edit_posts` |
| 媒体上传 | `upload_files` |
| 用户管理 | `list_users` |
| 系统维护 | `manage_options` |

---

## 5. 开放重定向防护

**原则：** 所有用户可控的重定向 URL 必须验证。

### 标准模式

```php
// 错误做法（不安全）
$redirect = $_GET['redirect_to'];
wp_redirect( $redirect );
exit;

// 正确做法
$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
$safe_redirect = wp_validate_redirect( $redirect, admin_url( 'index.php' ) );
wp_safe_redirect( $safe_redirect );
exit;
```

### 禁止操作
- 禁止将 `$_GET/$_POST` 中的 URL 直接传给 `wp_redirect()`
- 禁止使用 `wp_redirect()` 跳转到外部域名（除非有明确业务需求且做了白名单验证）

---

## 6. AJAX 处理函数安全模板

```php
/**
 * Handle AJAX request.
 *
 * @return void
 */
public function handle_ajax_example() {
    // Step 1: Nonce verification
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'w2p_example_action' ) ) {
        wp_send_json_error( [ 'message' => __( 'Security check failed.', 'wp-genius' ) ], 403 );
        exit;
    }

    // Step 2: Capability check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ], 403 );
        exit;
    }

    // Step 3: Sanitize input
    $param = isset( $_POST['param'] ) ? sanitize_text_field( wp_unslash( $_POST['param'] ) ) : '';
    $id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

    if ( empty( $param ) || 0 === $id ) {
        wp_send_json_error( [ 'message' => __( 'Invalid parameters.', 'wp-genius' ) ], 400 );
        exit;
    }

    // Step 4: Execute business logic
    try {
        $result = $this->do_something( $param, $id );
        wp_send_json_success( [ 'data' => $result ] );
    } catch ( \Exception $e ) {
        W2P_Logger::error( $e->getMessage(), 'example-module' );
        wp_send_json_error( [ 'message' => __( 'An error occurred.', 'wp-genius' ) ] );
    }
    exit;
}
```

---

## 7. Uninstall 清理标准

**原则：** 插件删除时必须清理所有数据。

### 文件结构

```php
<?php
// uninstall.php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// 1. 清理选项
delete_option( 'w2p_settings' );
delete_option( 'w2p_auto_publish_logs' );
delete_option( 'w2p_auto_publish_last_run' );
delete_option( 'w2p_media_turbo_processed_posts' );
delete_option( 'w2p_media_engine_migrated' );

// 2. 清理 Transients
delete_transient( 'w2p_auto_publish_active_lock' );
delete_transient( 'w2p_auto_publish_scheduled_status' );

// 3. 清理 Cron 事件
wp_clear_scheduled_hook( 'w2p_auto_publish_cron' );
wp_clear_scheduled_hook( 'w2p_media_turbo_cron' );

// 4. 清理用户元数据
$users = get_users( [ 'meta_key' => 'st_local_avatar', 'fields' => 'ID' ] );
foreach ( $users as $user_id ) {
    delete_user_meta( $user_id, 'st_local_avatar' );
}

// 5. 清理自定义数据库表（如果有）
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}w2p_custom_table" );
```

---

## 8. SQL 注入防护

**原则：** 所有动态查询必须使用 `$wpdb->prepare()`。

### 标准模式

```php
global $wpdb;

// 错误做法（不安全）
$wpdb->query( "SELECT * FROM $wpdb->posts WHERE post_title = '$title'" );

// 正确做法
$wpdb->query(
    $wpdb->prepare(
        "SELECT * FROM $wpdb->posts WHERE post_title = %s",
        $title
    )
);

// 多参数
$wpdb->query(
    $wpdb->prepare(
        "SELECT * FROM $wpdb->posts WHERE post_status = %s AND post_type = %s",
        $status,
        $type
    )
);
```

---

## 9. 文件操作安全

```php
// 验证文件类型
$allowed_types = [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ];
$file_ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
if ( ! in_array( $file_ext, $allowed_types, true ) ) {
    return new \WP_Error( 'invalid_type', __( 'File type not allowed.', 'wp-genius' ) );
}

// 验证 MIME 类MIME 类型
$mime = wp_check_filetype( $filename );
if ( empty( $mime['type'] ) ) {
    return new \WP_Error( 'invalid_mime', __( 'Invalid MIME type.', 'wp-genius' ) );
}

// 安全文件路径
$base_dir = wp_upload_dir()['basedir'];
$full_path = realpath( $base_dir . '/' . $relative_path );
if ( $full_path === false || strpos( $full_path, $base_dir ) !== 0 ) {
    return new \WP_Error( 'path_traversal', __( 'Invalid file path.', 'wp-genius' ) );
}
```

---

## 10. SSL/TLS 配置

```php
// SMTP 连接应默认验证 SSL
$phpmailer->smtpConnect( [
    'ssl' => [
        'verify_peer'       => true,
        'verify_peer_name'  => true,
        'allow_self_signed' => false,
    ],
] );

// 如需支持自签名证书，通过 filter 控制
$verify = apply_filters( 'w2p_smtp_ssl_verify', true );
$phpmailer->smtpConnect( [
    'ssl' => [
        'verify_peer'       => $verify,
        'verify_peer_name'  => $verify,
    ],
] );
```

---

**执行指令**：
当执行安全加固时，请按以下顺序操作：
1. 逐文件扫描，列出所有需要修复的安全问题
2. 按优先级排序（CRITICAL > HIGH > MEDIUM > LOW）
3. 按上述标准模式逐个修复
4. 修复后验证：确保没有引入新的安全问题
5. 输出修复报告：列出每个文件的修改内容
