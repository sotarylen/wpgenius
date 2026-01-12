<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Image_Watermark_Settings_API class.
 *
 * @class W2P_Image_Watermark_Settings_API
 */
class W2P_Image_Watermark_Settings_API {

	private $settings = [];
	private $input_settings = [];
	private $validated_settings = [];
	private $pages = [];
	private $page_types = [];
	private $prefix = '';
	private $slug = '';
	private $domain = '';
	private $plugin = '';
	private $plugin_url = '';
	private $object;
	private $nested = false;
	private $module_slug = '';

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct( $args ) {
		// set initial data
		$this->prefix = $args['prefix'];
		$this->domain = $args['domain'];
		$this->nested = isset( $args['nested'] ) ? (bool) $args['nested'] : false;

		// empty slug?
		if ( empty( $args['slug'] ) )
			$this->slug = $args['domain'];
		else
			$this->slug = $args['slug'];

		$this->object = $args['object'];
		$this->plugin = $args['plugin'];
		$this->plugin_url = $args['plugin_url'];
		$this->module_slug = isset( $args['module_slug'] ) ? $args['module_slug'] : '';


		// actions
		// add_action( 'admin_menu', [ $this, 'admin_menu_options' ], 11 ); // Menu registration handled by module wrapper
		
		if ( did_action( 'admin_init' ) ) {
			$this->init_pages();
		} else {
			add_action( 'admin_init', [ $this, 'init_pages' ] );
		}

		add_action( 'admin_init', [ $this, 'register_settings' ], 11 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * Get prefix.
	 *
	 * @return string
	 */
	public function get_prefix() {
		return $this->prefix;
	}

	/**
	 * Get pages.
	 *
	 * @return array
	 */
	public function get_pages() {
		return $this->pages;
	}

	/**
	 * Get settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Get current input settings during saving.
	 *
	 * @return array
	 */
	public function get_input_settings() {
		return $this->input_settings;
	}

	/**
	 * Get already validated setting fields during saving.
	 *
	 * @return array
	 */
	public function get_validated_settings() {
		return $this->validated_settings;
	}

	/**
	 * Load default scripts and styles.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		$handler = $this->prefix . '-settings-api-style';

		// register and enqueue styles
		wp_register_style( $handler, false );
		wp_enqueue_style( $handler );

		// add styles
		wp_add_inline_style( $handler, '.nav-tab-wrapper span.nav-span-disabled {
			cursor: not-allowed;
			float: left;
		}
		body.rtl .nav-tab-wrapper span.nav-span-disabled {
			float: right;
		}
		.nav-tab-wrapper a.nav-tab.nav-tab-disabled {
			pointer-events: none;
		}
		.nav-tab-wrapper a.nav-tab.nav-tab-disabled:hover {
			cursor: not-allowed;
		}' );
	}

	/**
	 * Initialize page data (formerly admin_menu_options).
	 *
	 * @return void
	 */
	public function init_pages() {
		$this->pages = apply_filters( $this->prefix . '_settings_pages', [] );
		$types = [
			'page'			=> [],
			'subpage'		=> [],
			'settings_page'	=> []
		];

		foreach ( $this->pages as $page => $data ) {
			// skip invalid page types
			if ( empty( $data['type'] ) || ! array_key_exists( $data['type'], $types ) )
				continue;

			if ( $data['type'] === 'page' ) {
				$types['page'][$data['menu_slug']] = $page;
			} elseif ( $data['type'] === 'subpage' ) {
				$types['subpage'][$data['menu_slug']] = $page;
			} elseif ( $data['type'] === 'settings_page' ) {
				$types['settings_page'][$data['menu_slug']] = $page;
			}
		}

		// set page types
		$this->page_types = $types;
	}

	/**
	 * Render settings.
	 *
	 * @global string $pagenow
	 *
	 * @return void
	 */
	public function options_page() {
		global $pagenow;

		$valid_page = false;
		$page_type = '';
		$matches = [];

		// get current screen
		$screen = get_current_screen();

		// Check if we can determine page from standard WP routing
		if ( $pagenow === 'admin.php' && isset( $screen->base ) && preg_match( '/^toplevel_page_(' . implode( '|', $this->page_types['page'] ) . ')$/', $screen->base, $matches ) === 1 && ! empty( $matches[1] ) ) {
			$valid_page = true;
			$page_type = 'page';
			$url_page = 'admin.php';
		}

		if ( ! $valid_page && $pagenow === 'admin.php' && isset( $screen->base ) && preg_match( '/^(?:toplevel|' . $this->prefix . ')_page_' . $this->prefix . '-(' . implode( '|', $this->page_types['subpage'] ) . ')-settings$/', $screen->base, $matches ) === 1 && ! empty( $matches[1] ) ) {
			$valid_page = true;
			$page_type = 'subpage';
			$url_page = 'admin.php';
		}

		if ( ! $valid_page && $pagenow === 'options-general.php' && isset( $screen->base ) && preg_match( '/^(?:settings_page_)(' . implode( '|', array_keys( $this->page_types['settings_page'] ) ) . ')$/', $screen->base, $matches ) === 1 ) {
			$valid_page = true;
			$page_type = 'settings_page';
			$url_page = 'options-general.php';
		}

		// Fallback for manual rendering (e.g. inside WP Genius module wrapper)
		if ( ! $valid_page ) {
			// Default to the first settings page found
			if ( ! empty( $this->page_types['settings_page'] ) ) {
				$slug = key( $this->page_types['settings_page'] );
				$page_key = $this->page_types['settings_page'][$slug];
				$valid_page = true;
				$page_type = 'settings_page';
				$url_page = 'admin.php'; // or whatever
				$matches[1] = $slug;
			} elseif ( ! empty( $this->page_types['page'] ) ) {
				$slug = key( $this->page_types['page'] );
				$page_key = $this->page_types['page'][$slug];
				$valid_page = true;
				$page_type = 'page';
				$url_page = 'admin.php';
				$matches[1] = $slug;
			}
		}

		// skip invalid pages
		if ( ! $valid_page )
			return;

		// Re-fetch page key if we matched via regex
		if ( empty( $page_key ) && isset( $matches[1] ) ) {
			$page_key = $this->page_types[$page_type][$matches[1]];
		}

		$tab_key = '';
		$tabs = [];

		// any tabs?
		if ( array_key_exists( 'tabs', $this->pages[$page_key] ) ) {
			// get tabs
			$tabs = $this->pages[$page_key]['tabs'];

			// reset tabs
			reset( $tabs );

			// get first default tab
			$first_tab = key( $tabs );

			// get current tab
			$tab_key = ! empty( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $tabs ) ? $_GET['tab'] : $first_tab;

			// check current tab
			if ( ! empty( $_GET['tab'] ) )
				$tab_key = sanitize_key( $_GET['tab'] );

			// invalid tab?
			if ( ! array_key_exists( $tab_key, $tabs ) )
				$tab_key = $first_tab;

			$tab_label = ! empty( $tabs[$tab_key]['label'] ) ? $tabs[$tab_key]['label'] : '';
			$tab_heading = ! empty( $tabs[$tab_key]['heading'] ) ? $tabs[$tab_key]['heading'] : '';
		} else
			$tab_label = '';

		if ( empty( $tabs ) )
			$tab_heading = '';

		$heading = '';

		if ( ! empty( $tab_heading ) )
			$heading = $tab_heading;
		elseif ( ! empty( $tab_label ) )
			$heading = $tab_label;
		elseif ( ! empty( $this->pages[$page_key]['page_title'] ) )
			$heading = $this->pages[$page_key]['page_title'];
		elseif ( ! empty( $this->plugin ) )
			$heading = $this->plugin;
		else
			$heading = $matches[1];

		echo '
		<div class="wrap ' . $this->prefix . '-settings-wrapper' . '" data-settings-prefix="' . esc_attr( $this->prefix ) . '">
			<div class="header-wrapper">
				<span class="header-title">' . esc_html( $heading ) . '</span>
			</div>';

		if ( ! empty( $tabs ) ) {
			echo '
			<nav class="nav-tab-wrapper">';

			foreach ( $tabs as $key => $tab ) {
				if ( ! empty( $tab['disabled'] ) )
					$url = '';
				else {
					// Build URL preserving current page context
					$current_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ( ! empty( $matches[1] ) ? $matches[1] : '' );
					$query_args = [ 'page' => $current_page, 'tab' => $key ];
					
					// Keep w2p_module context if present, OR force it if module_slug is set
					if ( ! empty( $this->module_slug ) ) {
						$query_args['w2p_module'] = $this->module_slug;
					} elseif ( isset( $_GET['w2p_module'] ) ) {
						$query_args['w2p_module'] = sanitize_key( $_GET['w2p_module'] );
					}
					
					$url = add_query_arg( $query_args, admin_url( $url_page ) );
				}

				if ( ! empty( $tab['disabled'] ) )
					echo '<span class="nav-span-disabled">';

				echo '
				<a class="nav-tab' . ( $tab_key === $key ? ' nav-tab-active' : '' ) . ( ! empty( $tab['disabled'] ) ? ' nav-tab-disabled' : '' ) . ( ! empty( $tab['class'] ) ? ' ' . esc_attr( $tab['class'] ) : '' ) . '" href="' . ( $url !== '' ? esc_url( $url ) : '#' ) . '">' . esc_html( $tab['label'] ) . '</a>';

				if ( ! empty( $tab['disabled'] ) )
					echo '</span>';
			}

			echo '
			</nav>';
		}

		echo '
			<div class="content-wrapper">
			<h1 class="screen-reader-text">' . esc_html( $heading ) . '</h1>';

		// skip for internal options page
		if ( $page_type !== 'settings_page' )
			settings_errors();

		// get settings page classes
		$settings_class = apply_filters( $this->prefix . '_settings_page_class', [ $this->slug . '-settings', $tab_key . '-settings', $this->prefix . '-settings' ] );

		// sanitize settings page classes
		$settings_class = array_unique( array_filter( array_map( 'sanitize_html_class', $settings_class ) ) );

		// resolve setting group for sidebar/form
		if ( ! empty( $tab_key ) ) {
			if ( ! empty( $tabs[$tab_key]['option_name'] ) ) {
				$setting = $tabs[$tab_key]['option_name'];
			} else {
				$setting = $this->prefix . '_' . $tab_key . '_settings';
			}
		} else {
			$setting = $this->prefix . '_' . $matches[1] . '_settings';
		}

		// capture sidebar output
		ob_start();
		do_action( $this->prefix . '_settings_sidebar', $setting, $page_type, $url_page, $tab_key );
		$sidebar_html = trim( ob_get_clean() );

		// add has-sidebar class if sidebar has content
		if ( ! empty( $sidebar_html ) ) {
			$settings_class[] = 'has-sidebar';
		}

		echo '
			<div class="' . implode( ' ', array_map( 'esc_attr', $settings_class ) ) . '">';

		$display_form = true;

		// check form attribute
		if ( ! empty( $tab_key ) && ! empty( $tabs[$tab_key]['form'] ) ) {
			$form = $tabs[$tab_key]['form'];

			if ( isset( $form['buttons'] ) && ! $form['buttons'] )
				$display_form = false;
		} elseif ( isset( $this->settings[$matches[1]]['form'] ) ) {
			$form = $this->settings[$matches[1]]['form'];

			if ( isset( $form['buttons'] ) && ! $form['buttons'] )
				$display_form = false;
		}

		if ( $display_form ) {
			echo '
				<form action="options.php" method="post" novalidate class="' . $this->prefix . '-settings-form">';
		}

		settings_fields( $setting );

		if ( $display_form )
			do_action( $this->prefix . '_settings_form', $setting, $page_type, $url_page, $tab_key );

		// Filter sections for current tab
		global $wp_settings_sections;
		
		if ( ! empty( $tab_key ) && ! empty( $tabs[$tab_key]['sections'] ) && is_array( $tabs[$tab_key]['sections'] ) ) {
			$tab_sections = array_keys( $tabs[$tab_key]['sections'] );
			
			// Backup original sections
			$original_sections = isset( $wp_settings_sections[$setting] ) ? $wp_settings_sections[$setting] : [];
			
			// Filter sections
			if ( ! empty( $original_sections ) ) {
				$filtered_sections = [];
				foreach ( $original_sections as $section_id => $section_data ) {
					if ( in_array( $section_id, $tab_sections, true ) ) {
						$filtered_sections[$section_id] = $section_data;
					}
				}
				$wp_settings_sections[$setting] = $filtered_sections;
			}
			
			do_settings_sections( $setting );
			
			// Restore original sections
			$wp_settings_sections[$setting] = $original_sections;
		} else {
			do_settings_sections( $setting );
		}

		if ( $display_form ) {
			$setting_hyphenated = str_replace( '_', '-', $setting );
			echo '
					<p class="submit">';

			submit_button( '', 'primary save-' . $setting_hyphenated, 'save_' . $setting, false, [ 'id' => 'save-' . $setting_hyphenated ] );

			submit_button( __( 'Reset to defaults', $this->domain ), 'outline reset-' . $setting_hyphenated, 'reset_' . $setting, false, [ 'id' => 'reset-' . $setting_hyphenated ] );

			echo '
					</p>
				</form>';
		}

		// output sidebar if it has content
		if ( ! empty( $sidebar_html ) ) {
			echo '
			<div class="' . $this->prefix . '-sidebar">' . $sidebar_html . '</div>';
		}

		echo '
			</div>
			</div>';

		echo '
			<div class="clear"></div>
		</div>';
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		$this->settings = apply_filters( $this->prefix . '_settings_data', [] );

		// check settings
		foreach ( $this->settings as $setting_id => $setting ) {
			// tabs?
			if ( is_array( $setting['option_name'] ) ) {
				foreach ( $setting['option_name'] as $tab => $option_name ) {
					$this->register_setting_fields( $tab, $setting, $option_name );
				}
			} else
				$this->register_setting_fields( $setting_id, $setting );
		}
	}

	/**
	 * Register setting with sections and fields.
	 *
	 * @return void
	 */
	public function register_setting_fields( $setting_id, $setting, $option_name = '' ) {
		if ( empty( $option_name ) )
			$option_name = $setting['option_name'];

		// register setting
		register_setting( $option_name, $option_name, ! empty( $setting['validate'] ) ? $setting['validate'] : [ $this, 'validate_settings' ] );

		// register setting sections
		if ( ! empty( $setting['sections'] ) ) {
			foreach ( $setting['sections'] as $section_id => $section ) {
				// skip unwanted sections
				if ( ! empty( $section['tab'] ) && $section['tab'] !== $setting_id )
					continue;

				// Auto-generate section classes and wrapper
				$base_slug = sanitize_html_class( str_replace( '_', '-', $section_id ) );
				$section_prefix = apply_filters( $this->prefix . '_settings_section_prefix', $this->prefix );
				$section_prefix = sanitize_html_class( $section_prefix );
				$section_classes = $section_prefix . '-section ' . $section_prefix . '-section-' . $base_slug;
				$section_args = [
					'section_class' => $section_classes,
					'before_section' => '<section id="' . $section_prefix . '-section-' . $base_slug . '" class="%s">',
					'after_section' => '</section>',
				];

				add_settings_section(
					$section_id,
					! empty( $section['title'] ) ? esc_html( $section['title'] ) : '',
					! empty( $section['callback'] ) ? $section['callback'] : null,
					! empty( $section['page'] ) ? $section['page'] : $option_name,
					$section_args
				);
			}
		}

		// register setting fields
		if ( ! empty( $setting['fields'] ) ) {
			foreach ( $setting['fields'] as $field_key => $field ) {
				// skip unwanted fields
				if ( ! empty( $field['tab'] ) && $field['tab'] !== $setting_id )
					continue;

				// set field ID
				$field_id = implode( '_', [ $this->prefix, $setting_id, $field_key ] );

				// skip rendering this field?
				if ( ! empty( $field['skip_rendering'] ) )
					continue;

				// prepare field args
				$args = array_merge( $this->prepare_field_args( $field, $field_id, $field_key, $setting_id, $option_name ), $field );
				$args['setting_id'] = $setting_id;
				$class = sanitize_html_class( str_replace( '_', '-', $field_id ) );
				$classes = [ $class ];

				if ( ! empty( $args['class'] ) ) {
					$extra_classes = preg_split( '/\s+/', trim( $args['class'] ) );
					$extra_classes = array_filter( $extra_classes );
					$extra_classes = array_map( 'sanitize_html_class', $extra_classes );
					$classes = array_merge( $classes, $extra_classes );
				}

				$classes = array_values( array_unique( array_filter( $classes ) ) );

				$field_class = implode( ' ', $classes );
				$wrapper_class = $class !== '' ? $class . '-row' : '';

				$args['class'] = $wrapper_class;
				$args['field_class'] = $field_class;
				$args['css_id'] = $class;

				add_settings_field(
					$field_id,
					! empty( $field['title'] ) ? esc_html( $field['title'] ) : '',
					[ $this, 'render_field' ],
					$option_name,
					! empty( $field['section'] ) ? esc_attr( $field['section'] ) : '',
					$args
				);
			}
		}
	}

	/**
	 * Prepare field arguments.
	 *
	 * @param array $args
	 * @return array
	 */
	public function prepare_field_args( $field, $field_id, $field_key, $setting_id, $setting_name ) {
		// get field type
		$field_type = ! empty( $field['type'] ) ? $field['type'] : '';

		// default lookup path
		$value = null;
		$default = null;
		$name = $setting_name . '[' . $field_key . ']';

		// check for parent
		if ( ! empty( $field['parent'] ) ) {
			$name = $setting_name . '[' . $field['parent'] . '][' . $field_key . ']';

			// try with setting_id first
			if ( isset( $this->object->options[$setting_id][$field['parent']][$field_key] ) ) {
				$value = $this->object->options[$setting_id][$field['parent']][$field_key];
			} elseif ( isset( $this->object->options[$field['parent']][$field_key] ) ) {
				// try without setting_id
				$value = $this->object->options[$field['parent']][$field_key];
			}

			// defaults
			if ( isset( $this->object->defaults[$setting_id][$field['parent']][$field_key] ) ) {
				$default = $this->object->defaults[$setting_id][$field['parent']][$field_key];
			} elseif ( isset( $this->object->defaults[$field['parent']][$field_key] ) ) {
				$default = $this->object->defaults[$field['parent']][$field_key];
			}
		} else {
			// nested?
			if ( $this->nested ) {
				$name = $setting_name . '[' . $setting_id . '][' . $field_key . ']';

				if ( isset( $this->object->options[$setting_id][$field_key] ) )
					$value = $this->object->options[$setting_id][$field_key];

				if ( isset( $this->object->defaults[$setting_id][$field_key] ) )
					$default = $this->object->defaults[$setting_id][$field_key];
			} else {
				// flat
				if ( isset( $this->object->options[$setting_id][$field_key] ) ) {
					$value = $this->object->options[$setting_id][$field_key];
				} elseif ( isset( $this->object->options[$field_key] ) ) {
					$value = $this->object->options[$field_key];
				}

				// defaults
				if ( isset( $this->object->defaults[$setting_id][$field_key] ) ) {
					$default = $this->object->defaults[$setting_id][$field_key];
				} elseif ( isset( $this->object->defaults[$field_key] ) ) {
					$default = $this->object->defaults[$field_key];
				}
			}
		}

		if ( $field_type === 'custom' ) {
			$value = null;
			$default = null;
		}

		return [
			'id'			=> $field_id,
			'html_id'		=> sanitize_html_class( str_replace( '_', '-', $field_id ) ),
			'name'			=> $name,
			'class'			=> ! empty( $field['class'] ) ? $field['class'] : '',
			'type'			=> $field_type,
			'label'			=> ! empty( $field['label'] ) ? $field['label'] : '',
			'description'	=> ! empty( $field['description'] ) ? $field['description'] : '',
			'text'			=> ! empty( $field['text'] ) ? $field['text'] : '',
			'min'			=> ! empty( $field['min'] ) ? (int) $field['min'] : 0,
			'max'			=> ! empty( $field['max'] ) ? (int) $field['max'] : 0,
			'options'		=> ! empty( $field['options'] ) ? $field['options'] : [],
			'callback'		=> ! empty( $field['callback'] ) ? $field['callback'] : null,
			'validate'		=> ! empty( $field['validate'] ) ? $field['validate'] : null,
			'callback_args'	=> ! empty( $field['callback_args'] ) ? $field['callback_args'] : [],
			'default'		=> $default,
			'value'			=> $value,
			'setting_id'    => $setting_id,
			'animation'     => ! empty( $field['animation'] ) ? $field['animation'] : ''
			/*
			after_field
			before_field
			*/
		];
	}

	/**
	 * Render settings field.
	 *
	 * @param array $args
	 * @return void|string
	 */
	public function render_field( $args ) {
		if ( empty( $args ) || ! is_array( $args ) )
			return;

		$div_classes = [];

		if ( ! empty( $args['field_class'] ) ) {
			$div_classes = preg_split( '/\s+/', trim( $args['field_class'] ) );
		}

		$div_classes[] = esc_attr( $this->prefix ) . '-field';
		$div_classes[] = esc_attr( $this->prefix ) . '-field-type-' . sanitize_html_class( $args['type'] );
		$div_classes = array_values( array_unique( array_filter( $div_classes ) ) );

		$data_attrs = '';
		$conditions = [];
		$condition_action = '';

		if ( ! empty( $args['condition'] ) && is_array( $args['condition'] ) ) {
			if ( isset( $args['condition']['field'] ) ) {
				$conditions = [ $args['condition'] ];
			} else {
				$conditions = $args['condition'];
			}
		}

		if ( ! empty( $args['condition_action'] ) && in_array( $args['condition_action'], [ 'show', 'hide', 'enable', 'disable' ], true ) ) {
			$condition_action = $args['condition_action'];
		}

		if ( ! empty( $conditions ) ) {
			$data_attr_prefix = sanitize_html_class( $this->prefix );
			$normalized_conditions = [];

			foreach ( $conditions as $condition ) {
				if ( empty( $condition['field'] ) || empty( $condition['operator'] ) ) {
					continue;
				}

				$field = $condition['field'];

				if ( strpos( $field, '-' ) === false && ! empty( $args['setting_id'] ) ) {
					$field_id = implode( '_', [ $this->prefix, $args['setting_id'], $field ] );
					$field = sanitize_html_class( str_replace( '_', '-', $field_id ) );
				}

				$normalized_conditions[] = [
					'field'    => $field,
					'operator' => $condition['operator'],
					'value'    => isset( $condition['value'] ) ? $condition['value'] : '',
				];
			}

			if ( ! empty( $normalized_conditions ) && $data_attr_prefix !== '' ) {
				if ( $condition_action !== '' ) {
					$data_attrs .= ' data-' . $data_attr_prefix . '-conditional-action="' . esc_attr( $condition_action ) . '"';
				}

				if ( ! empty( $args['animation'] ) && in_array( $args['animation'], [ 'fade', 'slide' ], true ) ) {
					$data_attrs .= ' data-' . $data_attr_prefix . '-animation="' . esc_attr( $args['animation'] ) . '"';
				}
				$data_attrs .= ' data-' . $data_attr_prefix . '-conditional="' . esc_attr( wp_json_encode( $normalized_conditions ) ) . '"';
			}
		}

		$html = '<div id="' . esc_attr( $args['html_id'] ) . '-setting"' . ( ! empty( $div_classes ) ? ' class="' . esc_attr( implode( ' ', $div_classes ) ) . '"' : '' ) . $data_attrs . '>';

		if ( ! empty ( $args['before_field'] ) )
			$html .= $args['before_field'];

		switch ( $args['type'] ) {
			case 'boolean':
				if ( empty( $args['disabled'] ) )
					$html .= '<input type="hidden" name="' . esc_attr( $args['name'] ) . '" value="false" />';

				$html .= '<label><input id="' . esc_attr( $args['html_id'] ) . '" type="checkbox" role="switch" name="' . esc_attr( $args['name'] ) . '" value="true" ' . checked( (bool) $args['value'], true, false ) . ' ' . disabled( empty( $args['disabled'] ), false, false ) . ' />' . esc_html( $args['label'] ) . '</label>';
				break;

			case 'radio':
				if ( empty( $args['options'] ) || ! is_array( $args['options'] ) )
					break;

                $display_type = ! empty( $args['display_type'] ) && in_array( $args['display_type'], [ 'horizontal', 'vertical' ], true ) ? $args['display_type'] : 'horizontal';

				if ( count( $args['options'] ) > 1 )
					$html .= '<div class="' . esc_attr( $this->prefix ) . '-field-group ' . esc_attr( $this->prefix ) . '-radio-group ' . $display_type . '">';

				foreach ( $args['options'] as $key => $name ) {
					$option_id = esc_attr( $args['html_id'] . '-' . $key );
					$html .= '<label for="' . $option_id . '"><input id="' . $option_id . '" type="radio" name="' . esc_attr( $args['name'] ) . '" value="' . esc_attr( $key ) . '" ' . checked( $key, $args['value'], false ) . ' ' . disabled( ! empty( $args['disabled'] ) && in_array ( $key, $args['disabled'], true ), true, false ) . ' />' . esc_html( $name ) . '</label>';
				}

				if ( count( $args['options'] ) > 1 )
					$html .= '</div>';
				break;

			case 'checkbox':
				// possible "empty" value
				if ( $args['value'] === 'empty' )
					$args['value'] = [];

				$html .= '<input type="hidden" name="' . esc_attr( $args['name'] ) . '" value="empty" />';

				if ( empty( $args['options'] ) || ! is_array( $args['options'] ) )
					break;

                $display_type = ! empty( $args['display_type'] ) && in_array( $args['display_type'], [ 'horizontal', 'vertical' ], true ) ? $args['display_type'] : 'horizontal';

				if ( count( $args['options'] ) > 1 )
					$html .= '<div class="' . esc_attr( $this->prefix ) . '-field-group ' . esc_attr( $this->prefix ) . '-checkbox-group ' . $display_type . '">';

				foreach ( $args['options'] as $key => $name ) {
					$option_id = esc_attr( $args['html_id'] . '-' . $key );
					$html .= '<label><input id="' . $option_id . '" type="checkbox" name="' . esc_attr( $args['name'] ) . '[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $args['value'] ), true, false ) . ' ' . disabled( ! empty( $args['disabled'] ) && in_array ( $key, $args['disabled'], true ), true, false ) . ' />' . esc_html( $name ) . '</label>';
				}

				if ( count( $args['options'] ) > 1 )
					$html .= '</div>';
				break;

			case 'select':
				$html .= '<select id="' . esc_attr( $args['html_id'] ) . '" name="' . esc_attr( $args['name'] ) . '" ' . disabled( empty( $args['disabled'] ), false, false ) . '/>';

				foreach ( $args['options'] as $key => $name ) {
					$html .= '<option value="' . esc_attr( $key ) . '" ' . selected( $args['value'], $key, false ) . '>' . esc_html( $name ) . '</option>';
				}

				$html .= '</select>';
				break;

			case 'range':
				$slider_id = esc_attr( $args['html_id'] . '-slider' );
				$range_id = esc_attr( $args['html_id'] . '-range' );
				$html .= '<input id="' . $slider_id . '" type="range" name="' . esc_attr( $args['name'] ) . '" value="' . esc_attr( $args['value'] ) . '" min="' . esc_attr( $args['min'] ) . '" max="' . esc_attr( $args['max'] ) . '" data-range-output="' . $range_id . '" /><output id="' . $range_id . '" class="' . esc_attr( $this->prefix ) . '-range-output" name="' . $range_id . '">' . ( (int) $args['value'] ) . '</output>';
				break;

			case 'number':
				$min_attr = isset( $args['min'] ) ? ' min="' . esc_attr( $args['min'] ) . '"' : '';
				$max_attr = isset( $args['max'] ) ? ' max="' . esc_attr( $args['max'] ) . '"' : '';
				$step_attr = isset( $args['step'] ) ? ' step="' . esc_attr( $args['step'] ) . '"' : '';
				$html .= ( ! empty( $args['prepend'] ) ? wp_kses_post( $args['prepend'] ) : '' );
				$html .= '<input id="' . esc_attr( $args['html_id'] ) . '" type="number" value="' . esc_attr( $args['value'] ) . '" name="' . esc_attr( $args['name'] ) . '"' . $min_attr . $max_attr . $step_attr . ' />';
				$html .= ( ! empty( $args['append'] ) ? wp_kses_post( $args['append'] ) : '' );
				break;

			case 'color':
				$color_value = esc_attr( $args['value'] );
				$color_name = esc_attr( $args['name'] );
				$input_id = esc_attr( $args['html_id'] );
				$input_class = 'iw-color-input';

				if ( ! empty( $args['subclass'] ) ) {
					$input_class .= ' ' . esc_attr( $args['subclass'] );
				}

				$swatch_style = ' style="background-color: ' . $color_value . ';"';

				$html .= '<div class="iw-color-control">';
				// Text input for the hex color value.
				$html .= '<input id="' . $input_id . '" type="text" name="' . $color_name . '" value="' . $color_value . '" class="' . $input_class . '" />';
				// Swatch button to toggle the picker.
				$html .= '<button type="button" class="iw-color-swatch"' . $swatch_style . ' aria-label="' . esc_attr__( 'Open color picker', $this->domain ) . '" aria-expanded="false"></button>';
				// Vanilla-colorful picker (hidden by default).
				$html .= '<div class="iw-color-popover" aria-hidden="true"><hex-color-picker color="' . $color_value . '"></hex-color-picker></div>';
				$html .= '</div>';
				break;

			case 'custom':
				ob_start();
				call_user_func( $args['callback'], $args );
				$html .= ob_get_clean();
				break;

			case 'info':
				$html .= '<span' . ( ! empty( $args['subclass'] ) ? ' class="' . esc_attr( $args['subclass'] ) . '"' : '' ) . '>' . esc_html( $args['text'] ) . '</span>';
				break;

			case 'class':
			case 'input':
			default:
				$empty_disabled = empty( $args['disabled'] );
				$subclass = ! empty( $args['subclass'] ) ? $args['subclass'] : 'text-regular';

				$html .= ( ! empty( $args['prepend'] ) ? wp_kses_post( $args['prepend'] ) : '' );
				$html .= '<input id="' . esc_attr( $args['html_id'] ) . '" class="' . esc_attr( $args['subclass'] ) . '" type="text" value="' . esc_attr( $args['value'] ) . '" name="' . esc_attr( $args['name'] ) . '" ' . disabled( $empty_disabled, false, false ) . '/>';
				$html .= ( ! empty( $args['append'] ) ? wp_kses_post( $args['append'] ) : '' );

				if ( ! $empty_disabled )
					$html .= '<input' . ( $empty_disabled ? '' : ' class="hidden"' ) . ' type="text" value="' . esc_attr( $args['value'] ) . '" name="' . esc_attr( $args['name'] ) . '">';
		}

		if ( ! empty ( $args['after_field'] ) )
			$html .= $args['after_field'];

		if ( ! empty ( $args['description'] ) )
			$html .= '<p class="description">' . $args['description'] . '</p>';

		$html .= '</div>';

		if ( ! empty( $args['return'] ) )
			return $html;
		else
			echo $html;
	}

	/**
	 * Validate settings field.
	 *
	 * @param mixed $value
	 * @param string $type
	 * @param array $args
	 * @return mixed
	 */
	public function validate_field( $value = null, $type = '', $args = [] ) {
		if ( is_null( $value ) )
			return null;

		switch ( $type ) {
			case 'boolean':
				// possible value: 'true' or 'false'
				$value = ( $value === 'true' || $value === true );
				break;

			case 'radio':
				$value = is_array( $value ) ? $args['default'] : sanitize_key( $value );

				// disallow disabled radios
				if ( ! empty( $args['disabled'] ) && in_array( $value, $args['disabled'], true ) )
					$value = $args['default'];
				break;

			case 'checkbox':
				// possible value: 'empty' or array
				if ( $value === 'empty' )
					$value = [];
				else {
					if ( is_array( $value ) && ! empty( $value ) ) {
						$value = array_map( 'sanitize_key', $value );
						$values = [];

						foreach ( $value as $single_value ) {
							if ( array_key_exists( $single_value, $args['options'] ) )
								$values[] = $single_value;
						}

						$value = $values;
					} else
						$value = [];
				}
				break;

			case 'number':
				$value = (int) $value;

				// is value lower than?
				if ( isset( $args['min'] ) && $value < $args['min'] )
					$value = $args['min'];

				// is value greater than?
				if ( isset( $args['max'] ) && $value > $args['max'] )
					$value = $args['max'];
				break;

			case 'color':
				$value = sanitize_text_field( $value );
				if ( ! preg_match( '/^#[a-f0-9]{3,6}$/i', $value ) ) {
					$value = $args['default'] ?? '#000000';
				}
				break;

			case 'info':
				$value = '';
				break;

			case 'custom':
				// do nothing
				break;

			case 'class':
				$value = trim( $value );

				// more than 1 class?
				if ( strpos( $value, ' ' ) !== false ) {
					// get unique valid HTML classes
					$value = array_unique( array_filter( array_map( 'sanitize_html_class', explode( ' ', $value ) ) ) );

					if ( ! empty( $value ) )
						$value = implode( ' ', $value );
					else
						$value = '';
				// single class
				} else
					$value = sanitize_html_class( $value, $args['default'] );
				break;

			case 'input':
			case 'select':
			default:
				$value = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( $value );
				break;
		}

		return stripslashes_deep( $value );
	}

	/**
	 * Validate settings.
	 *
	 * @param array $input
	 * @return array
	 */
	public function validate_settings( $input ) {
		// check capability
		if ( ! current_user_can( 'manage_options' ) )
			return $input;

		// check option page
		if ( empty( $_POST['option_page'] ) )
			return $input;

		// try to get setting name and ID
		foreach ( $this->settings as $id => $setting ) {
			// tabs?
			if ( is_array( $setting['option_name'] ) ) {
				foreach ( $setting['option_name'] as $tab => $option_name ) {
					// found valid setting?
					if ( $option_name === $_POST['option_page'] ) {
						// assign setting ID
						$setting_id = $tab;

						// assign setting name
						$setting_name = $option_name;

						// assign setting key
						$setting_key = $id;

						// already found setting, no need to check the rest
						break 2;
					}
				}
			} else {
				// found valid setting?
				if ( $setting['option_name'] === $_POST['option_page'] ) {
					// assign setting ID and key
					$setting_key = $setting_id = $id;

					// assign setting name
					$setting_name = $setting['option_name'];

					// already found setting, no need to check the rest
					break;
				}
			}
		}

		// check setting id, no need to check $setting_name since it was at the same stage
		if ( empty( $setting_id ) )
			return $input;

		// save settings
		if ( isset( $_POST['save_' . $setting_name] ) ) {
			$input = $this->validate_input_settings( $setting_id, $setting_key, $input );

			add_settings_error( $setting_name, 'settings_saved', __( 'Settings saved.', $this->domain ), 'updated' );
		// reset settings
		} elseif ( isset( $_POST['reset_' . $setting_name] ) ) {
			// get default values
			$input = $this->object->defaults[$setting_id];

			// check custom reset functions
			if ( ! empty( $this->settings[$setting_key]['fields'] ) ) {
				foreach ( $this->settings[$setting_key]['fields'] as $field_id => $field ) {
					// skip invalid tab field if any
					if ( ! empty( $field['tab'] ) && $field['tab'] !== $setting_id )
						continue;

					// custom reset function?
					if ( ! empty( $field['reset'] ) ) {
						// valid function? no need to check "else" since all default values are already set
						if ( $this->callback_function_exists( $field['reset'] ) ) {
							if ( $field['type'] === 'custom' )
								$input = call_user_func( $field['reset'], $input, $field );
							else
								$input[$field_id] = call_user_func( $field['reset'], $input[$field_id], $field );
						}
					}
				}
			}

			add_settings_error( $setting_name, 'settings_restored', __( 'Settings restored to defaults.', $this->domain ), 'updated' );
		}

		do_action( $this->prefix . '_configuration_updated', 'settings', $input );

		return $input;
	}

	/**
	 * Validate input settings.
	 *
	 * @param string $setting_id
	 * @param array $input
	 * @return array
	 */
	public function validate_input_settings( $setting_id, $setting_key, $input ) {
		if ( ! empty( $this->settings[$setting_key]['fields'] ) ) {
			foreach ( $this->settings[$setting_key]['fields'] as $field_id => $field ) {
				// skip saving this field?
				if ( ! empty( $field['skip_saving'] ) )
					continue;

				// skip invalid tab field if any
				if ( ! empty( $field['tab'] ) && $field['tab'] !== $setting_id )
					continue;

				// handle nested input
				if ( ! empty( $field['parent'] ) ) {
					// check if input has parent key
					if ( isset( $input[$field['parent']] ) && is_array( $input[$field['parent']] ) ) {
						// custom validate function?
						if ( ! empty( $field['validate'] ) ) {
							// valid function?
							if ( $this->callback_function_exists( $field['validate'] ) ) {
								if ( $field['type'] === 'custom' )
									$input = call_user_func( $field['validate'], $input, $field );
								else
									$input[$field['parent']][$field_id] = isset( $input[$field['parent']][$field_id] ) ? call_user_func( $field['validate'], $input[$field['parent']][$field_id], $field ) : $this->object->defaults[$setting_id][$field['parent']][$field_id];
							} else
								$input[$field['parent']][$field_id] = $this->object->defaults[$setting_id][$field['parent']][$field_id];
						} else {
							// field data?
							if ( isset( $input[$field['parent']][$field_id] ) ) {
								// make sure default value is available
								if ( ! isset( $field['default'] ) )
									$field['default'] = $this->object->defaults[$setting_id][$field['parent']][$field_id];

								$input[$field['parent']][$field_id] = $this->validate_field( $input[$field['parent']][$field_id], $field['type'], $field );
							} else
								$input[$field['parent']][$field_id] = $this->object->defaults[$setting_id][$field['parent']][$field_id];
						}
					}
				} else {
					// nested?
					if ( $this->nested ) {
						// custom validate function?
						if ( ! empty( $field['validate'] ) ) {
							// valid function?
							if ( $this->callback_function_exists( $field['validate'] ) ) {
								if ( $field['type'] === 'custom' )
									$input = call_user_func( $field['validate'], $input, $field );
								else
									$input[$setting_id][$field_id] = isset( $input[$setting_id][$field_id] ) ? call_user_func( $field['validate'], $input[$setting_id][$field_id], $field ) : $this->object->defaults[$setting_id][$field_id];
							} else
								$input[$setting_id][$field_id] = $this->object->defaults[$setting_id][$field_id];
						} else {
							// field data?
							if ( isset( $input[$setting_id][$field_id] ) ) {
								// make sure default value is available
								if ( ! isset( $field['default'] ) )
									$field['default'] = $this->object->defaults[$setting_id][$field_id];

								$input[$setting_id][$field_id] = $this->validate_field( $input[$setting_id][$field_id], $field['type'], $field );
							} else
								$input[$setting_id][$field_id] = $this->object->defaults[$setting_id][$field_id];
						}
					} else {
						// custom validate function?
						if ( ! empty( $field['validate'] ) ) {
							// valid function?
							if ( $this->callback_function_exists( $field['validate'] ) ) {
								if ( $field['type'] === 'custom' )
									$input = call_user_func( $field['validate'], $input, $field );
								else
									$input[$field_id] = isset( $input[$field_id] ) ? call_user_func( $field['validate'], $input[$field_id], $field ) : $this->object->defaults[$setting_id][$field_id];
							} else
								$input[$field_id] = $this->object->defaults[$setting_id][$field_id];
						} else {
							// field data?
							if ( isset( $input[$field_id] ) ) {
								// make sure default value is available
								if ( ! isset( $field['default'] ) )
									$field['default'] = $this->object->defaults[$setting_id][$field_id];

								$input[$field_id] = $this->validate_field( $input[$field_id], $field['type'], $field );
							} else
								$input[$field_id] = $this->object->defaults[$setting_id][$field_id];
						}
					}
				}

				// update input data
				$this->input_settings = $input;

				// add this field as validated
				$this->validated_settings[] = $field_id;
			}
		}

		return $input;
	}

	/**
	 * Check whether callback is a valid function.
	 *
	 * @param string|array $callback
	 * @return bool
	 */
	public function callback_function_exists( $callback ) {
		// function as array?
		if ( is_array( $callback ) ) {
			list( $object, $function ) = $callback;

			// check function existence
			$function_exists = method_exists( $object, $function );
		// function as string?
		} elseif ( is_string( $callback ) ) {
			// check function existence
			$function_exists = function_exists( $callback );
		} else
			$function_exists = false;

		return $function_exists;
	}

	/**
	 * Get value based on minimum and maximum.
	 *
	 * @param array $data
	 * @param string $setting_name
	 * @param int $default
	 * @param int $min
	 * @param int $max
	 * @return void
	 */
	public function get_int_value( $data, $setting_name, $default, $min, $max ) {
		// check existence of value
		$value = array_key_exists( $setting_name, $data ) ? (int) $data[$setting_name] : $default;

		if ( $value > $max || $value < $min )
			$value = $default;

		return $value;
	}
}
