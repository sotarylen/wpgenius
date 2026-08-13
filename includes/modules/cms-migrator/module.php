<?php
/**
 * CMS Migrator Module
 *
 * 将外部CMS数据迁移到WordPress
 *
 * @package WP_Genius
 * @subpackage Modules/CMSMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CMS Migrator Module Class
 */
class W2P_CmsMigratorModule extends W2P_Abstract_Module {

	/**
	 * Module ID
	 *
	 * @return string
	 */
	public static function id() {
		return 'cms-migrator';
	}

	/**
	 * Module Name
	 *
	 * @return string
	 */
	public static function name() {
		return __( 'CMS Migrator', 'wp-genius' );
	}

	/**
	 * Module Description
	 *
	 * @return string
	 */
	public static function description() {
		return __( 'Migrate content from external CMS to WordPress custom post types.', 'wp-genius' );
	}

	/**
	 * Module Icon
	 *
	 * @return string
	 */
	public static function icon() {
		return 'fa-solid fa-database';
	}

	/**
	 * DB Connector Instance
	 *
	 * @var CMS_DB_Connector|null
	 */
	private $db_connector = null;

	/**
	 * Migrator Instance
	 *
	 * @var CMS_Migrator|null
	 */
	private $migrator = null;

	/**
	 * Media Importer Instance
	 *
	 * @var CMS_Media_Importer|null
	 */
	private $media_importer = null;

	/**
	 * Migration Logger Instance
	 *
	 * @var CMS_Migration_Logger|null
	 */
	private $logger = null;

	/**
	 * Initialize Module
	 *
	 * @return void
	 */
	public function init() {
		$this->load_dependencies();
		$this->load_options();
		$this->register_hooks();
	}

	/**
	 * Load Dependencies
	 *
	 * @return void
	 */
	private function load_dependencies() {
		$classes_dir = __DIR__ . '/classes/';

		require_once $classes_dir . 'class-db-connector.php';
		require_once $classes_dir . 'class-migrator.php';
		require_once $classes_dir . 'class-media-importer.php';
		require_once $classes_dir . 'class-migration-logger.php';
	}

	/**
	 * Load CSF Options
	 *
	 * @return void
	 */
	private function load_options() {
		$options_path = __DIR__ . '/options.php';
		if ( file_exists( $options_path ) && class_exists( 'CSF' ) ) {
			require_once $options_path;
		}
	}

	/**
	 * Register Hooks
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers
		add_action( 'wp_ajax_w2p_cms_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_w2p_cms_get_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_w2p_cms_start_migration', array( $this, 'ajax_start_migration' ) );
		add_action( 'wp_ajax_w2p_cms_get_progress', array( $this, 'ajax_get_progress' ) );
		add_action( 'wp_ajax_w2p_cms_stop_migration', array( $this, 'ajax_stop_migration' ) );
		add_action( 'wp_ajax_w2p_cms_rollback', array( $this, 'ajax_rollback' ) );
		add_action( 'wp_ajax_w2p_cms_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_w2p_cms_preview_data', array( $this, 'ajax_preview_data' ) );
	}

	/**
	 * Get DB Connector instance
	 *
	 * @return CMS_DB_Connector
	 */
	public function get_db_connector() {
		if ( null === $this->db_connector ) {
			$this->db_connector = new CMS_DB_Connector();
		}
		return $this->db_connector;
	}

	/**
	 * Get Migrator instance
	 *
	 * @return CMS_Migrator
	 */
	public function get_migrator() {
		if ( null === $this->migrator ) {
			$this->migrator = new CMS_Migrator( $this->get_db_connector(), $this->get_media_importer(), $this->get_logger() );
		}
		return $this->migrator;
	}

	/**
	 * Get Media Importer instance
	 *
	 * @return CMS_Media_Importer
	 */
	public function get_media_importer() {
		if ( null === $this->media_importer ) {
			$this->media_importer = new CMS_Media_Importer();
		}
		return $this->media_importer;
	}

	/**
	 * Get Logger instance
	 *
	 * @return CMS_Migration_Logger
	 */
	public function get_logger() {
		if ( null === $this->logger ) {
			$this->logger = new CMS_Migration_Logger();
		}
		return $this->logger;
	}

