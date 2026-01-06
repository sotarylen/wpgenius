<?php
if (!defined('ABSPATH')) {
    exit;
}

abstract class W2P_Abstract_Module {
    // 模块唯一 ID（目录名为默认）
    public static function id() {
        return '';
    }

    public static function name() {
        return '';
    }

    public static function description() {
        return '';
    }

    // 在插件初始化时调用
    public function init() {}

    // 在设置页中注册模块设置片段（可选）
    public function register_settings() {}

    /**
     * Render a view template for the module.
     * 
     * @param string $view_name Name of the template (without .php).
     * @param array  $args      Variables to pass to the template.
     */
    public function render_view( $view_name, $args = [] ) {
        $file = plugin_dir_path( ( new ReflectionClass( get_called_class() ) )->getFileName() ) . $view_name . '.php';
        
        if ( file_exists( $file ) ) {
            // Extract variables to local scope
            if ( ! empty( $args ) ) {
                extract( $args );
            }
            
            // Standard data available to all views
            $module_id = static::id();
            $settings = $this->get_settings();
            $nonce = wp_create_nonce( 'w2p_' . str_replace( '-', '_', $module_id ) . '_nonce' );

            include $file;
        } else {
            W2P_Logger::error( "View template not found: {$file}", 'core' );
        }
    }

    /**
     * Get module-specific settings.
     * 
     * @return array
     */
    public function get_settings() {
        return get_option( $this->settings_key(), [] );
    }

    /**
     * Get the settings key for the module.
     * 
     * @return string
     */
    public function settings_key() {
        // Allow modules to override this
        return 'w2p_' . str_replace( '-', '_', static::id() ) . '_settings';
    }

    // 激活/停用钩子（可选）
    public function activate() {}
    public function deactivate() {}
}

?>
