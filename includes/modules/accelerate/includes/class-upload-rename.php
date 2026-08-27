<?php
/**
 * WP Genius Accelerate — Upload Rename
 *
 * Split from module.php (refactored from the God class).
 *
 * @package WP_Genius
 * @subpackage Modules/Accelerate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class W2P_Accelerate_UploadRename
 */
class W2P_Accelerate_UploadRename {

	/**
	 * Parent module instance.
	 *
	 * @var W2P_AccelerateModule
	 */
	private $module;

	/**
	 * Sanitized-name → original-name map, shared across upload filters in
	 * the same request. PHP-FPM worker process-local static; residual data
	 * may persist between requests until the worker exits.
	 *
	 * @var array<string, string>
	 */
	private static $original_titles = array();

	/**
	 * Constructor.
	 *
	 * @param W2P_AccelerateModule $module Parent module.
	 */
	public function __construct( $module ) {
		$this->module = $module;
	}

	/**
	 * ============================================
	 * Upload Rename Integration
	 * ============================================
	 */
	public function init_upload_rename() {
		$settings = $this->module->get_settings();
		if ( empty( $settings['accelerate_enable_upload_rename'] ) ) {
			return;
		}

		add_filter( 'wp_handle_upload_prefilter', array( $this, 'handle_upload_prefilter' ) );
		add_filter( 'wp_insert_attachment_data', array( $this, 'maybe_replace_attachment_title' ), 10, 2 );
	}
	public function handle_upload_prefilter( $file ) {
		if ( empty( $file['name'] ) ) {
			return $file;
		}

		$settings = $this->module->get_settings();
		$pattern  = isset( $settings['accelerate_upload_rename_pattern'] ) ? $settings['accelerate_upload_rename_pattern'] : '{timestamp}_{sanitized}';

		$pattern_template = $pattern;
		$pattern          = preg_replace_callback(
			'/\{date(?::([^}]+))?\}/',
			function ( $m ) {
				$fmt = isset( $m[1] ) && $m[1] ? $m[1] : 'Y-m-d';
				return date( $fmt );
			},
			$pattern
		);

		$original_base = pathinfo( $file['name'], PATHINFO_FILENAME );
		$original_base = sanitize_file_name( $original_base );
		$sanitized     = $original_base;
		$ext           = pathinfo( $file['name'], PATHINFO_EXTENSION );
		$timestamp     = time();
		$random        = wp_rand( 1000, 9999 );

		$current_user = wp_get_current_user();
		$user_login   = ! empty( $current_user->user_login ) ? $current_user->user_login : '';
		$user_id      = ! empty( $current_user->ID ) ? $current_user->ID : 0;

		$replacements = array(
			'{timestamp}'  => $timestamp,
			'{sanitized}'  => $sanitized,
			'{rand}'       => $random,
			'{datetime}'   => date( 'YmdHis' ),
			'{year}'       => date( 'Y' ),
			'{month}'      => date( 'm' ),
			'{day}'        => date( 'd' ),
			'{hour}'       => date( 'H' ),
			'{minute}'     => date( 'i' ),
			'{second}'     => date( 's' ),
			'{user_id}'    => $user_id,
			'{user_login}' => $user_login,
			'{orig}'       => $original_base,
			'{ext}'        => $ext,
			'{uniqid}'     => uniqid(),
		);

		$new_name = strtr( $pattern, $replacements );

		if ( false === strpos( $pattern_template, '{ext}' ) ) {
			$new_name = $new_name . ( $ext ? '.' . $ext : '' );
		}

		$new_sanitized = sanitize_file_name( $new_name );
		$file['name']  = $new_sanitized;

		$new_base                           = pathinfo( $new_sanitized, PATHINFO_FILENAME );
		self::$original_titles[ $new_base ] = $original_base;

		return $file;
	}
	public function maybe_replace_attachment_title( $data, $postarr ) {
		if ( empty( $data['post_title'] ) ) {
			return $data;
		}

		$current_base = sanitize_file_name( $data['post_title'] );
		if ( isset( self::$original_titles[ $current_base ] ) ) {
			$data['post_title'] = self::$original_titles[ $current_base ];
		}

		return $data;
	}

	/**
	 * Supported rename tokens and their meaning.
	 *
	 * Single source of truth for the replacement tokens, shared by
	 * handle_upload_prefilter() (which computes the runtime values) and the
	 * settings UI hint in options.php (which reads this list to tell users
	 * what they can type). Keep the keys in sync with the $replacements map
	 * in handle_upload_prefilter().
	 *
	 * @return array<string,string> token => human-readable meaning.
	 */
	public static function get_token_descriptions() {
		return array(
			'{timestamp}'    => __( 'Unix timestamp in seconds', 'wp-genius' ),
			'{sanitized}'    => __( 'Original filename, sanitized', 'wp-genius' ),
			'{rand}'         => __( '4-digit random number', 'wp-genius' ),
			'{datetime}'     => __( 'Date and time (YmdHis)', 'wp-genius' ),
			'{date:Y-m-d}'   => __( 'Date in any PHP date() format, e.g. {date:Y-m-d}', 'wp-genius' ),
			'{year}'         => __( '4-digit year', 'wp-genius' ),
			'{month}'        => __( '2-digit month', 'wp-genius' ),
			'{day}'          => __( '2-digit day', 'wp-genius' ),
			'{hour}'         => __( '2-digit hour', 'wp-genius' ),
			'{minute}'       => __( '2-digit minute', 'wp-genius' ),
			'{second}'       => __( '2-digit second', 'wp-genius' ),
			'{user_id}'      => __( 'Current user ID', 'wp-genius' ),
			'{user_login}'   => __( 'Current user login', 'wp-genius' ),
			'{orig}'         => __( 'Original base filename', 'wp-genius' ),
			'{ext}'          => __( 'File extension (no dot)', 'wp-genius' ),
			'{uniqid}'       => __( 'Unique ID', 'wp-genius' ),
		);
	}
}
