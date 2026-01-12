<?php
/**
 * Module Name: 微信助手
 * Description: 提供微信公众号文章分享、数据同步等功能。
 * Version: 1.0.0
 * Author: WPGenius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WechatAssistantModule {

	private static $instance = null;
	public $api;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		$this->init_constants();
	}

	public function name() {
		return __( 'WeChat Assistant', 'wp-genius' );
	}

	public function description() {
		return __( 'Configure WeChat Official Account settings for post sharing and synchronization.', 'wp-genius' );
	}

	public function icon() {
		return 'fa-brands fa-weixin';
	}

	public function init() {
		$this->includes();
		$this->init_hooks();

		$appid     = get_option( 'w2p_wechat_appid' );
		$appsecret = get_option( 'w2p_wechat_secret' );

		if ( $appid && $appsecret ) {
			$this->api = new WPGenius_Wechat_Api( $appid, $appsecret );
		}
	}

	private function init_constants() {
		if ( ! defined( 'W2P_WECHAT_PATH' ) ) {
			define( 'W2P_WECHAT_PATH', plugin_dir_path( __FILE__ ) );
		}
		if ( ! defined( 'W2P_WECHAT_URL' ) ) {
			define( 'W2P_WECHAT_URL', plugin_dir_url( __FILE__ ) );
		}
	}

	private function includes() {
		// New flat structure path
		require_once W2P_WECHAT_PATH . 'class-wechat-api.php';
	}

	private function init_hooks() {
		// JSSDK
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );

		// Sync
		add_action( 'wp_ajax_w2p_wechat_push_draft', array( $this, 'ajax_push_draft' ) );
		add_action( 'post_submitbox_misc_actions', array( $this, 'add_sync_button' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	public function register_settings_tab( $tabs ) {
		$tabs['wechat_assistant'] = __( 'WeChat Assistant', 'wp-genius' );
		return $tabs;
	}

	public function render_settings() {
		include W2P_WECHAT_PATH . 'settings.php';
	}

	public function register_rest_routes() {
		register_rest_route( 'wechat', '/sign', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_sign' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function rest_sign( $request ) {
		$url = $request->get_param( 'url' );
		if ( ! $this->api ) {
			return new WP_Error( 'no_key', __( 'Please configure AppID and Secret first', 'wp-genius' ), array( 'status' => 400 ) );
		}
		return $this->api->sign_package( $url );
	}

	public function enqueue_frontend_scripts() {
		if ( ! is_singular() ) {
			return;
		}
		
		// Optional: Check setting to enable share script
		if ( 'yes' !== get_option( 'w2p_wechat_enable_share', 'yes' ) ) {
			return;
		}

		// Only enqueue if we have API credentials
		if ( ! $this->api ) {
			return;
		}

		wp_enqueue_script( 'w2p-wechat-share', 'https://res.wx.qq.com/open/js/jweixin-1.6.0.js', array(), '1.6.0', true );
		
		$assets_url = plugin_dir_url( WP_GENIUS_FILE ) . 'assets/';
		wp_enqueue_script( 'w2p-wechat-share-init', $assets_url . 'js/modules/wechat-share.js', array( 'jquery', 'w2p-wechat-share' ), '1.0.0', true );
		
		wp_localize_script( 'w2p-wechat-share-init', 'wxs', array(
			'ajax' => rest_url( 'wechat/sign' ),
		) );
	}
	
	public function enqueue_admin_scripts( $hook ) {
		global $post;
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		
		// Use global assets URL
		// W2P_ASSETS_URL is likely defined in main plugin file. I should use plugin_dir_url or ensure W2P_ASSETS_URL is available.
		// Checking other modules: they likely use `plugin_dir_url( dirname( dirname( dirname( __FILE__ ) ) ) ) . 'assets/...'`
		// Or if `W2P_PLUGIN_URL` exists.
		
		// Safest way:
		$assets_url = plugin_dir_url( WP_GENIUS_FILE ) . 'assets/';
		
		wp_enqueue_script( 'w2p-wechat-admin', $assets_url . 'js/modules/wechat-assistant.js', array( 'jquery' ), '1.0.0', true );
		wp_localize_script( 'w2p-wechat-admin', 'w2p_wechat_admin', array(
			'nonce' => wp_create_nonce( 'w2p_wechat_push' ),
			'i18n'  => array(
				'pushing'  => __( 'Pushing...', 'wp-genius' ),
				'push_btn' => __( 'Push to WeChat Draft', 'wp-genius' ),
				'error'    => __( 'Network error, please try again', 'wp-genius' ),
			),
		) );
	}

	public function add_sync_button( $post ) {
		if ( 'post' !== $post->post_type ) {
			return;
		}
		?>
		<div class="misc-pub-section misc-pub-wechat-sync">
			<span class="dashicons dashicons-share-alt"></span>
			<a href="#" id="w2p-wechat-sync-btn" class="button button-small"><?php _e( 'Push to WeChat Draft', 'wp-genius' ); ?></a>
			<div id="w2p-wechat-sync-status" style="margin-top: 5px; color: #666;"></div>
		</div>
		<?php
	}

	public function ajax_push_draft() {
		check_ajax_referer( 'w2p_wechat_push', 'nonce' );
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied', 'wp-genius' ) );
		}

		$post_id = intval( $_POST['post_id'] );
		if ( ! $post_id ) {
			wp_send_json_error( __( 'Invalid Post ID', 'wp-genius' ) );
		}

		if ( ! $this->api ) {
			wp_send_json_error( __( 'AppID or Secret not configured', 'wp-genius' ) );
		}

		$result = $this->api->push_to_draft( $post_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		} else {
			wp_send_json_success( sprintf( __( 'Draft pushed, Media ID: %s', 'wp-genius' ), $result ) );
		}
	}
}
