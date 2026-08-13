<?php
if ( ! defined( 'ABSPATH' ) ) {
	die; // Cannot access directly.
}

// Helper to get template content
$get_template_content = function() {
    ob_start();
    include plugin_dir_path( __FILE__ ) . 'views/manual-publish-ui.php';
    return ob_get_clean();
};

return [
	'module_id' => 'auto-publish',
	'id'     => 'auto_publish',
	'title'  => __( 'Auto Publish', 'wp-genius' ),
	'icon'   => 'fa fa-clock',
	'fields' => [
        [
            'id'   => 'auto_publish_tabs',
            'type' => 'tabbed',
            'tabs' => [
                // Tab 1: Configuration
                [
                    'title'  => __( 'Auto Publish', 'wp-genius' ),
                    'icon'   => 'fa fa-sliders',
                    'fields' => [
                        [
                            'id'    => 'auto_publish_cron_enabled',
                            'type'  => 'switcher',
                            'title' => __( 'Enable Auto Publish', 'wp-genius' ),
                            'label' => __( 'Enable background scheduled publishing using WP-Cron.', 'wp-genius' ),
                        ],
                        [
                            'id'          => 'auto_publish_interval',
                            'type'        => 'select',
                            'title'       => __( 'Execution Interval', 'wp-genius' ),
                            'placeholder' => __( 'Select an interval', 'wp-genius' ),
                            'options'     => [
                                'w2p_every_5_minutes'  => __( 'Every 5 Minutes', 'wp-genius' ),
                                'w2p_every_15_minutes' => __( 'Every 15 Minutes', 'wp-genius' ),
                                'w2p_every_30_minutes' => __( 'Every 30 Minutes', 'wp-genius' ),
                                'hourly'               => __( 'Hourly', 'wp-genius' ),
                                'twicedaily'           => __( 'Twice Daily', 'wp-genius' ),
                                'daily'                => __( 'Daily', 'wp-genius' ),
                            ],
                            'default'     => 'hourly',
                            'dependency'  => [ 'auto_publish_cron_enabled', '==', 'true' ],
                        ],
                        [
                            'id'      => 'auto_publish_batch_size',
                            'type'    => 'slider',
                            'title'   => __( 'Batch Size', 'wp-genius' ),
                            'subtitle' => __( 'Number of drafts to publish in each execution.', 'wp-genius' ),
                            'min'     => 5,
                            'max'     => 100,
                            'step'    => 5,
                            'default' => 5,
                            'unit'    => ' ' . __( 'posts', 'wp-genius' ),
                        ],
                    ],
                ],
                // Tab 2: Tools
                [
                    'title'  => __( 'Manual Bulk Publish', 'wp-genius' ),
                    'icon'   => 'fa fa-tools',
                    'fields' => [
                        [
                            'type'    => 'content',
                            'content' => $get_template_content(),
                        ],
                    ],
                ],
            ],
        ],
	],
];

