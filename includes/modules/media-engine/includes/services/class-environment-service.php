<?php
/**
 * Media Engine Environment Checker
 *
 * Checks whether all external commands and dependencies are available
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2P_Media_Environment_Checker {

	/**
	 * Get the required external command configuration
	 *
	 * @return array Command configuration array
	 */
	private static function get_required_commands() {
		return array(
			'vips'     => array(
				'name'        => 'libvips',
				'check_cmd'   => 'vips --version 2>&1',
				'description' => __( 'High-performance image processing library (primary engine for static images)', 'wp-genius' ),
				'priority'    => 'high',
			),
			'cwebp'    => array(
				'name'        => 'cwebp',
				'check_cmd'   => 'cwebp -version 2>&1',
				'description' => __( 'WebP encoder from Google (fallback for static images)', 'wp-genius' ),
				'priority'    => 'medium',
			),
			'gif2webp' => array(
				'name'        => 'gif2webp',
				'check_cmd'   => 'gif2webp -version 2>&1',
				'description' => __( 'Animated GIF to WebP converter (required for GIF processing)', 'wp-genius' ),
				'priority'    => 'high',
			),
			'wp-cli'   => array(
				'name'        => 'WP-CLI',
				'check_cmd'   => 'wp --version 2>&1',
				'description' => __( 'WordPress command-line interface (required for batch operations)', 'wp-genius' ),
				'priority'    => 'critical',
			),
		);
	}

	/**
	 * Check all environment dependencies
	 *
	 * @return array Check result
	 */
	public static function check_all() {
		$results = array(
			'all_passed'  => true,
			'can_process' => false,
			'commands'    => array(),
			'php'         => self::check_php_requirements(),
			'system'      => self::check_system_info(),
		);

		$required_commands = self::get_required_commands();

		foreach ( $required_commands as $key => $config ) {
			$check_result                = self::check_command( $key, $config );
			$results['commands'][ $key ] = $check_result;

			if ( ! $check_result['available'] && $config['priority'] === 'critical' ) {
				$results['all_passed'] = false;
			}
		}

		// Determine whether images can be processed (needs at least one static image engine + gif2webp)
		$has_static_engine = $results['commands']['vips']['available'] || $results['commands']['cwebp']['available'];
		$has_gif_engine    = $results['commands']['gif2webp']['available'];
		$has_wpcli         = $results['commands']['wp-cli']['available'];

		$results['can_process'] = $has_static_engine && $has_gif_engine && $has_wpcli;

		return $results;
	}

	/**
	 * Check whether a single command is available
	 *
	 * @param string $key    Command key name
	 * @param array  $config Configuration info
	 * @return array Check result
	 */
	private static function check_command( $key, $config ) {
		$result = array(
			'name'        => $config['name'],
			'description' => $config['description'],
			'priority'    => $config['priority'],
			'available'   => false,
			'version'     => '',
			'path'        => '',
			'error'       => '',
		);

		// Run the check command
		$output      = array();
		$return_code = 0;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Static detection command (vips --version etc.), not user input.
		exec( $config['check_cmd'], $output, $return_code );

		if ( $return_code === 0 && ! empty( $output ) ) {
			$result['available'] = true;
			$result['version']   = implode( ' ', $output );

			// Get the command path
			$which_output = array();
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Static detection command (vips --version etc.), not user input.
			exec( "which $key 2>&1", $which_output );
			if ( ! empty( $which_output[0] ) ) {
				$result['path'] = $which_output[0];
			}
		} else {
			$result['error'] = ! empty( $output ) ? implode( ' ', $output ) : __( 'Command not found', 'wp-genius' );
		}

		return $result;
	}

	/**
	 * Check PHP requirements
	 *
	 * @return array PHP check result
	 */
	private static function check_php_requirements() {
		return array(
			'version'       => PHP_VERSION,
			'version_ok'    => version_compare( PHP_VERSION, '7.4', '>=' ),
			'exec_enabled'  => function_exists( 'exec' ),
			'proc_open'     => function_exists( 'proc_open' ),
			'memory_limit'  => ini_get( 'memory_limit' ),
			'max_exec_time' => ini_get( 'max_execution_time' ),
		);
	}

	/**
	 * Get system information
	 *
	 * @return array System information
	 */
	private static function check_system_info() {
		$cpu_cores = 1;

		// Try to get the number of CPU cores
		if ( function_exists( 'shell_exec' ) ) {
			$cores_output = shell_exec( 'getconf _NPROCESSORS_ONLN 2>&1' );
			if ( is_numeric( trim( $cores_output ) ) ) {
				$cpu_cores = (int) trim( $cores_output );
			}
		}

		return array(
			'os'        => PHP_OS,
			'cpu_cores' => $cpu_cores,
			'uname'     => function_exists( 'php_uname' ) ? php_uname() : 'N/A',
		);
	}

	/**
	 * Get the recommended number of concurrent workers
	 *
	 * @return int Recommended number of concurrent workers
	 */
	public static function get_recommended_workers() {
		$system_info = self::check_system_info();
		$cpu_cores   = $system_info['cpu_cores'];

		// Recommended: 75% of the CPU core count
		return max( 1, (int) floor( $cpu_cores * 0.75 ) );
	}

	/**
	 * Render environment check result HTML
	 *
	 * @param array $results Check result
	 * @return string HTML output
	 */
	public static function render_status_html( $results = null ) {
		if ( $results === null ) {
			$results = self::check_all();
		}

		ob_start();
		?>
		<div class="w2p-environment-status">
			<?php if ( $results['can_process'] ) : ?>
				<div class="w2p-alert w2p-alert-success">
					<i class="fa-solid fa-circle-check"></i>
					<strong><?php esc_html_e( 'Environment Ready', 'wp-genius' ); ?></strong>
					<?php esc_html_e( 'All required dependencies are available. Media processing is enabled.', 'wp-genius' ); ?>
				</div>
			<?php else : ?>
				<div class="w2p-alert w2p-alert-error">
					<i class="fa-solid fa-triangle-exclamation"></i>
					<strong><?php esc_html_e( 'Environment Issues Detected', 'wp-genius' ); ?></strong>
					<?php esc_html_e( 'Some required dependencies are missing. Please install them to enable media processing.', 'wp-genius' ); ?>
				</div>
			<?php endif; ?>

			<!-- System Information -->
			<div class="w2p-env-section">
				<h5><i class="fa-solid fa-server"></i> <?php esc_html_e( 'System Information', 'wp-genius' ); ?></h5>
				<table class="w2p-env-table">
					<tr>
						<td><?php esc_html_e( 'Operating System', 'wp-genius' ); ?></td>
						<td><code><?php echo esc_html( $results['system']['os'] ); ?></code></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'CPU Cores', 'wp-genius' ); ?></td>
						<td><code><?php echo esc_html( $results['system']['cpu_cores'] ); ?></code></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Recommended Workers', 'wp-genius' ); ?></td>
						<td><code><?php echo esc_html( self::get_recommended_workers() ); ?></code></td>
					</tr>
				</table>
			</div>

			<!-- PHP Environment -->
			<div class="w2p-env-section">
				<h5><i class="fa-brands fa-php"></i> <?php esc_html_e( 'PHP Environment', 'wp-genius' ); ?></h5>
				<table class="w2p-env-table">
					<tr>
						<td><?php esc_html_e( 'PHP Version', 'wp-genius' ); ?></td>
						<td>
							<code><?php echo esc_html( $results['php']['version'] ); ?></code>
							<?php if ( $results['php']['version_ok'] ) : ?>
								<span class="w2p-status-badge w2p-status-success"><?php esc_html_e( 'OK', 'wp-genius' ); ?></span>
							<?php else : ?>
								<span class="w2p-status-badge w2p-status-error"><?php esc_html_e( 'Requires 7.4+', 'wp-genius' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'exec() Function', 'wp-genius' ); ?></td>
						<td>
							<?php if ( $results['php']['exec_enabled'] ) : ?>
								<span class="w2p-status-badge w2p-status-success"><i class="fa-solid fa-check"></i> <?php esc_html_e( 'Enabled', 'wp-genius' ); ?></span>
							<?php else : ?>
								<span class="w2p-status-badge w2p-status-error"><i class="fa-solid fa-xmark"></i> <?php esc_html_e( 'Disabled', 'wp-genius' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'proc_open() Function', 'wp-genius' ); ?></td>
						<td>
							<?php if ( $results['php']['proc_open'] ) : ?>
								<span class="w2p-status-badge w2p-status-success"><i class="fa-solid fa-check"></i> <?php esc_html_e( 'Enabled', 'wp-genius' ); ?></span>
							<?php else : ?>
								<span class="w2p-status-badge w2p-status-error"><i class="fa-solid fa-xmark"></i> <?php esc_html_e( 'Disabled', 'wp-genius' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Memory Limit', 'wp-genius' ); ?></td>
						<td><code><?php echo esc_html( $results['php']['memory_limit'] ); ?></code></td>
					</tr>
				</table>
			</div>

			<!-- External Commands -->
			<div class="w2p-env-section">
				<h5><i class="fa-solid fa-terminal"></i> <?php esc_html_e( 'External Commands', 'wp-genius' ); ?></h5>
				<table class="w2p-env-table">
					<?php foreach ( $results['commands'] as $key => $cmd ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $cmd['name'] ); ?></strong>
								<?php if ( $cmd['priority'] === 'critical' ) : ?>
									<span class="w2p-priority-badge w2p-priority-critical"><?php esc_html_e( 'Critical', 'wp-genius' ); ?></span>
								<?php elseif ( $cmd['priority'] === 'high' ) : ?>
									<span class="w2p-priority-badge w2p-priority-high"><?php esc_html_e( 'Required', 'wp-genius' ); ?></span>
								<?php endif; ?>
								<br>
								<small class="description"><?php echo esc_html( $cmd['description'] ); ?></small>
							</td>
							<td>
								<?php if ( $cmd['available'] ) : ?>
									<span class="w2p-status-badge w2p-status-success">
										<i class="fa-solid fa-check"></i> <?php esc_html_e( 'Available', 'wp-genius' ); ?>
									</span>
									<br>
									<small><code><?php echo esc_html( $cmd['path'] ); ?></code></small>
									<br>
									<small class="description"><?php echo esc_html( $cmd['version'] ); ?></small>
								<?php else : ?>
									<span class="w2p-status-badge w2p-status-error">
										<i class="fa-solid fa-xmark"></i> <?php esc_html_e( 'Not Found', 'wp-genius' ); ?>
									</span>
									<br>
									<small class="description"><?php echo esc_html( $cmd['error'] ); ?></small>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>

			<?php if ( ! $results['can_process'] ) : ?>
				<div class="w2p-alert w2p-alert-info">
					<i class="fa-solid fa-circle-info"></i>
					<strong><?php esc_html_e( 'Installation Guide', 'wp-genius' ); ?></strong>
					<p><?php esc_html_e( 'To install missing dependencies on macOS:', 'wp-genius' ); ?></p>
					<pre><code>brew install vips webp wp-cli</code></pre>
					<p><?php esc_html_e( 'On Ubuntu/Debian:', 'wp-genius' ); ?></p>
					<pre><code>sudo apt-get install libvips-tools webp</code></pre>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}

// Backward compatibility alias.
if ( ! class_exists( 'MediaEngineEnvironmentChecker', false ) ) {
	class_alias( 'W2P_Media_Environment_Checker', 'MediaEngineEnvironmentChecker' );
}
