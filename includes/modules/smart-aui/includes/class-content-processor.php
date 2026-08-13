<?php
/**
 * Smart AUI — Content Processor
 *
 * 文章内容相关的处理：自动设置特色图片、从 URL 解析本地附件 ID。
 * 从 module.php 拆分（原 God class 重构）。
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class W2P_SmartAUI_Content_Processor
 */
class W2P_SmartAUI_Content_Processor {

	/**
	 * Parent module instance.
	 *
	 * @var W2P_SmartAUIModule
	 */
	private $module;

	/**
	 * Constructor.
	 *
	 * @param W2P_SmartAUIModule $module Parent module.
	 */
	public function __construct( $module ) {
		$this->module = $module;
	}

	/**
	 * Auto Set Featured Image
	 *
	 * 从文章内容中解析第一张本地图片并设置为特色图片。
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post|null $post   Post object.
	 * @return void
	 */
	public function auto_set_featured_image( $post_id, $post = null ) {
		// [OPTIMIZATION] Immediate Bypassing for Deletion Actions
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- save_post 钩子，WP 核心已验 nonce。
		if ( isset( $_REQUEST['action'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- 仅读取 action 判断删除操作。
			$action = $_REQUEST['action'];
			if ( in_array( $action, array( 'trash', 'delete', 'untrash' ), true ) ) {
				return;
			}
		}

		// Check if enabled (default true)
		$settings = $this->module->get_settings();
		$auto_set = isset( $settings['smart_aui_auto_set_featured_image'] ) ? $settings['smart_aui_auto_set_featured_image'] : true;

		if ( ! $auto_set ) {
			return;
		}

		// 如果未传入 post 对象，则获取
		if ( ! $post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post ) {
			return;
		}

		// 检查文章类型是否支持特色图片
		if ( ! post_type_supports( $post->post_type, 'thumbnail' ) ) {
			return;
		}

		// 检查 REST API 设置：如果是 REST API 请求且禁用了 REST API 支持，则跳过
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && empty( $settings['smart_aui_process_images_on_rest_api'] ) ) {
			return;
		}

		// 如果文章正在被移动到回收站，跳过处理
		if ( isset( $post->post_status ) && 'trash' === $post->post_status ) {
			return;
		}

		// 使用 WP_HTML_Tag_Processor 精准解析图片标签
		$processor            = new \WP_HTML_Tag_Processor( $post->post_content );
		$current_thumbnail_id = get_post_thumbnail_id( $post_id );
		$found_intended_id    = false;

		while ( $processor->next_tag( 'img' ) ) {
			$attachment_id = false;
			$src           = $processor->get_attribute( 'src' );

			// 1. 优先尝试从 class 属性提取 ID (wp-image-{id})
			$classes = $processor->get_attribute( 'class' ) ?? '';
			if ( preg_match( '/wp-image-(\d+)/', $classes, $class_matches ) ) {
				$attachment_id = intval( $class_matches[1] );
			}

			// 2. 如果没找到，尝试通过 URL 匹配附件
			if ( ! $attachment_id && $src ) {
				$attachment_id = $this->get_attachment_id_from_url( $src );
			}

			if ( $attachment_id ) {
				$found_intended_id = $attachment_id;
				break; // 找到第一张可用的本地图片后退出循环
			}
		}

		if ( $found_intended_id ) {
			// 如果找到的图片 ID 与当前特色图片 ID 不同，则更新
			if ( intval( $found_intended_id ) !== intval( $current_thumbnail_id ) ) {
				set_post_thumbnail( $post_id, $found_intended_id );
			}
		}
	}

	/**
	 * Get Attachment ID from URL
	 *
	 * 通过 URL 解析本地附件 ID（含 OSS/自定义 base_url、scaled/尺寸后缀处理）。
	 *
	 * @param string $image_url 图片 URL。
	 * @return int|false
	 */
	public function get_attachment_id_from_url( $image_url ) {
		// 获取本地域名（从 site_url 获取）
		$site_url     = site_url();
		$site_domain  = wp_parse_url( $site_url, PHP_URL_HOST );
		$image_domain = wp_parse_url( $image_url, PHP_URL_HOST );

		// 检查是否在同一域名下（支持 OSS 或其他自定义目录）
		// 如果没有域名（相对路径），也认为是本地的
		$is_local = false;
		if ( ! $image_domain || $image_domain === $site_domain ) {
			$is_local = true;
		} else {
			// 检查是否匹配配置的 base_url
			$settings    = $this->module->get_settings();
			$base_url    = ! empty( $settings['smart_aui_base_url'] ) ? $settings['smart_aui_base_url'] : $site_url;
			$base_domain = wp_parse_url( $base_url, PHP_URL_HOST );
			if ( $image_domain === $base_domain ) {
				$is_local = true;
			}
		}

		if ( ! $is_local ) {
			return false;
		}

		// 通过URL查找附件ID
		$attachment_id = attachment_url_to_postid( $image_url );

		if ( ! $attachment_id ) {
			// 尝试使用去掉协议的 URL 进行匹配
			$clean_url     = preg_replace( '/^https?:/i', '', $image_url );
			$attachment_id = attachment_url_to_postid( $clean_url );
		}

		if ( ! $attachment_id ) {
			// 尝试通过文件名查找（处理 scaled 或 resized 图片）
			global $wpdb;
			$filename = basename( $image_url );

			// 如果 pathinfo 可用，提取文件名
			$path_info = pathinfo( $filename );
			if ( ! empty( $path_info['filename'] ) ) {
				$base_name_only = preg_replace( '/(-\d+x\d+|-scaled)$/i', '', $path_info['filename'] );

				// 阿里云 OSS 可能会在文件名后加样式处理，如 !style
				$base_name_only = explode( '!', $base_name_only )[0];

				// [NEW] 尝试获取路径上下文 (YYYY/MM)
				$path_prefix = '';
				if ( preg_match( '/(\d{4}\/\d{2})\//', $image_url, $path_matches ) ) {
					$path_prefix = $path_matches[1] . '/';
				}

				// 精准搜索：完全匹配或带有 -scaled 后缀（避免匹配 _gallery 等无关文件）
				$attachment_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND (meta_value = %s OR meta_value = %s) LIMIT 1",
						$path_prefix . $base_name_only . '.' . $path_info['extension'],
						$path_prefix . $base_name_only . '-scaled.' . $path_info['extension']
					)
				);

				// 如果还是没找到，且文件名包含 -scaled，尝试去掉它再搜
				if ( ! $attachment_id && strpos( $path_info['filename'], '-scaled' ) !== false ) {
					$unscaled_name = str_replace( '-scaled', '', $path_info['filename'] );
					$attachment_id = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
							$path_prefix . $unscaled_name . '.' . $path_info['extension']
						)
					);
				}
			}
		}

		return $attachment_id ? intval( $attachment_id ) : false;
	}
}
