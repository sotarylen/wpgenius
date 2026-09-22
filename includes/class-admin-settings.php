<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Admin settings class
 *
 * Responsible for initializing the plugin settings page built on the CSF framework and loading global configuration options.
 * Specifically handles settings-data protection for hidden/disabled modules, preventing loss of existing configuration data when a module is disabled.
 *
 * @package WP_Genius
 */
class W2P_Admin_Settings {
	protected $loader;

	public function __construct( $loader ) {
		$this->loader = $loader;
		// No more manual menu registration. CSF handles it.
		$this->register_settings();
	}

	/**
	 * Register global settings (logic from the former includes/admin/options.php merged here)
	 */
	public function register_settings() {
		// Global Settings Prefix
		$prefix = 'w2p_settings';

		// 1. Initialize Framework
		// CSF is already required during plugin file loading (is_admin guard); this is only a defensive check.
		// Note: do not defer require to this point - CSF registers its init hook (setup) when the file is loaded,
		// a deferred require would make setup miss init and fail to register the settings menu.
		if ( ! class_exists( 'CSF' ) ) {
			$csf_path = plugin_dir_path( WP_GENIUS_FILE ) . 'includes/csf/codestar-framework.php';
			if ( file_exists( $csf_path ) ) {
				require_once $csf_path;
			}
		}

		if ( class_exists( 'CSF' ) ) {
			CSF::createOptions(
				$prefix,
				array(
					'menu_title'      => 'WP Genius',
					'menu_slug'       => 'wp-genius-settings',
					'menu_type'       => 'submenu',
					'menu_parent'     => 'tools.php',
					'framework_title' => 'WP Genius',
					'show_sub_menu'   => false,
					'theme'           => 'light',
				)
			);

			// Use the injected loader instance instead of creating a new one
			$this->loader->discover( true );
			$modules = $this->loader->get_available_modules();

			// 2. Section 0: Environment Check (System Status & Module Prerequisites)
			CSF::createSection(
				$prefix,
				array(
					'title'  => __( 'Environment Check', 'wp-genius' ),
					'icon'   => 'fa-solid fa-stethoscope',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => $this->render_environment_check_content( $modules ),
						),
					),
				)
			);

			// 3. Section 1: Module Management
			$module_fields = array();

			if ( ! empty( $modules ) ) {
				foreach ( $modules as $id => $module ) {
					$name = method_exists( $module, 'name' ) ? $module->name() : ucfirst( $id );
					$desc = method_exists( $module, 'description' ) ? $module->description() : '';

					$req_error = null;
					if ( method_exists( $module, 'check_requirements' ) ) {
						$req = $module->check_requirements();
						if ( is_wp_error( $req ) ) {
							$req_error = $req->get_error_message();
						}
					}

					$field_config = array(
						'id'       => 'module_' . $id,
						'type'     => 'switcher',
						'title'    => $name,
						'subtitle' => $desc,
						'default'  => false,
					);

					if ( ! empty( $req_error ) ) {
						$field_config['subtitle']  .= '<div class="w2p-req-badge"><i class="fa-solid fa-triangle-exclamation"></i> ' . esc_html__( 'Dependencies not met. Check the Environment Check tab for details.', 'wp-genius' ) . '</div>';
						$field_config['attributes'] = array( 'disabled' => 'disabled' );
					}

					$module_fields[] = $field_config;
				}
			} else {
				$module_fields[] = array(
					'type'    => 'content',
					'content' => __( 'No modules found or Loader not ready.', 'wp-genius' ),
				);
			}

			CSF::createSection(
				$prefix,
				array(
					'title'  => __( 'Module Management', 'wp-genius' ),
					'icon'   => 'fa fa-th-large',
					'fields' => $module_fields,
				)
			);

