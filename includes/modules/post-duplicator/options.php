<?php
/**
 * Post Duplicator Module - CSF Options
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$legacy   = get_option( 'w2p_post_duplicator_settings', array() );
$defaults = array(
	'mode'                                   => 'advanced',
	'single_after_duplication_action'        => 'notice',
	'list_single_after_duplication_action'   => 'notice',
	'list_multiple_after_duplication_action' => 'notice',
	'status'                                 => 'draft',
	'type'                                   => 'same',
	'post_author'                            => 'current_user',
	'timestamp'                              => 'current',
	'title'                                  => __( 'Copy', 'wp-genius' ),
	'slug'                                   => __( 'copy', 'wp-genius' ),
	'time_offset'                            => false,
	'time_offset_days'                       => 0,
	'time_offset_hours'                      => 0,
	'time_offset_minutes'                    => 0,
	'time_offset_seconds'                    => 0,
	'time_offset_direction'                  => 'newer',
);
$settings = wp_parse_args( $legacy, $defaults );

return array(
	'module_id' => 'post-duplicator',
	'id'        => 'post_duplicator',
	'title'     => __( 'Post Duplicator', 'wp-genius' ),
	'icon'      => 'fa-solid fa-copy',
	'fields'    => array(
		array(
			'id'      => '_subheading_config',
			'type'    => 'subheading',
			'content' => __( 'Configure post duplication behavior', 'wp-genius' ),
		),
		array(
			'id'      => 'post_duplicator_mode',
			'type'    => 'select',
			'title'   => __( 'Mode', 'wp-genius' ),
			'options' => array(
				'basic'    => __( 'Basic (Quick Duplicate)', 'wp-genius' ),
				'advanced' => __( 'Advanced (Popup Modal)', 'wp-genius' ),
			),
			'default' => $settings['mode'],
			'desc'    => __( 'Advanced mode opens a modal to adjust settings before duplication.', 'wp-genius' ),
		),
		array(
			'id'      => 'post_duplicator_list_single_after_duplication_action',
			'type'    => 'select',
			'title'   => __( 'After Duplication (Post List Screen)', 'wp-genius' ),
			'options' => array(
				'notice'   => __( 'Display Notice', 'wp-genius' ),
				'refresh'  => __( 'Refresh Page', 'wp-genius' ),
				'new_tab'  => __( 'Open in New Tab', 'wp-genius' ),
				'same_tab' => __( 'Open in Same Tab', 'wp-genius' ),
			),
			'default' => $settings['list_single_after_duplication_action'],
		),
		array(
			'id'      => '_subheading_advanced_behavior',
			'type'    => 'subheading',
			'content' => __( 'Advanced Behavior', 'wp-genius' ),
		),
		array(
			'id'      => 'post_duplicator_title',
			'type'    => 'text',
			'title'   => __( 'Duplicate Title Suffix', 'wp-genius' ),
			'desc'    => __( 'Appended to the duplicate post title.', 'wp-genius' ),
			'default' => $settings['title'],
		),
		array(
			'id'      => 'post_duplicator_slug',
			'type'    => 'text',
			'title'   => __( 'Duplicate Slug Suffix', 'wp-genius' ),
			'desc'    => __( 'Appended to the duplicate post slug.', 'wp-genius' ),
			'default' => $settings['slug'],
		),
		array(
			'id'      => 'post_duplicator_status',
			'type'    => 'select',
			'title'   => __( 'Post Status', 'wp-genius' ),
			'options' => array(
				'same'    => __( 'Same as original', 'wp-genius' ),
				'draft'   => __( 'Draft', 'wp-genius' ),
				'publish' => __( 'Published', 'wp-genius' ),
				'pending' => __( 'Pending', 'wp-genius' ),
			),
			'default' => $settings['status'],
		),
		array(
			'id'      => 'post_duplicator_timestamp',
			'type'    => 'select',
			'title'   => __( 'Post Date', 'wp-genius' ),
			'options' => array(
				'current'   => __( 'Current Time', 'wp-genius' ),
				'duplicate' => __( 'Duplicate Timestamp', 'wp-genius' ),
			),
			'default' => $settings['timestamp'],
		),
		array(
			'id'      => 'post_duplicator_post_author',
			'type'    => 'select',
			'title'   => __( 'Post Author', 'wp-genius' ),
			'options' => array(
				'current_user'  => __( 'Current User', 'wp-genius' ),
				'original_user' => __( 'Original Post Author', 'wp-genius' ),
			),
			'default' => $settings['post_author'],
		),
		array(
			'id'      => '_subheading_title_format',
			'type'    => 'subheading',
			'content' => __( 'Title Formatting', 'wp-genius' ),
		),
		array(
			'id'      => 'post_duplicator_time_offset',
			'type'    => 'switcher',
			'title'   => __( 'Enable Offset', 'wp-genius' ),
			'default' => $settings['time_offset'],
		),
		array(
			'id'         => 'post_duplicator_time_offset_days',
			'type'       => 'number',
			'title'      => __( 'Days', 'wp-genius' ),
			'default'    => $settings['time_offset_days'],
			'dependency' => array( 'post_duplicator_time_offset', '==', 'true' ),
		),
		array(
			'id'         => 'post_duplicator_time_offset_hours',
			'type'       => 'number',
			'title'      => __( 'Hours', 'wp-genius' ),
			'default'    => $settings['time_offset_hours'],
			'dependency' => array( 'post_duplicator_time_offset', '==', 'true' ),
		),
		array(
			'id'         => 'post_duplicator_time_offset_minutes',
			'type'       => 'number',
			'title'      => __( 'Minutes', 'wp-genius' ),
			'default'    => $settings['time_offset_minutes'],
			'dependency' => array( 'post_duplicator_time_offset', '==', 'true' ),
		),
		array(
			'id'         => 'post_duplicator_time_offset_seconds',
			'type'       => 'number',
			'title'      => __( 'Seconds', 'wp-genius' ),
			'default'    => $settings['time_offset_seconds'],
			'dependency' => array( 'post_duplicator_time_offset', '==', 'true' ),
		),
		array(
			'id'         => 'post_duplicator_time_offset_direction',
			'type'       => 'select',
			'title'      => __( 'Direction', 'wp-genius' ),
			'options'    => array(
				'newer' => __( 'Newer', 'wp-genius' ),
				'older' => __( 'Older', 'wp-genius' ),
			),
			'default'    => $settings['time_offset_direction'],
			'dependency' => array( 'post_duplicator_time_offset', '==', 'true' ),
		),
	),
);
