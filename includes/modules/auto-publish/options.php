<?php
if ( ! defined( 'ABSPATH' ) ) {
	die; // Cannot access directly.
}

// Return configuration array instead of calling CSF::createSection directly
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
                    'title'  => __( 'Configuration', 'wp-genius' ),
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
                    'title'  => __( 'Tools', 'wp-genius' ),
                    'icon'   => 'fa fa-tools',
                    'fields' => [
                        [
                            'type'    => 'content',
                            'content' => '
                                <div class="w2p-auto-publish-logs">
                                    <div class="w2p-section-header">
                                        <h4>' . __( 'Manual Bulk Publish', 'wp-genius' ) . '</h4>
                                        <div class="w2p-header-actions">
                                            <button type="button" id="w2p-start-publish" class="button button-primary w2p-btn w2p-btn-primary">
                                                <i class="fa-solid fa-play"></i>
                                                ' . __( 'Start Now', 'wp-genius' ) . '
                                            </button>
                                            <button type="button" id="w2p-stop-publish" class="button w2p-btn w2p-btn-stop hidden">
                                                <i class="fa-solid fa-pause"></i>
                                                ' . __( 'Stop', 'wp-genius' ) . '
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="w2p-manual-publish-controls">
                                        <div class="w2p-manual-publish-info">
                                            <div class="w2p-progress-text">
                                                <strong class="w2p-progress-counter">0/0</strong>
                                            </div>
                                            <div class="progress-text">' . __( 'Ready to start...', 'wp-genius' ) . '</div>
                                        </div>
                                        
                                        <div id="w2p-publish-progress" class="hidden mt-15">
                                            <div class="progress-bar-container">
                                                <div class="progress-bar-inner" style="width: 0%;"></div>
                                            </div>
                                            <div id="w2p-smart-aui-preview-area" class="w2p-smart-aui-preview-area mt-15"></div>
                                        </div>
                                    </div>
                                </div>',
                        ],
                        [
                            'type'    => 'content',
                            'content' => '
                                <div class="w2p-auto-publish-logs">
                                    <div class="w2p-section-header w2p-section-spacing">
                                        <h4>' . __( 'Publish Logs', 'wp-genius' ) . '</h4>
                                        <button type="button" id="w2p-clean-logs" class="button w2p-btn w2p-btn-secondary w2p-btn-small">
                                            <i class="fa-solid fa-trash"></i>
                                            ' . __( 'Clear Logs', 'wp-genius' ) . '
                                        </button>
                                    </div>
                                    
                                    <div class="w2p-log-container">
                                        <table class="wp-list-table widefat fixed striped">
                                            <thead>
                                                <tr>
                                                    <th width="20%">' . __( 'Time', 'wp-genius' ) . '</th>
                                                    <th>' . __( 'Post', 'wp-genius' ) . '</th>
                                                    <th width="15%">' . __( 'Source', 'wp-genius' ) . '</th>
                                                    <th width="15%">' . __( 'Status', 'wp-genius' ) . '</th>
                                                </tr>
                                            </thead>
                                            <tbody id="w2p-publish-logs-body">
                                                <tr><td colspan="4">' . __( 'Loading logs...', 'wp-genius' ) . '</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>',
                        ],
                    ],
                ],
            ],
        ],
	],
];
