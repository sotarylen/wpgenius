<?php
/**
 * Word to Post — Upload Handler
 *
 * DOCX upload, directory scanning, and cleanup.
 * Split out of class-word-to-posts.php (God class refactor).
 *
 * @package WP_Genius
 * @subpackage Modules/WordToPost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class W2P_WordToPost_UploadHandler
 */
class W2P_WordToPost_UploadHandler {

	/**
	 * DOCX importer (shared helper).
	 *
	 * @var W2P_WordToPost_DocxImporter
	 */
	private $importer;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->importer = new W2P_WordToPost_DocxImporter();
	}

	public function scanUploads() {
		if ( ! isset( $_POST['word_to_posts_scan_nonce'] ) || ! wp_verify_nonce( $_POST['word_to_posts_scan_nonce'], 'word_to_posts_scan' ) ) {
			wp_send_json_error( __( 'Nonce verification failed', 'wp-genius' ) );
			return;
		}

		$upload_dir = wp_upload_dir();
		$target_dir = $upload_dir['basedir'] . '/word2post';
		$log        = array();

		if ( is_dir( $target_dir ) ) {
			$files = glob( $target_dir . '/*' );
			if ( count( $files ) > 0 ) {
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						$log[] = basename( $file );
					}
				}
			} else {
				$log[] = __( 'No files found in the uploads folder.', 'wp-genius' );
			}
		} else {
			$log[] = __( 'Uploads folder not found.', 'wp-genius' );
		}

		wp_send_json_success( $log );
	}
	public function cleanUploads() {
		if ( ! isset( $_POST['word_to_posts_clean_nonce'] ) || ! wp_verify_nonce( $_POST['word_to_posts_clean_nonce'], 'word_to_posts_clean' ) ) {
			wp_send_json_error( __( 'Nonce verification failed', 'wp-genius' ) );
			return;
		}

		$upload_dir = wp_upload_dir();
		$target_dir = $upload_dir['basedir'] . '/word2post';
		$log        = array();

		if ( is_dir( $target_dir ) ) {
			$files = glob( $target_dir . '/*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					if ( unlink( $file ) ) {
						$log[] = basename( $file ) . ' ' . __( 'file deleted successfully', 'wp-genius' );
					} else {
						$log[] = basename( $file ) . ' ' . __( 'file deletion failed', 'wp-genius' );
					}
				}
			}
			$log[] = __( 'All files cleaned successfully.', 'wp-genius' );
		} else {
			$log[] = __( 'Uploads folder not found.', 'wp-genius' );
		}

		wp_send_json_success( $log );
	}
	public function showAdminNotices() {
		if ( get_current_screen()->id !== 'tools_page_wp-genius' ) {
			return;
		}
	}
	public function handleFileUpload() {
		if ( ! isset( $_POST['word_to_posts_upload_nonce'] ) || ! wp_verify_nonce( $_POST['word_to_posts_upload_nonce'], 'word_to_posts_upload' ) ) {
			wp_send_json_error( __( 'Nonce verification failed', 'wp-genius' ) );
			return;
		}

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! isset( $_FILES['word_file'] ) || empty( $_FILES['word_file']['name'] ) ) {
			wp_send_json_error( __( 'No file was uploaded.', 'wp-genius' ) );
			return;
		}

		$uploadedfile     = $_FILES['word_file'];
		$upload_overrides = array( 'test_form' => false );
		$movefile         = wp_handle_upload( $uploadedfile, $upload_overrides );

		if ( $movefile && ! isset( $movefile['error'] ) ) {
			$upload_dir = wp_upload_dir();
			$target_dir = $upload_dir['basedir'] . '/word2post';
			if ( ! file_exists( $target_dir ) ) {
				mkdir( $target_dir, 0755, true );
			}
			$target_file = $target_dir . '/' . basename( $movefile['file'] );
			if ( rename( $movefile['file'], $target_file ) ) {
				$tags = isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '';
				$this->importer->importAndPublish( $target_file, $tags );
			} else {
				wp_send_json_error( __( 'File move failed', 'wp-genius' ) );
			}
		} else {
			$error_msg = isset( $movefile['error'] ) ? $movefile['error'] : __( 'File upload failed', 'wp-genius' );
			wp_send_json_error( $error_msg );
		}
	}
}
