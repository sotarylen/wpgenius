<?php
// exit if accessed directly
if (!defined('ABSPATH'))
    exit;

new W2P_Image_Watermark_Update();

/**
 * Image Watermark update class.
 *
 * @class W2P_Image_Watermark_Update
 */
class W2P_Image_Watermark_Update {

    /**
     * Class constructor.
     */
    public function __construct() {
        // actions
        add_action('admin_init', [$this, 'check_update']);
    }

    /**
     * Check if update is required.
     */
    public function check_update() {
        if (!current_user_can('manage_options') || !current_user_can('install_plugins'))
            return;

        // gets current database version
        $current_db_version = get_option('w2p_image_watermark_version', '1.0.0');

        // new version?
        if (version_compare($current_db_version, W2P_Image_Watermark::instance()->defaults['version'], '<')) {
            // update plugin version
            update_option('w2p_image_watermark_version', W2P_Image_Watermark::instance()->defaults['version'], false);
        }
    }
}