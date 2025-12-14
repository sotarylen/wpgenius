<?php
if (!defined('ABSPATH')) {
    exit;
}

class W2P_Module_Loader {
    protected $modules = array();
    protected $modules_dir;
    protected $assets_modules_dir;

    public function __construct($modules_dir = '') {
        $this->modules_dir = $modules_dir ? $modules_dir : plugin_dir_path(__FILE__) . 'modules/';
        // 添加 assets 目录作为额外的模块目录
        $this->assets_modules_dir = plugin_dir_path(__FILE__) . '../assets/modules/';
    }

    // 发现并包含 modules 目录下每个模块的 main 文件（约定为 module.php）
    public function discover() {
        // 从 includes/modules 目录加载模块
        if (is_dir($this->modules_dir)) {
            $this->load_modules_from_directory($this->modules_dir);
        }
        
        // 从 assets/modules 目录加载模块
        if (is_dir($this->assets_modules_dir)) {
            $this->load_modules_from_directory($this->assets_modules_dir);
        }
    }
    
    // 从指定目录加载模块
    protected function load_modules_from_directory($directory) {
        $dirs = scandir($directory);
        foreach ($dirs as $d) {
            if ($d === '.' || $d === '..') continue;
            $path = $directory . $d;
            if (is_dir($path)) {
                $main = $path . '/module.php';
                if (file_exists($main)) {
                    include_once $main;
                    $class = $this->class_name_from_dir($d);
                    if (class_exists($class)) {
                        try {
                            $instance = new $class();
                            $this->modules[$d] = $instance;
                        } catch (Exception $e) {
                            error_log('W2P Module instantiation failed: ' . $e->getMessage());
                        }
                    }
                }
            }
        }
    }

    protected function class_name_from_dir($dir) {
        $parts = preg_split('/[-_]/', $dir);
        $parts = array_map('ucfirst', $parts);
        return implode('', $parts) . 'Module';
    }

    // 初始化已启用的模块
    public function init() {
        $this->discover();
        $enabled = get_option('word2posts_modules', array());
        foreach ($this->modules as $id => $module) {
            $is_enabled = isset($enabled[$id]) ? (bool) $enabled[$id] : false;
            if ($is_enabled && method_exists($module, 'init')) {
                try {
                    $module->init();
                } catch (Exception $e) {
                    error_log('W2P Module init error (' . $id . '): ' . $e->getMessage());
                }
            }
        }
    }

    public function get_available_modules() {
        return $this->modules;
    }

    public function is_enabled($id) {
        $enabled = get_option('word2posts_modules', array());
        return !empty($enabled[$id]);
    }

    public function set_enabled($id, $state) {
        $enabled = get_option('word2posts_modules', array());
        $old_state = isset($enabled[$id]) ? (bool) $enabled[$id] : false;
        $new_state = (bool) $state;
        
        if ($old_state !== $new_state) {
            $enabled[$id] = $new_state;
            update_option('word2posts_modules', $enabled);
            
            // 调用模块的 enable 或 disable 方法
            if (isset($this->modules[$id])) {
                if ($new_state && method_exists($this->modules[$id], 'enable')) {
                    $this->modules[$id]->enable();
                } elseif (!$new_state && method_exists($this->modules[$id], 'disable')) {
                    $this->modules[$id]->disable();
                }
            }
        }
    }
}

?>
