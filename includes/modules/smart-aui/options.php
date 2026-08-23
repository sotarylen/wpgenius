<?php
if ( ! defined( 'ABSPATH' ) ) {
	die; // Cannot access directly.
}

// Config settings
$prefix = 'w2p_settings';

// Get current post types for exclusion
$post_types      = get_post_types( array( 'public' => true ), 'objects' );
$exclude_options = array();
foreach ( $post_types as $w2p_pt ) {
	if ( in_array( $w2p_pt->name, array( 'attachment', 'revision', 'nav_menu_item' ), true ) ) {
		continue;
	}
	$exclude_options[ $w2p_pt->name ] = $w2p_pt->labels->name;
}

return array(
	'module_id' => 'smart-aui', // Critical: Must match directory name
	'title'     => __( 'Smart AUI', 'wp-genius' ),
	'icon'      => 'fa-solid fa-cloud-arrow-down',
	'fields'    => array(
		array(
			'id'   => 'smart_aui_tabs',
			'type' => 'tabbed',
			'tabs' => array(
				// Tab 1: Settings (merged General + Filtering + Advanced)
				array(
					'title'  => __( 'Smart AUI Settings', 'wp-genius' ),
					'icon'   => 'fa fa-cog',
					'fields' => array(
						// === General Settings ===
						array(
							'id'      => '_subheading_general_config',
							'type'    => 'subheading',
							'content' => __( 'General Configuration', 'wp-genius' ),
						),
						array(
							'id'      => 'smart_aui_base_url',
							'type'    => 'text',
							'title'   => __( 'Base URL', 'wp-genius' ),
							'desc'    => __( 'The base URL to use for uploaded images. Defaults to your site URL.', 'wp-genius' ),
							'default' => get_site_url(),
						),
						array(
							'id'      => 'smart_aui_image_name_pattern',
							'type'    => 'text',
							'title'   => __( 'Image Name Pattern', 'wp-genius' ),
							// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- %filename% etc. are template-variable examples, not printf placeholders.
							'desc'    => __( 'Pattern for naming uploaded images. Available variables: %filename%, %post_title%, %post_date%, %random%.', 'wp-genius' ),
							'default' => '%filename%',
						),
						array(
							'id'      => 'smart_aui_alt_text_pattern',
							'type'    => 'text',
							'title'   => __( 'Alt Text Pattern', 'wp-genius' ),
							// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- %filename% etc. are template-variable examples, not printf placeholders.
							'desc'    => __( 'Pattern for alt text of uploaded images. Available variables: %image_alt%, %filename%, %post_title%.', 'wp-genius' ),
							'default' => '%image_alt%',
						),
						array(
							'id'      => 'smart_aui_auto_set_featured_image',
							'type'    => 'switcher',
							'title'   => __( 'Auto Set Featured Image', 'wp-genius' ),
							'label'   => __( 'Automatically set the first uploaded image as featured image if none exists.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'smart_aui_process_images_on_rest_api',
							'type'    => 'switcher',
							'title'   => __( 'REST API Support', 'wp-genius' ),
							'label'   => __( 'Automatically upload remote images when content is created via REST API.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'smart_aui_attach_orphan_images',
							'type'    => 'switcher',
							'title'   => __( 'Attach Orphan Images', 'wp-genius' ),
							'label'   => __( 'When assigning an ID to an image that is an orphan in the media library (not attached to any post/page), automatically set this post as its parent.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'smart_aui_capture_videos',
							'type'    => 'switcher',
							'title'   => __( 'Capture Videos', 'wp-genius' ),
							'label'   => __( 'Automatically download and import remote videos from &lt;video&gt; tags to media library.', 'wp-genius' ),
							'default' => false,
						),

						// === Filtering Rules ===
						array(
							'id'      => '_subheading_filtering_rules',
							'type'    => 'subheading',
							'content' => __( 'Filtering Rules', 'wp-genius' ),
						),
						array(
							'id'      => 'smart_aui_min_width',
							'type'    => 'number',
							'title'   => __( 'Min Width', 'wp-genius' ),
							'unit'    => 'px',
							'default' => 300,
						),
						array(
							'id'      => 'smart_aui_min_height',
							'type'    => 'number',
							'title'   => __( 'Min Height', 'wp-genius' ),
							'unit'    => 'px',
							'default' => 200,
						),
						array(
							'id'      => '_submessage_dimension_filter',
							'type'    => 'submessage',
							'style'   => 'info',
							'content' => __( 'Skip images with dimensions smaller than these values (useful for filtering out icons/avatars). Set to 0 to process all images.', 'wp-genius' ),
						),
						array(
							'id'          => 'smart_aui_exclude_domains',
							'type'        => 'textarea',
							'title'       => __( 'Exclude Domains', 'wp-genius' ),
							'placeholder' => 'example.com',
							'desc'        => __( 'Enter domains to exclude from processing, one per line. Supports wildcards (e.g., *.xuite.net).', 'wp-genius' ),
						),
						array(
							'id'      => 'smart_aui_exclude_post_types',
							'type'    => 'checkbox',
							'title'   => __( 'Exclude Post Types', 'wp-genius' ),
							'options' => $exclude_options,
							'desc'    => __( 'Post types that should skip automatic image processing.', 'wp-genius' ),
						),

						// === Performance & Advanced ===
						array(
							'id'      => '_subheading_performance_advanced',
							'type'    => 'subheading',
							'content' => __( 'Performance & Advanced', 'wp-genius' ),
						),
						array(
							'id'       => 'smart_aui_concurrent_threads',
							'type'     => 'slider',
							'title'    => __( 'Concurrent Threads', 'wp-genius' ),
							'subtitle' => __( 'Maximum number of concurrent image downloads per post.', 'wp-genius' ),
							'min'      => 1,
							'max'      => 16,
							'unit'     => __( 'threads', 'wp-genius' ),
							'default'  => 4,
						),
						array(
							'id'       => 'smart_aui_max_retries',
							'type'     => 'slider',
							'title'    => __( 'Max Retries', 'wp-genius' ),
							'subtitle' => __( 'Maximum retry attempts when an image download fails.', 'wp-genius' ),
							'min'      => 0,
							'max'      => 10,
							'default'  => 3,
						),
						array(
							'id'      => 'smart_aui_skip_duplicates',
							'type'    => 'switcher',
							'title'   => __( 'Skip Duplicate Images', 'wp-genius' ),
							'label'   => __( 'If enabled, existing images will be reused. If disabled, all images will be redownloaded.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'smart_aui_show_progress_ui',
							'type'    => 'switcher',
							'title'   => __( 'Show Upload Progress', 'wp-genius' ),
							'label'   => __( 'Display a progress bar when saving posts with external images.', 'wp-genius' ),
							'default' => true,
						),
					),
				),
				// Tab 2: 媒体增强（迁移自子主题 Impreza-child 的媒体增强功能）
				array(
					'title'  => __( '媒体增强', 'wp-genius' ),
					'icon'   => 'fa fa-images',
					'fields' => array(
						array(
							'id'      => '_subheading_media_enhance',
							'type'    => 'subheading',
							'content' => __( 'Media Enhancements', 'wp-genius' ),
						),
						array(
							'id'      => 'smart_aui_media_find_posts_filter',
							'type'    => 'switcher',
							'title'   => __( '附加筛选', 'wp-genius' ),
							'label'   => __( '在「附加到文章或页面」弹窗注入 Type / ID 范围 / Status 三档筛选；查询白名单 post / page / novel / albums，固定排除 chapter，搜索只匹配文章标题（不查内容），每页 25 条。', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'smart_aui_media_bulk_move_category',
							'type'    => 'switcher',
							'title'   => __( '批量移动到分类', 'wp-genius' ),
							'label'   => __( '媒体库列表模式批量操作新增「移动到分类」，选择目标分类（us_media_category）后批量替换附件分类，并提示移动结果。', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'smart_aui_media_mime_cache',
							'type'    => 'switcher',
							'title'   => __( '媒体库性能优化', 'wp-genius' ),
							'label'   => __( '短路 get_available_post_mime_types 慢查询（约百万附件下表扫描 ~20s），Redis → wp_options → SQL 三级缓存 + 双写，TTL 12h；CLI 提供 media-mime-flush / media-mime-test / media-perf-verify。', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'smart_aui_enhance_attach',
							'type'    => 'switcher',
							'title'   => __( '文件名反查与 URL 回写', 'wp-genius' ),
							'label'   => __( '媒体库列表 Unattached 过滤下，弹窗自动按附件文件名反查包含该图（内容中）的文章；选择后经 wp_media_attach_action 把文章里匹配文件名的图片 URL（含远程遗留 URL）替换为本地 URL。', 'wp-genius' ),
							'default' => false,
						),
						array(
							'id'      => '_submessage_media_enhance_migration',
							'type'    => 'submessage',
							'style'   => 'warning',
							'content' => __( '以下功能迁移自子主题 Impreza-child。与子主题同名功能并存时以先加载者生效；迁移完成后请删除子主题 functions.php 的 [任务1][任务3] 段与 inc/media-bulk-move-category.php。', 'wp-genius' ),
						),
					),
				),
				// Tab 3: Logs
				array(
					'title'  => __( 'Capture Failure Logs', 'wp-genius' ),
					'icon'   => 'fa fa-history',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => '
                                <div class="w2p-section">
                                    <div class="w2p-section-body">
                                        <div class="w2p-section-header">
                                            <h3>' . __( 'Capture Failure Logs', 'wp-genius' ) . '</h3>
                                            <p>
                                                <button type="button" id="w2p-smart-aui-clear-logs" class="w2p-btn w2p-btn-primary">
                                                    <i class="fa-solid fa-trash"></i>
                                                    ' . __( 'Clear All Logs', 'wp-genius' ) . '
                                                </button>
                                            </p>
                                        </div>

                                        <div class="w2p-alert w2p-alert-success">
                                            <i class="fa-solid fa-circle-check"></i>
                                            ' . __( 'The following image URLs failed to download and will be skipped in future attempts to avoid infinite retry loops.', 'wp-genius' ) . '
                                        </div>

                                        <div class="w2p-log-container" id="w2p-smart-aui-logs-container">
                                            ' . __( 'Loading logs...', 'wp-genius' ) . '
                                        </div>
                                    </div>
                                </div>
                            ',
						),
					),
				),
			),
		),
	),
);
