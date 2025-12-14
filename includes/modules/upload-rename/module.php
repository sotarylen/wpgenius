<?php
if (!defined('ABSPATH')) {
    exit;
}

class UploadRenameModule extends W2P_Abstract_Module {
    /**
     * Mapping of renamed base filenames => original base filenames for this request
     *
     * @var array
     */
    protected static $original_titles = array();
    public static function id() {
        return 'upload-rename';
    }

    public static function name() {
        return __('Upload Rename', 'wp-genius');
    }

    public static function description() {
        return __('Automatically normalize and rename uploaded files.', 'wp-genius');
    }

    public function init() {
        // 在上传预处理阶段重命名文件名
        add_filter('wp_handle_upload_prefilter', array($this, 'handle_prefilter'));

        // 注册模块设置（在启用模块时注册）
        add_action('admin_init', array($this, 'register_settings'));
        // 在插入附件数据前，允许替换 post_title 为原始文件名
        add_filter('wp_insert_attachment_data', array($this, 'maybe_replace_attachment_title'), 10, 2);
    }

    public function register_settings() {
        register_setting('word2posts_modules', 'w2p_upload_rename_pattern', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '{timestamp}_{sanitized}',
        ));
    }

    public function handle_prefilter($file) {
        if (empty($file['name'])) {
            return $file;
        }

        $pattern = get_option('w2p_upload_rename_pattern', '{timestamp}_{sanitized}');
        // 保留原始模板以便判断是否包含 {ext}
        $pattern_template = $pattern;
        // 支持 {date} 或 {date:FORMAT}，默认格式 Y-m-d
        $pattern = preg_replace_callback('/\{date(?::([^}]+))?\}/', function($m) {
            $fmt = isset($m[1]) && $m[1] ? $m[1] : 'Y-m-d';
            return date($fmt);
        }, $pattern);
        // 原始基名（不含扩展名），作为媒体标题使用（做最小的文件名清理）
        $original_base = pathinfo($file['name'], PATHINFO_FILENAME);
        $original_base = sanitize_file_name( $original_base );
        $sanitized = $original_base;
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $timestamp = time();
        $random = wp_rand(1000, 9999);

        // 当前用户信息（若已登录）
        $current_user = wp_get_current_user();
        $user_login = !empty($current_user->user_login) ? $current_user->user_login : '';
        $user_id = !empty($current_user->ID) ? $current_user->ID : 0;

        $replacements = array(
            '{timestamp}' => $timestamp,
            '{sanitized}' => $sanitized,
            '{rand}'      => $random,
            '{datetime}' => date('YmdHis'),
            '{year}'     => date('Y'),
            '{month}'    => date('m'),
            '{day}'      => date('d'),
            '{hour}'     => date('H'),
            '{minute}'   => date('i'),
            '{second}'   => date('s'),
            '{user_id}'  => $user_id,
            '{user_login}' => $user_login,
            '{orig}'     => $original_base,
            '{ext}'      => $ext,
            '{uniqid}'   => uniqid(),
        );

        // 执行替换
        $new_name = strtr( $pattern, $replacements );

        // 如果模板没有包含 {ext}，则自动追加扩展名
        if ( false === strpos( $pattern_template, '{ext}' ) ) {
            $new_name = $new_name . ( $ext ? '.' . $ext : '' );
        }

        // 确保文件名不包含非法字符
        $new_sanitized = sanitize_file_name($new_name);
        $file['name'] = $new_sanitized;

        // 记录映射：新基名 => 原始基名（未含扩展）
        $new_base = pathinfo( $new_sanitized, PATHINFO_FILENAME );
        self::$original_titles[ $new_base ] = $original_base;

        return $file;
    }

    /**
     * Replace attachment post_title with original base filename when available
     *
     * @param array $data
     * @param array $postarr
     * @return array
     */
    public function maybe_replace_attachment_title( $data, $postarr ) {
        if ( empty( $data['post_title'] ) ) {
            return $data;
        }

        $current_base = sanitize_file_name( $data['post_title'] );
        if ( isset( self::$original_titles[ $current_base ] ) ) {
            // 使用原始基名（未含扩展）作为标题
            $data['post_title'] = self::$original_titles[ $current_base ];
        }

        return $data;
    }
}

?>
