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

    // 激活/停用钩子（可选）
    public function activate() {}
    public function deactivate() {}
}

?>
