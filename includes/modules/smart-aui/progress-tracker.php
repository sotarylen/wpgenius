<?php
/**
 * Progress Tracker for Smart Auto Upload Images
 * 
 * Hooks into the image processing pipeline to track progress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2P_Smart_AUI_Progress_Tracker {
	
	private static $instance = null;
	private $current_user_id = 0;
	private $progress_data = [];
	
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		$this->current_user_id = get_current_user_id();
		$this->init_hooks();
	}
	
	private function init_hooks() {
		// Hook before image processing starts
		add_action( 'smart_aui_before_process_images', [ $this, 'start_tracking' ], 10, 2 );
		
		// Hook for each image processed
		add_action( 'smart_aui_image_processed', [ $this, 'update_progress' ], 10, 3 );
		
		// Hook after all images processed
		add_action( 'smart_aui_after_process_images', [ $this, 'complete_tracking' ], 10, 2 );
	}
	
	// Store process ID
	private $process_id = '';
	
	/**
	 * Set the current process ID
	 */
	public function set_process_id( $process_id ) {
		$this->process_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', $process_id );
	}
	
	public function start_tracking( $images, $post_data ) {
		$this->progress_data = [
			'status' => 'processing',
			'total' => count( $images ),
			'processed' => 0,
			'success' => 0,
			'failed' => 0,
			'current_url' => '',
			'start_time' => time(),
			'process_id' => $this->process_id
		];
		
		$this->save_progress();
	}
	
	public function update_progress( $image, $result, $index ) {
		// If we have a process_id set in this instance, we should check if the progress data we are updating belongs to it
		// But practically, the instance is singleton per request. 
		// If we are in the request that processes images, set_process_id should have been called before processing.
		
		$this->progress_data['processed']++;
		
		if ( is_wp_error( $result ) ) {
			$this->progress_data['failed']++;
		} else {
			$this->progress_data['success']++;
		}
		
		$this->progress_data['current_url'] = $image['url'] ?? '';
		
		$this->save_progress();
	}
	
	public function complete_tracking( $processed_count, $post_data ) {
		$this->progress_data['status'] = 'completed';
		$this->progress_data['end_time'] = time();
		
		$this->save_progress();
	}
	
	private function save_progress() {
		$key = 'w2p_smart_aui_progress_' . $this->current_user_id;
		if ( ! empty( $this->process_id ) ) {
			$key .= '_' . $this->process_id;
		}
		
		set_transient(
			$key,
			$this->progress_data,
			300 // 5 minutes
		);
	}
	
	public static function get_progress( $user_id = null, $process_id = '' ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}
		
		$key = 'w2p_smart_aui_progress_' . $user_id;
		if ( ! empty( $process_id ) ) {
			$key .= '_' . $process_id;
		}
		
		return get_transient( $key );
	}
	
	public static function clear_progress( $user_id = null, $process_id = '' ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}
		
		$key = 'w2p_smart_aui_progress_' . $user_id;
		if ( ! empty( $process_id ) ) {
			$key .= '_' . $process_id;
		}
		
		delete_transient( $key );
	}
}
	
// Initialize tracker
W2P_Smart_AUI_Progress_Tracker::get_instance();

/**
 * 获取当前用户的图片处理进度（公共函数，供其他模块调用）
 *
 * @param string   $process_id 进程ID，可选。
 * @param int|null $user_id    用户ID，默认当前用户。
 * @return array|false
 */
if ( ! function_exists( 'w2p_smart_aui_get_progress' ) ) {
	function w2p_smart_aui_get_progress( $process_id = '', $user_id = null ) {
		return W2P_Smart_AUI_Progress_Tracker::get_progress( $user_id, $process_id );
	}
}

/**
 * 清理指定进程的进度数据
 *
 * @param string   $process_id 进程ID，可选。
 * @param int|null $user_id    用户ID，默认当前用户。
 * @return void
 */
if ( ! function_exists( 'w2p_smart_aui_clear_progress' ) ) {
	function w2p_smart_aui_clear_progress( $process_id = '', $user_id = null ) {
		W2P_Smart_AUI_Progress_Tracker::clear_progress( $user_id, $process_id );
	}
}

/**
 * 判断指定进程是否仍在处理
 *
 * @param string   $process_id 进程ID，可选。
 * @param int|null $user_id    用户ID，默认当前用户。
 * @return bool
 */
if ( ! function_exists( 'w2p_smart_aui_is_processing' ) ) {
	function w2p_smart_aui_is_processing( $process_id = '', $user_id = null ) {
		$progress = W2P_Smart_AUI_Progress_Tracker::get_progress( $user_id, $process_id );
		return is_array( $progress ) && isset( $progress['status'] ) && 'processing' === $progress['status'];
	}
}
