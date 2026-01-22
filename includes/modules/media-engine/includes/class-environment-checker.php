<?php
/**
 * Media Engine Environment Checker
 *
 * 检查所有外部命令和依赖是否可用
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaEngineEnvironmentChecker {

	/**
	 * 获取必需的外部命令配置
	 *
	 * @return array 命令配置数组
	 */
	private static function get_required_commands() {
		return [
			'vips'      => [
				'name'        => 'libvips',
				'check_cmd'   => 'vips --version 2>&1',
				'description' => __( 'High-performance image processing library (primary engine for static images)', 'wp-genius' ),
				'priority'    => 'high',
			],
			'cwebp'     => [
				'name'        => 'cwebp',
				'check_cmd'   => 'cwebp -version 2>&1',
				'description' => __( 'WebP encoder from Google (fallback for static images)', 'wp-genius' ),
				'priority'    => 'medium',
			],
			'gif2webp'  => [
				'name'        => 'gif2webp',
				'check_cmd'   => 'gif2webp -version 2>&1',
				'description' => __( 'Animated GIF to WebP converter (required for GIF processing)', 'wp-genius' ),
				'priority'    => 'high',
			],
			'wp-cli'    => [
				'name'        => 'WP-CLI',
				'check_cmd'   => 'wp --version 2>&1',
				'description' => __( 'WordPress command-line interface (required for batch operations)', 'wp-genius' ),
				'priority'    => 'critical',
			],
		];
	}

	/**
	 * 检查所有环境依赖
	 *
	 * @return array 检查结果
	 */
	public static function check_all() {
		$results = [
			'all_passed'  => true,
			'can_process' => false,
			'commands'    => [],
			'php'         => self::check_php_requirements(),
			'system'      => self::check_system_info(),
		];

		$required_commands = self::get_required_commands();

		foreach ( $required_commands as $key => $config ) {
			$check_result = self::check_command( $key, $config );
			$results['commands'][ $key ] = $check_result;

			if ( ! $check_result['available'] && $config['priority'] === 'critical' ) {
				$results['all_passed'] = false;
			}
		}

		// 判断是否可以处理图片 (至少需要一个静态图引擎 + gif2webp)
		$has_static_engine = $results['commands']['vips']['available'] || $results['commands']['cwebp']['available'];
		$has_gif_engine    = $results['commands']['gif2webp']['available'];
		$has_wpcli         = $results['commands']['wp-cli']['available'];

		$results['can_process'] = $has_static_engine && $has_gif_engine && $has_wpcli;

		return $results;
	}

	/**
	 * 检查单个命令是否可用
	 *
	 * @param string $key    命令键名
	 * @param array  $config 配置信息
	 * @return array 检查结果
	 */
	private static function check_command( $key, $config ) {
		$result = [
			'name'        => $config['name'],
			'description' => $config['description'],
			'priority'    => $config['priority'],
			'available'   => false,
			'version'     => '',
			'path'        => '',
			'error'       => '',
		];

		// 执行检查命令
		$output      = [];
		$return_code = 0;
		exec( $config['check_cmd'], $output, $return_code );

		if ( $return_code === 0 && ! empty( $output ) ) {
			$result['available'] = true;
			$result['version']   = implode( ' ', $output );

			// 获取命令路径
			$which_output = [];
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
	 * 检查 PHP 要求
	 *
	 * @return array PHP 检查结果
	 */
	private static function check_php_requirements() {
		return [
			'version'        => PHP_VERSION,
			'version_ok'     => version_compare( PHP_VERSION, '7.4', '>=' ),
			'exec_enabled'   => function_exists( 'exec' ),
			'proc_open'      => function_exists( 'proc_open' ),
			'memory_limit'   => ini_get( 'memory_limit' ),
			'max_exec_time'  => ini_get( 'max_execution_time' ),
		];
	}

	/**
	 * 获取系统信息
	 *
	 * @return array 系统信息
	 */
	private static function check_system_info() {
		$cpu_cores = 1;
		
		// 尝试获取 CPU 核心数
		if ( function_exists( 'shell_exec' ) ) {
			$cores_output = shell_exec( 'getconf _NPROCESSORS_ONLN 2>&1' );
			if ( is_numeric( trim( $cores_output ) ) ) {
				$cpu_cores = (int) trim( $cores_output );
			}
		}

		return [
			'os'        => PHP_OS,
			'cpu_cores' => $cpu_cores,
			'uname'     => function_exists( 'php_uname' ) ? php_uname() : 'N/A',
		];
	}

	/**
	 * 获取推荐的并发数
	 *
	 * @return int 推荐的并发数
	 */
	public static function get_recommended_workers() {
		$system_info = self::check_system_info();
		$cpu_cores   = $system_info['cpu_cores'];

		// 推荐使用 CPU 核心数的 75%
		return max( 1, (int) floor( $cpu_cores * 0.75 ) );
	}

	/**
	 * 渲染环境检测结果 HTML
	 *
	 * @param array $results 检查结果
	 * @return string HTML 输出
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

			<!-- 系统信息 -->
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

			<!-- PHP 环境 -->
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

			<!-- 外部命令 -->
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

		<style>
		.w2p-environment-status {
			font-size: 14px;
		}
		.w2p-alert {
			padding: 12px 16px;
			border-radius: 6px;
			margin-bottom: 20px;
			display: flex;
			align-items: flex-start;
			gap: 10px;
		}
		.w2p-alert i {
			margin-top: 2px;
		}
		.w2p-alert-success {
			background: #d1fae5;
			color: #065f46;
			border-left: 4px solid #10b981;
		}
		.w2p-alert-error {
			background: #fee2e2;
			color: #991b1b;
			border-left: 4px solid #ef4444;
		}
		.w2p-alert-info {
			background: #dbeafe;
			color: #1e40af;
			border-left: 4px solid #3b82f6;
		}
		.w2p-alert pre {
			background: rgba(0,0,0,0.1);
			padding: 8px 12px;
			border-radius: 4px;
			margin: 8px 0;
		}
		.w2p-env-section {
			margin-bottom: 24px;
		}
		.w2p-env-section h5 {
			margin: 0 0 12px 0;
			font-size: 15px;
			font-weight: 600;
			color: #374151;
		}
		.w2p-env-table {
			width: 100%;
			border-collapse: collapse;
			background: #fff;
			border: 1px solid #e5e7eb;
			border-radius: 6px;
			overflow: hidden;
		}
		.w2p-env-table tr {
			border-bottom: 1px solid #e5e7eb;
		}
		.w2p-env-table tr:last-child {
			border-bottom: none;
		}
		.w2p-env-table td {
			padding: 12px 16px;
			vertical-align: top;
		}
		.w2p-env-table td:first-child {
			width: 40%;
			font-weight: 500;
			background: #f9fafb;
		}
		.w2p-status-badge {
			display: inline-block;
			padding: 4px 10px;
			border-radius: 12px;
			font-size: 12px;
			font-weight: 600;
		}
		.w2p-status-success {
			background: #d1fae5;
			color: #065f46;
		}
		.w2p-status-error {
			background: #fee2e2;
			color: #991b1b;
		}
		.w2p-priority-badge {
			display: inline-block;
			padding: 2px 8px;
			border-radius: 10px;
			font-size: 11px;
			font-weight: 600;
			margin-left: 6px;
		}
		.w2p-priority-critical {
			background: #fecaca;
			color: #991b1b;
		}
		.w2p-priority-high {
			background: #fed7aa;
			color: #92400e;
		}
		</style>
		<?php
		return ob_get_clean();
	}
}
