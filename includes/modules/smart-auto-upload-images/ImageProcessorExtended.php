<?php
/**
 * Enhanced Image Processor Wrapper with Better Error Handling
 * 
 * Fixes:
 * 1. Better URL replacement with encoding handling
 * 2. Proper status for existing images
 * 3. Timeout and memory management
 * 4. Image attachment association (Post ID fix)
 * 5. Robust retry mechanism
 */

namespace SmartAutoUploadImages\Services;

use SmartAutoUploadImages\Utils\Logger;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enhanced Image Processor Class
 */
class ImageProcessorExtended {
	
	/**
	 * Original ImageProcessor instance
	 *
	 * @var ImageProcessor
	 */
	private $processor;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->processor = new ImageProcessor();
		
		// 增加PHP执行时间和内存限制
		@ini_set( 'max_execution_time', '300' ); // 5分钟
		@ini_set( 'memory_limit', '512M' );
	}
	
	/**
	 * Process post content for images with progress tracking
	 *
	 * @param string $content Post content.
	 * @param array  $post_data Post data.
	 * @return string|false Processed content or false on no changes.
	 */
	public function process_post_content( string $content, array $post_data ) {
		// [FIX 1] 确保 Post ID 存在，以解决附件未关联的问题
		if ( empty( $post_data['ID'] ) ) {
			if ( isset( $_POST['post_ID'] ) ) {
				$post_data['ID'] = intval( $_POST['post_ID'] );
			} elseif ( isset( $GLOBALS['post']->ID ) ) {
				$post_data['ID'] = $GLOBALS['post']->ID;
			}
		}

		// 使用反射来访问私有方法
		$reflection = new \ReflectionClass( $this->processor );
		$find_images_method = $reflection->getMethod( 'find_images_in_content' );
		$find_images_method->setAccessible( true );
		
		$images = $find_images_method->invoke( $this->processor, $content );

		if ( empty( $images ) ) {
			return false;
		}

		// Fire action before processing
		do_action( 'smart_aui_before_process_images', $images, $post_data );

		// 获取私有属性
		$downloader_property = $reflection->getProperty( 'downloader' );
		$downloader_property->setAccessible( true );
		$downloader = $downloader_property->getValue( $this->processor );
		
		$logger_property = $reflection->getProperty( 'logger' );
		$logger_property->setAccessible( true );
		$logger = $logger_property->getValue( $this->processor );

		$processed_content = $content;
		$processed_count   = 0;
		$success_count     = 0;
		$failed_count      = 0;
		$max_retries       = 3; // 最大重试次数
		
		// 获取本地域名配置
		$smart_aui_settings = \SmartAutoUploadImages\Plugin::get_settings();
		$base_url = !empty($smart_aui_settings['base_url']) ? $smart_aui_settings['base_url'] : site_url();
		$site_domain = parse_url($base_url, PHP_URL_HOST);
		$site_url = site_url();

		foreach ( $images as $index => $image ) {
			// [FIX 3] 检查是否已经是本地图片，如果是则跳过
			if ( strpos( $image['url'], $base_url ) === 0 || strpos( $image['url'], $site_url ) === 0 ) {
				$logger->info( 'Skipped local image', [ 'url' => $image['url'] ] );
				// 标记为成功（跳过），触发事件以便进度条更新
				do_action( 'smart_aui_image_processed', $image, [ 'skipped' => true ], $index );
				$success_count++; // 计入成功
				$processed_count++; // 计入处理总数
				continue;
			}
			
			// 检查域名是否匹配
			$image_host = parse_url($image['url'], PHP_URL_HOST);
			if ( $image_host === $site_domain ) {
				$logger->info( 'Skipped image with local domain', [ 'url' => $image['url'] ] );
				do_action( 'smart_aui_image_processed', $image, [ 'skipped' => true ], $index );
				$success_count++;
				$processed_count++;
				continue;
			}
			
			// 每处理5张图片，刷新一次输出缓冲，防止超时
			if ( $index % 5 === 0 ) {
				if ( function_exists( 'wp_ob_end_flush_all' ) ) {
					wp_ob_end_flush_all();
				}
				flush();
			}
			
			// [FIX 2] 内置重试机制
			$retry_count = 0;
			$result = null;
			
			while ( $retry_count < $max_retries ) {
				$result = $downloader->download_image( $image, $post_data );
				
				if ( ! is_wp_error( $result ) ) {
					// 成功，跳出重试循环
					break;
				}
				
				// 失败，增加重试计数并等待
				$retry_count++;
				if ( $retry_count < $max_retries ) {
					// 简单的指数退避：1s, 2s
					sleep( $retry_count );
				}
			}

			if ( is_wp_error( $result ) ) {
				$logger->error(
					'Failed to process image after retries',
					[
						'url'     => $image['url'],
						'error'   => $result->get_error_message(),
						'retries' => $retry_count,
					]
				);
				
				$failed_count++;
				
				// Fire action for failed image
				do_action( 'smart_aui_image_processed', $image, $result, $index );
				continue;
			}

			// 使用改进的URL替换方法
			$processed_content = $this->replace_image_url_enhanced( $processed_content, $image, $result );
			++$processed_count;
			$success_count++;
			
			// Fire action for successful image (包括已存在的图片)
			do_action( 'smart_aui_image_processed', $image, $result, $index );
		}

		// Fire action after processing
		do_action( 'smart_aui_after_process_images', $processed_count, $post_data );

		if ( $processed_count > 0 ) {
			$logger->info(
				'Processed images for post',
				[
					'post_id'         => $post_data['ID'] ?? 0,
					'processed_count' => $processed_count,
					'success_count'   => $success_count,
					'failed_count'    => $failed_count,
				]
			);
			return $processed_content;
		}

		return false;
	}
	
	/**
	 * Enhanced URL replacement with better encoding handling
	 *
	 * @param string $content Content to modify.
	 * @param array  $image Original image data.
	 * @param array  $result Download result.
	 * @return string Modified content.
	 */
	private function replace_image_url_enhanced( string $content, array $image, array $result ): string {
		$settings = \SmartAutoUploadImages\Plugin::get_settings();
		$base_url = trim( $settings['base_url'], '/' );

		$new_url_parts = wp_parse_url( $result['url'] );
		$new_url       = $base_url . $new_url_parts['path'];
		
		$old_url = $image['url'];

		// 尝试多种URL变体进行替换，处理编码问题
		$url_variants = [
			$old_url,
			html_entity_decode( $old_url ),
			urldecode( $old_url ),
			str_replace( '&amp;', '&', $old_url ),
			str_replace( '&', '&amp;', $old_url ),
		];
		
		// 去重
		$url_variants = array_unique( $url_variants );
		
		// 替换所有变体
		foreach ( $url_variants as $variant ) {
			if ( strpos( $content, $variant ) !== false ) {
				$content = str_replace( $variant, $new_url, $content );
			}
		}

		// 处理Alt文本
		if ( ! empty( $image['alt'] ) && ! empty( $result['alt_text'] ) ) {
			$old_alt_pattern = 'alt=["\']' . preg_quote( $image['alt'], '/' ) . '["\']';
			$new_alt         = $result['alt_text'];
			$new_alt_pattern = 'alt="' . esc_attr( $new_alt ) . '"';

			$content = preg_replace( '/' . $old_alt_pattern . '/i', $new_alt_pattern, $content );
		}

		return $content;
	}
}