			// 3. Auto-load module-specific options
			if ( defined( 'WP_GENIUS_FILE' ) ) {
				$modules_root = plugin_dir_path( WP_GENIUS_FILE ) . 'includes/modules';

				if ( is_dir( $modules_root ) ) {
					foreach ( $modules as $id => $module_instance ) {
						// Check if enabled and requirements met
						if ( $this->loader->is_enabled( $id ) ) {
							if ( method_exists( $module_instance, 'check_requirements' ) && is_wp_error( $module_instance->check_requirements() ) ) {
								continue;
							}

							$options_file = $modules_root . '/' . $id . '/options.php';

							if ( file_exists( $options_file ) ) {
								$section_config = include $options_file;

								if ( is_array( $section_config ) && isset( $section_config['module_id'] ) ) {
									CSF::createSection( $prefix, $section_config );
								}
							}
						}
					}
				}
			}
		}

		add_filter( 'pre_update_option_w2p_settings', array( $this, 'preserve_hidden_module_settings' ), 10, 2 );
	}

	/**
	 * Preserve Hidden Module Settings and Validate Requirements
	 *
	 * Compares new settings with old settings. If a key existing in old settings is missing
	 * in new settings (and wasn't explicitly unset), we assume it's from a disabled (hidden) module.
	 *
	 * @param array $new_value New value.
	 * @param array $old_value Old value.
	 * @return array Merged value.
	 */
	public function preserve_hidden_module_settings( $new_value, $old_value ) {
		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return $new_value;
		}

		// Validate module requirements when enabling modules.
		$modules = $this->loader->get_available_modules();
		$errors  = array();

		foreach ( $modules as $id => $module ) {
			$module_key = 'module_' . $id;
			if ( ! empty( $new_value[ $module_key ] ) && method_exists( $module, 'check_requirements' ) ) {
				$req = $module->check_requirements();
				if ( is_wp_error( $req ) ) {
					// Disallow activation and revert to disabled
					$new_value[ $module_key ] = false;
					$errors[]                 = $req->get_error_message();
				}
			}
		}

		foreach ( $old_value as $key => $value ) {
			// If key existed in old but missing in new
			if ( ! array_key_exists( $key, $new_value ) ) {
				$new_value[ $key ] = $value;
			}
		}

		return $new_value;
	}

	/**
	 * Render Environment Check Section Content
	 *
	 * Aggregates prerequisite dependencies for Novel Manager, Media Engine, and core server environment.
	 *
	 * @param array $modules Discovered modules list.
	 * @return string HTML output.
	 */
	private function render_environment_check_content( $modules ) {
		ob_start();
		?>
		<div class="w2p-env-dashboard">
			<!-- 1. System Environment Card -->
			<div class="w2p-env-card">
				<div class="w2p-env-card-header">
					<h4 class="w2p-env-card-title">
						<i class="fa-solid fa-server"></i>
						<?php esc_html_e( 'System Environment', 'wp-genius' ); ?>
					</h4>
				</div>
				<table class="w2p-env-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Component', 'wp-genius' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
							<th><?php esc_html_e( 'Current Value', 'wp-genius' ); ?></th>
							<th><?php esc_html_e( 'Details', 'wp-genius' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						// PHP Version
						$php_ok = version_compare( PHP_VERSION, '7.4', '>=' );
						?>
						<tr>
							<td><strong>PHP Version</strong></td>
							<td>
								<span class="w2p-env-status <?php echo $php_ok ? 'w2p-env-status--ok' : 'w2p-env-status--fail'; ?>">
									<i class="fa-solid <?php echo $php_ok ? 'fa-check' : 'fa-xmark'; ?>"></i>
									<?php echo $php_ok ? esc_html__( 'Passed', 'wp-genius' ) : esc_html__( 'Failed', 'wp-genius' ); ?>
								</span>
							</td>
							<td><?php echo esc_html( PHP_VERSION ); ?></td>
							<td><?php esc_html_e( 'PHP 7.4 or higher is recommended.', 'wp-genius' ); ?></td>
						</tr>
						<?php
						// WordPress Version
						global $wp_version;
						$wp_ok = version_compare( $wp_version, '5.8', '>=' );
						?>
						<tr>
							<td><strong>WordPress</strong></td>
							<td>
								<span class="w2p-env-status <?php echo $wp_ok ? 'w2p-env-status--ok' : 'w2p-env-status--fail'; ?>">
									<i class="fa-solid <?php echo $wp_ok ? 'fa-check' : 'fa-xmark'; ?>"></i>
									<?php echo $wp_ok ? esc_html__( 'Passed', 'wp-genius' ) : esc_html__( 'Failed', 'wp-genius' ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $wp_version ); ?></td>
							<td><?php esc_html_e( 'WordPress 5.8+ required for block and media hooks.', 'wp-genius' ); ?></td>
						</tr>
						<?php
						// Uploads Directory Writable
						$upload_dir = wp_upload_dir();
						$upload_ok  = wp_is_writable( $upload_dir['basedir'] );
						?>
						<tr>
							<td><strong>Upload Directory</strong></td>
							<td>
								<span class="w2p-env-status <?php echo $upload_ok ? 'w2p-env-status--ok' : 'w2p-env-status--fail'; ?>">
									<i class="fa-solid <?php echo $upload_ok ? 'fa-check' : 'fa-xmark'; ?>"></i>
									<?php echo $upload_ok ? esc_html__( 'Writable', 'wp-genius' ) : esc_html__( 'Not Writable', 'wp-genius' ); ?>
								</span>
							</td>
							<td><?php echo esc_html( wp_basename( $upload_dir['basedir'] ) ); ?></td>
							<td><?php esc_html_e( 'Required for temporary import processing and media uploads.', 'wp-genius' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- 2. Novel Manager Prerequisites Card -->
			<div class="w2p-env-card">
				<div class="w2p-env-card-header">
					<h4 class="w2p-env-card-title">
						<i class="fa-solid fa-book"></i>
						<?php esc_html_e( 'Novel Manager Prerequisites', 'wp-genius' ); ?>
					</h4>
					<?php
					$novel_status = class_exists( 'W2P_NovelManagerModule' ) && method_exists( 'W2P_NovelManagerModule', 'get_requirements_status' )
						? W2P_NovelManagerModule::get_requirements_status()
						: null;

					$all_novel_ok = ! empty( $novel_status['all_passed'] );
					?>
					<span class="w2p-env-status <?php echo $all_novel_ok ? 'w2p-env-status--ok' : 'w2p-env-status--fail'; ?>">
						<i class="fa-solid <?php echo $all_novel_ok ? 'fa-check' : 'fa-triangle-exclamation'; ?>"></i>
						<?php echo $all_novel_ok ? esc_html__( 'Ready to Enable', 'wp-genius' ) : esc_html__( 'Action Required', 'wp-genius' ); ?>
					</span>
				</div>
				<table class="w2p-env-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Requirement', 'wp-genius' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
							<th><?php esc_html_e( 'Description', 'wp-genius' ); ?></th>
							<th><?php esc_html_e( 'Action', 'wp-genius' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! empty( $novel_status['items'] ) ) : ?>
							<?php foreach ( $novel_status['items'] as $item ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $item['name'] ); ?></strong></td>
									<td>
										<span class="w2p-env-status <?php echo ! empty( $item['status'] ) ? 'w2p-env-status--ok' : 'w2p-env-status--fail'; ?>">
											<i class="fa-solid <?php echo ! empty( $item['status'] ) ? 'fa-check' : 'fa-xmark'; ?>"></i>
											<?php echo ! empty( $item['status'] ) ? esc_html__( 'Detected', 'wp-genius' ) : esc_html__( 'Missing', 'wp-genius' ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $item['description'] ); ?></td>
									<td>
										<?php if ( ! empty( $item['action_url'] ) && ! empty( $item['action_text'] ) ) : ?>
											<a href="<?php echo esc_url( $item['action_url'] ); ?>" class="w2p-env-action-link" target="_blank">
												<i class="fa-solid fa-arrow-up-right-from-square"></i>
												<?php echo esc_html( $item['action_text'] ); ?>
											</a>
										<?php else : ?>
											<span>-</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<!-- 3. Media Engine Dependencies Card (Reusing existing checker) -->
			<?php
			$media_checker_file = plugin_dir_path( WP_GENIUS_FILE ) . 'includes/modules/media-engine/includes/services/class-environment-service.php';
			if ( file_exists( $media_checker_file ) ) {
				require_once $media_checker_file;
			}

			if ( class_exists( 'W2P_Media_Environment_Checker' ) ) :
				$media_env = W2P_Media_Environment_Checker::check_all();
				$media_ok  = ! empty( $media_env['can_process'] );
				?>
				<div class="w2p-env-card">
					<div class="w2p-env-card-header">
						<h4 class="w2p-env-card-title">
							<i class="fa-solid fa-photo-film"></i>
							<?php esc_html_e( 'Media Engine Tools', 'wp-genius' ); ?>
						</h4>
						<span class="w2p-env-status <?php echo $media_ok ? 'w2p-env-status--ok' : 'w2p-env-status--warn'; ?>">
							<i class="fa-solid <?php echo $media_ok ? 'fa-check' : 'fa-circle-info'; ?>"></i>
							<?php echo $media_ok ? esc_html__( 'Ready for Processing', 'wp-genius' ) : esc_html__( 'Limited Engines Available', 'wp-genius' ); ?>
						</span>
					</div>
					<table class="w2p-env-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Tool / Command', 'wp-genius' ); ?></th>
								<th><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
								<th><?php esc_html_e( 'Version / Info', 'wp-genius' ); ?></th>
								<th><?php esc_html_e( 'Role', 'wp-genius' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! empty( $media_env['commands'] ) ) : ?>
								<?php foreach ( $media_env['commands'] as $cmd_key => $cmd_info ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $cmd_info['name'] ); ?></strong></td>
										<td>
											<span class="w2p-env-status <?php echo ! empty( $cmd_info['available'] ) ? 'w2p-env-status--ok' : 'w2p-env-status--warn'; ?>">
												<i class="fa-solid <?php echo ! empty( $cmd_info['available'] ) ? 'fa-check' : 'fa-xmark'; ?>"></i>
												<?php echo ! empty( $cmd_info['available'] ) ? esc_html__( 'Available', 'wp-genius' ) : esc_html__( 'Unavailable', 'wp-genius' ); ?>
											</span>
										</td>
										<td><?php echo esc_html( ! empty( $cmd_info['version'] ) ? $cmd_info['version'] : '-' ); ?></td>
										<td><?php echo esc_html( ! empty( $cmd_info['description'] ) ? $cmd_info['description'] : '-' ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}

