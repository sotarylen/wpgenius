<?php
/**
 * AI Engine Admin Page
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tabs = array(
	'generate'  => __( 'Generate', 'wp-genius' ),
	'prompts'   => __( 'Prompts', 'wp-genius' ),
	'schedules' => __( 'Schedules', 'wp-genius' ),
	'queue'     => __( 'Queue', 'wp-genius' ),
	'settings'  => __( 'Settings', 'wp-genius' ),
);

$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'generate';
?>
<div class="wrap w2p-ai-engine">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php if ( ! empty( $this->provider_manager ) && ! empty( $this->prompt_engine ) && ! empty( $this->content_queue ) && ! empty( $this->scheduler ) ) : ?>
	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-genius-ai-engine&tab=' . $tab_id ) ); ?>"
				class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="tab-content">
		<?php
		switch ( $active_tab ) {
			case 'generate':
				include __DIR__ . '/tab-generate.php';
				break;
			case 'prompts':
				include __DIR__ . '/tab-prompts.php';
				break;
			case 'schedules':
				include __DIR__ . '/tab-schedules.php';
				break;
			case 'queue':
				include __DIR__ . '/tab-queue.php';
				break;
			case 'settings':
				include __DIR__ . '/tab-settings.php';
				break;
		}
		?>
	</div>
	<?php else : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'AI Engine components not fully loaded. Please check plugin dependencies.', 'wp-genius' ); ?></p>
		</div>
	<?php endif; ?>
</div>
