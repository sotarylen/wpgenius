<?php
if ( ! defined( 'ABSPATH' ) ) {
	die; // Cannot access directly.
}

// Config settings
$prefix = 'w2p_settings';

// Get current post types for exclusion
$post_types = get_post_types( [ 'public' => true ], 'objects' );
$exclude_options = [];
foreach ( $post_types as $post_type ) {
    if ( in_array( $post_type->name, [ 'attachment', 'revision', 'nav_menu_item' ] ) ) {
        continue;
    }
    $exclude_options[ $post_type->name ] = $post_type->labels->name;
}

return [
    'module_id' => 'smart-aui', // Critical: Must match directory name
    'title'  => __( 'Smart AUI', 'wp-genius' ),
    'icon'   => 'fa-solid fa-cloud-arrow-down',
    'fields' => [
        [
            'id'   => 'smart_aui_tabs',
            'type' => 'tabbed',
            'tabs' => [
                // Tab 1: Settings (merged General + Filtering + Advanced)
                [
                    'title' => __( 'Smart AUI Settings', 'wp-genius' ),
                    'icon'  => 'fa fa-cog',
                    'fields' => [
                        // === General Settings ===
                        [
                            'id'      => '_subheading_general_config',
                            'type'    => 'subheading',
                            'content' => __( 'General Configuration', 'wp-genius' ),
                        ],
                        [
                            'id'      => 'smart_aui_base_url',
                            'type'    => 'text',
                            'title'   => __( 'Base URL', 'wp-genius' ),
                            'desc'    => __( 'The base URL to use for uploaded images. Defaults to your site URL.', 'wp-genius' ),
                            'default' => get_site_url(),
                        ],
                        [
                            'id'      => 'smart_aui_image_name_pattern',
                            'type'    => 'text',
                            'title'   => __( 'Image Name Pattern', 'wp-genius' ),
                            'desc'    => __( 'Pattern for naming uploaded images. Available variables: %filename%, %post_title%, %post_date%, %random%.', 'wp-genius' ),
                            'default' => '%filename%',
                        ],
                        [
                            'id'      => 'smart_aui_alt_text_pattern',
                            'type'    => 'text',
                            'title'   => __( 'Alt Text Pattern', 'wp-genius' ),
                            'desc'    => __( 'Pattern for alt text of uploaded images. Available variables: %image_alt%, %filename%, %post_title%.', 'wp-genius' ),
                            'default' => '%image_alt%',
                        ],
                        [
                            'id'    => 'smart_aui_auto_set_featured_image',
                            'type'  => 'switcher',
                            'title' => __( 'Auto Set Featured Image', 'wp-genius' ),
                            'label' => __( 'Automatically set the first uploaded image as featured image if none exists.', 'wp-genius' ),
                            'default' => true,
                        ],
                        [
                            'id'    => 'smart_aui_process_images_on_rest_api',
                            'type'  => 'switcher',
                            'title' => __( 'REST API Support', 'wp-genius' ),
                            'label' => __( 'Automatically upload remote images when content is created via REST API.', 'wp-genius' ),
                            'default' => true,
                        ],
                        [
                            'id'    => 'smart_aui_capture_videos',
                            'type'  => 'switcher',
                            'title' => __( 'Capture Videos', 'wp-genius' ),
                            'label' => __( 'Automatically download and import remote videos from &lt;video&gt; tags to media library.', 'wp-genius' ),
                            'default' => false,
                        ],
                        
                        // === Filtering Rules ===
                        [
                            'id'      => '_subheading_filtering_rules',
                            'type'    => 'subheading',
                            'content' => __( 'Filtering Rules', 'wp-genius' ),
                        ],
                        [
                            'id'      => 'smart_aui_min_width',
                            'type'    => 'number',
                            'title'   => __( 'Min Width', 'wp-genius' ),
                            'unit'    => 'px',
                            'default' => 300,
                        ],
                        [
                            'id'      => 'smart_aui_min_height',
                            'type'    => 'number',
                            'title'   => __( 'Min Height', 'wp-genius' ),
                            'unit'    => 'px',
                            'default' => 200,
                        ],
                        [
                            'id'      => '_submessage_dimension_filter',
                            'type'    => 'submessage',
                            'style'   => 'info',
                            'content' => __( 'Skip images with dimensions smaller than these values (useful for filtering out icons/avatars). Set to 0 to process all images.', 'wp-genius' ),
                        ],
                        [
                            'id'          => 'smart_aui_exclude_domains',
                            'type'        => 'textarea',
                            'title'       => __( 'Exclude Domains', 'wp-genius' ),
                            'placeholder' => 'example.com',
                            'desc'        => __( 'Enter domains to exclude from processing, one per line. Supports wildcards (e.g., *.xuite.net).', 'wp-genius' ),
                        ],
                        [
                            'id'       => 'smart_aui_exclude_post_types',
                            'type'     => 'checkbox',
                            'title'    => __( 'Exclude Post Types', 'wp-genius' ),
                            'options'  => $exclude_options,
                            'desc'     => __( 'Post types that should skip automatic image processing.', 'wp-genius' ),
                        ],
                        
                        // === Performance & Advanced ===
                        [
                            'id'      => '_subheading_performance_advanced',
                            'type'    => 'subheading',
                            'content' => __( 'Performance & Advanced', 'wp-genius' ),
                        ],
                        [
                            'id'      => 'smart_aui_concurrent_threads',
                            'type'    => 'slider',
                            'title'   => __( 'Concurrent Threads', 'wp-genius' ),
                            'subtitle' => __( 'Maximum number of concurrent image downloads per post.', 'wp-genius' ),
                            'min'     => 1,
                            'max'     => 16,
                            'unit'    => __( 'threads', 'wp-genius' ),
                            'default' => 4,
                        ],
                        [
                            'id'      => 'smart_aui_max_retries',
                            'type'    => 'slider',
                            'title'   => __( 'Max Retries', 'wp-genius' ),
                            'subtitle' => __( 'Maximum retry attempts when an image download fails.', 'wp-genius' ),
                            'min'     => 0,
                            'max'     => 10,
                            'default' => 3,
                        ],
                        [
                            'id'    => 'smart_aui_skip_duplicates',
                            'type'  => 'switcher',
                            'title' => __( 'Skip Duplicate Images', 'wp-genius' ),
                            'label' => __( 'If enabled, existing images will be reused. If disabled, all images will be redownloaded.', 'wp-genius' ),
                            'default' => true,
                        ],
                         [
                            'id'    => 'smart_aui_show_progress_ui',
                            'type'  => 'switcher',
                            'title' => __( 'Show Upload Progress', 'wp-genius' ),
                            'label' => __( 'Display a progress bar when saving posts with external images.', 'wp-genius' ),
                            'default' => true,
                        ],
                    ]
                ],
                // Tab 2: Logs
                [
                    'title' => __( 'Capture Failure Logs', 'wp-genius' ),
                    'icon'  => 'fa fa-history',
                    'fields' => [
                        [
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
                        ]
                    ]
                ]
            ]
        ]
    ]
];