	/**
	 * Enqueue Admin Assets
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'wp-genius-settings' ) === false ) {
			return;
		}

		$plugin_url = plugin_dir_url( WP_GENIUS_FILE );

		wp_enqueue_style(
			'w2p-cms-migrator',
			$plugin_url . 'includes/modules/cms-migrator/assets/css/cms-migrator.css',
			array(),
			'1.2.0'
		);

		wp_enqueue_script(
			'w2p-cms-migrator',
			$plugin_url . 'includes/modules/cms-migrator/assets/js/cms-migrator.js',
			array( 'jquery', 'wp-util' ),
			'1.2.0',
			true
		);

		wp_localize_script(
			'w2p-cms-migrator',
			'w2pCMSMigrator',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'w2p_cms_migrator_nonce' ),
			)
		);
	}

	/**
	 * AJAX: Test Database Connection
	 *
	 * @return void
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'w2p_cms_migrator_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-genius' ) ) );
		}

		$settings = get_option( 'w2p_cms_migrator_settings', array() );

		$host = sanitize_text_field( wp_unslash( $_POST['db_host'] ?? $settings['db_host'] ?? '' ) );
		$port = sanitize_text_field( wp_unslash( $_POST['db_port'] ?? $settings['db_port'] ?? '3306' ) );
		$name = sanitize_text_field( wp_unslash( $_POST['db_name'] ?? $settings['db_name'] ?? '' ) );
		$user = sanitize_text_field( wp_unslash( $_POST['db_user'] ?? $settings['db_user'] ?? '' ) );
		$pass = $this->get_db_pass( $_POST['db_pass'] ?? null );

		$connector = new CMS_DB_Connector();
		$result    = $connector->test_connection( $host, $port, $name, $user, $pass );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Connection successful!', 'wp-genius' ) ) );
	}

	/**
	 * AJAX: Get Database Statistics
	 *
	 * @return void
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'w2p_cms_migrator_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-genius' ) ) );
		}

		$settings  = get_option( 'w2p_cms_migrator_settings', array() );
		$connector = $this->get_db_connector();

		$result = $connector->test_connection(
			sanitize_text_field( wp_unslash( $_POST['db_host'] ?? $settings['db_host'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['db_port'] ?? $settings['db_port'] ?? '3306' ) ),
			sanitize_text_field( wp_unslash( $_POST['db_name'] ?? $settings['db_name'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['db_user'] ?? $settings['db_user'] ?? '' ) ),
			$this->get_db_pass( $_POST['db_pass'] ?? null )
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$stats = $connector->get_table_stats();

		if ( is_wp_error( $stats ) ) {
			wp_send_json_error( array( 'message' => $stats->get_error_message() ) );
		}

		wp_send_json_success( array( 'stats' => $stats ) );
	}

	/**
	 * AJAX: Start Migration
	 *
	 * @return void
	 */
	public function ajax_start_migration() {
		check_ajax_referer( 'w2p_cms_migrator_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-genius' ) ) );
		}

		$types       = isset( $_POST['types'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['types'] ) ) : array();
		$mappings    = isset( $_POST['mappings'] ) ? map_deep( wp_unslash( $_POST['mappings'] ), 'sanitize_text_field' ) : array();
		$batch_limit = isset( $_POST['batch_limit'] ) ? absint( $_POST['batch_limit'] ) : 0;

		if ( empty( $types ) ) {
			wp_send_json_error( array( 'message' => __( 'No content types selected.', 'wp-genius' ) ) );
		}

		$settings  = get_option( 'w2p_cms_migrator_settings', array() );
		$connector = $this->get_db_connector();

		$result = $connector->test_connection(
			sanitize_text_field( wp_unslash( $settings['db_host'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $settings['db_port'] ?? '3306' ) ),
			sanitize_text_field( wp_unslash( $settings['db_name'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $settings['db_user'] ?? '' ) ),
			$this->get_db_pass()
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$migrator = $this->get_migrator();
		$result   = $migrator->start_migration( $types, $mappings, $batch_limit );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Migration started.', 'wp-genius' ) ) );
	}

	/**
	 * AJAX: Get Migration Progress
	 *
	 * @return void
	 */
	public function ajax_get_progress() {
		check_ajax_referer( 'w2p_cms_migrator_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-genius' ) ) );
		}

		$progress = get_option( 'w2p_cms_migration_progress', array() );
		wp_send_json_success( array( 'progress' => $progress ) );
	}

	/**
	 * AJAX: Stop Migration
	 *
	 * @return void
	 */
	public function ajax_stop_migration() {
		check_ajax_referer( 'w2p_cms_migrator_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-genius' ) ) );
		}

		$migrator = $this->get_migrator();
		$result   = $migrator->stop_migration();

		wp_send_json_success( array( 'message' => __( 'Migration stopped.', 'wp-genius' ) ) );
	}

	/**
	 * AJAX: Rollback Migration
	 *
	 * @return void
	 */
	public function ajax_rollback() {
		check_ajax_referer( 'w2p_cms_migrator_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-genius' ) ) );
		}

		$migrator = $this->get_migrator();
		$result   = $migrator->rollback();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Rollback completed.', 'wp-genius' ) ) );
	}

	/**
	 * AJAX: Save Settings
	 *
	 * @return void
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'w2p_cms_migrator_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-genius' ) ) );
		}

		$settings = get_option( 'w2p_cms_migrator_settings', array() );

		$settings['db_host'] = sanitize_text_field( wp_unslash( $_POST['db_host'] ?? '' ) );
		$settings['db_port'] = sanitize_text_field( wp_unslash( $_POST['db_port'] ?? '3306' ) );
		$settings['db_name'] = sanitize_text_field( wp_unslash( $_POST['db_name'] ?? '' ) );
		$settings['db_user'] = sanitize_text_field( wp_unslash( $_POST['db_user'] ?? '' ) );

		// Only update the password when a new one is provided (keeps it out of the DOM).
		if ( ! empty( $_POST['db_pass'] ) ) {
			$settings['db_pass'] = W2P_Crypto::encrypt( sanitize_text_field( wp_unslash( $_POST['db_pass'] ) ) );
		}

		update_option( 'w2p_cms_migrator_settings', $settings );
		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'wp-genius' ) ) );
	}

	/**
	 * AJAX: Preview Data
	 *
	 * @return void
	 */
	public function ajax_preview_data() {
		check_ajax_referer( 'w2p_cms_migrator_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-genius' ) ) );
		}

		$type  = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'books';
		$limit = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 10;

		$settings  = get_option( 'w2p_cms_migrator_settings', array() );
		$connector = $this->get_db_connector();

		$result = $connector->test_connection(
			sanitize_text_field( wp_unslash( $_POST['db_host'] ?? $settings['db_host'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['db_port'] ?? $settings['db_port'] ?? '3306' ) ),
			sanitize_text_field( wp_unslash( $_POST['db_name'] ?? $settings['db_name'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['db_user'] ?? $settings['db_user'] ?? '' ) ),
			$this->get_db_pass( $_POST['db_pass'] ?? null )
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$data = $connector->preview_data( $type, $limit );

		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ) );
		}

		wp_send_json_success( array( 'data' => $data ) );
	}

	/**
	 * Resolve the database password from a posted value or stored (encrypted) settings.
	 *
	 * Legacy plaintext values stored in options are re-encrypted lazily on first read.
	 *
	 * @param string|null $posted Raw posted password value (may be null/empty).
	 * @return string
	 */
	private function get_db_pass( $posted = null ) {
		if ( null !== $posted && '' !== (string) $posted ) {
			return sanitize_text_field( wp_unslash( $posted ) );
		}

		$settings = get_option( 'w2p_cms_migrator_settings', array() );
		$stored   = isset( $settings['db_pass'] ) ? $settings['db_pass'] : '';

		if ( '' === $stored ) {
			return '';
		}

		$pass = W2P_Crypto::decrypt( $stored );

		if ( null === $pass ) {
			// Legacy plaintext value: re-encrypt on read (lazy migration).
			$pass                = $stored;
			$settings['db_pass'] = W2P_Crypto::encrypt( $stored );
			update_option( 'w2p_cms_migrator_settings', $settings );
		}

		return is_string( $pass ) ? $pass : '';
	}
}

// Legacy alias for backward compatibility (pre-2.0.0 class name).
if ( ! class_exists( 'CmsMigratorModule', false ) ) {
	class_alias( 'W2P_CmsMigratorModule', 'CmsMigratorModule' );
}
