<?php
/**
 * Album Importer — Import View
 *
 * 服务端只渲染骨架（挂载自检 + 目录选择器 + 结果表结构 + 模板），
 * 目录列表与扫描结果都由 JS 走 Ajax 填充，避免打开设置页就跑一次可能很慢的递归扫描。
 *
 * @package WP_Genius
 * @subpackage Modules/AlbumImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $mount 挂载点自检结果 @see W2P_Album_Importer::get_mount_status() */
$mount = W2P_Album_Importer::get_mount_status();

// 工作室候选：服务端渲染成 <datalist>，既能让用户看到既有词条、又允许直接输入新名字
// （原生控件 + 服务端转义，比在 JS 里拼 options 稳）。实测仅 55 条，量级无压力。
$studio_terms = get_terms(
	array(
		'taxonomy'   => 'photostudio',
		'hide_empty' => false,
	)
);
$studio_terms = is_wp_error( $studio_terms ) ? array() : $studio_terms;
?>
<datalist id="w2p-album-studio-list">
	<?php foreach ( $studio_terms as $studio_term ) : ?>
		<option value="<?php echo esc_attr( $studio_term->name ); ?>"></option>
	<?php endforeach; ?>
</datalist>

<div id="w2p-album-importer" class="w2p-wrapper">

	<!-- 挂载自检（只读） -->
	<div class="w2p-env-card w2p-album-mount-card">
		<div class="w2p-env-card-header">
			<h4 class="w2p-env-card-title">
				<i class="fa-solid fa-plug-circle-check"></i>
				<?php esc_html_e( 'Mount Check', 'wp-genius' ); ?>
			</h4>
			<span class="w2p-env-status <?php echo $mount['readable'] ? 'w2p-env-status--ok' : 'w2p-env-status--fail'; ?>">
				<i class="fa-solid <?php echo $mount['readable'] ? 'fa-check' : 'fa-xmark'; ?>"></i>
				<?php echo $mount['readable'] ? esc_html__( 'Readable', 'wp-genius' ) : esc_html__( 'Not Readable', 'wp-genius' ); ?>
			</span>
		</div>

		<p class="w2p-album-mount-path">
			<code><?php echo esc_html( $mount['root'] ); ?></code>
		</p>

		<?php if ( ! $mount['readable'] ) : ?>
			<p class="w2p-album-mount-error">
				<i class="fa-solid fa-triangle-exclamation"></i>
				<?php echo esc_html( $mount['error'] ); ?>
			</p>
			<p class="w2p-album-mount-hint">
				<?php esc_html_e( 'Declare the read-only mount on the php service in docker-compose.yml, then recreate the php container.', 'wp-genius' ); ?>
			</p>
		<?php else : ?>
			<p class="w2p-album-mount-hint">
				<?php esc_html_e( 'Everything below this path is browsable. Only this path is writable-free: nothing is ever written back.', 'wp-genius' ); ?>
			</p>
		<?php endif; ?>
	</div>

	<!-- 目录选择器 -->
	<div class="w2p-album-picker">
		<div class="w2p-album-picker-bar">
			<label class="w2p-album-path-label" for="w2p-album-path"><?php esc_html_e( 'Folder', 'wp-genius' ); ?></label>
			<input
				type="text"
				id="w2p-album-path"
				class="w2p-album-path-input"
				value="<?php echo esc_attr( $mount['root'] ); ?>"
				spellcheck="false"
				autocomplete="off"
			>
			<button type="button" id="w2p-album-up-btn" class="w2p-btn w2p-btn-secondary" disabled>
				<i class="fa-solid fa-arrow-turn-up"></i> <?php esc_html_e( 'Up', 'wp-genius' ); ?>
			</button>
			<button type="button" id="w2p-album-open-btn" class="w2p-btn w2p-btn-secondary" <?php disabled( ! $mount['readable'] ); ?>>
				<i class="fa-solid fa-folder-open"></i> <?php esc_html_e( 'Open', 'wp-genius' ); ?>
			</button>
			<button type="button" id="w2p-album-scan-btn" class="w2p-btn w2p-btn-primary" <?php disabled( ! $mount['readable'] ); ?>>
				<i class="fa-solid fa-magnifying-glass"></i> <?php esc_html_e( 'Scan this folder', 'wp-genius' ); ?>
			</button>
		</div>

		<div id="w2p-album-browser" class="w2p-album-browser"></div>
		<p id="w2p-album-scope-hint" class="w2p-album-scope-hint"></p>
	</div>

	<!-- 结果工具条 -->
	<div class="w2p-album-toolbar">
		<label class="w2p-album-check-all-label">
			<input type="checkbox" id="w2p-album-check-all" disabled>
			<?php esc_html_e( 'Select all', 'wp-genius' ); ?>
		</label>
		<button type="button" id="w2p-album-select-new-btn" class="w2p-btn w2p-btn-secondary" disabled>
			<i class="fa-solid fa-square-check"></i> <?php esc_html_e( 'Select only new', 'wp-genius' ); ?>
		</button>
		<span id="w2p-album-scan-summary" class="w2p-album-summary"></span>
		<span class="w2p-album-toolbar-spacer"></span>
		<button type="button" id="w2p-album-stop-btn" class="w2p-btn w2p-btn-warning" hidden>
			<i class="fa-solid fa-circle-stop"></i> <?php esc_html_e( 'Stop', 'wp-genius' ); ?>
		</button>
		<button type="button" id="w2p-album-import-btn" class="w2p-btn w2p-btn-success" disabled>
			<i class="fa-solid fa-circle-check"></i> <?php esc_html_e( 'Import Selected', 'wp-genius' ); ?>
		</button>
	</div>

	<!-- 导入进度 -->
	<div id="w2p-album-progress" class="w2p-album-progress" hidden>
		<div class="w2p-album-progress-info">
			<span id="w2p-album-progress-status"></span>
			<span id="w2p-album-progress-count"></span>
		</div>
		<div class="w2p-album-progress-bg">
			<div id="w2p-album-progress-bar" class="w2p-album-progress-fill"></div>
		</div>
	</div>

	<!-- 待导入列表 -->
	<div class="w2p-album-table-wrap">
		<table class="w2p-album-table">
			<thead>
				<tr>
					<th class="w2p-album-col-check"></th>
					<th class="w2p-album-col-dir"><?php esc_html_e( 'Directory', 'wp-genius' ); ?></th>
					<th><?php esc_html_e( 'Title', 'wp-genius' ); ?></th>
					<th><?php esc_html_e( 'Studio', 'wp-genius' ); ?></th>
					<th><?php esc_html_e( 'Model(s)', 'wp-genius' ); ?></th>
					<th class="w2p-album-col-narrow"><?php esc_html_e( 'Serial', 'wp-genius' ); ?></th>
					<th class="w2p-album-col-date"><?php esc_html_e( 'Release', 'wp-genius' ); ?></th>
					<th class="w2p-album-col-count"><?php esc_html_e( 'Images', 'wp-genius' ); ?></th>
					<th class="w2p-album-col-status"><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
					<th class="w2p-album-col-action"></th>
				</tr>
			</thead>
			<tbody id="w2p-album-rows"></tbody>
		</table>
	</div>

	<p id="w2p-album-empty" class="w2p-album-empty">
		<?php esc_html_e( 'Pick a folder above, then click "Scan this folder".', 'wp-genius' ); ?>
	</p>

	<!-- 模板：JS 只克隆节点，不拼 HTML 字符串 -->
	<template id="w2p-album-row-tpl">
		<tr class="w2p-album-row">
			<td class="w2p-album-col-check"><input type="checkbox" class="w2p-album-row-check"></td>
			<td class="w2p-album-cell-dir"><span class="w2p-album-dirname"></span></td>
			<td><input type="text" class="w2p-album-input w2p-album-input-title"></td>
			<td class="w2p-album-cell-studio"></td>
			<td class="w2p-album-cell-models"></td>
			<td><input type="text" class="w2p-album-input w2p-album-input-number"></td>
			<td><input type="text" class="w2p-album-input w2p-album-input-date"></td>
			<td class="w2p-album-cell-count"></td>
			<td class="w2p-album-cell-status"></td>
			<td class="w2p-album-cell-action"></td>
		</tr>
	</template>

	<template id="w2p-album-discard-tpl">
		<button type="button" class="w2p-album-discard-btn" title="<?php esc_attr_e( 'Discard', 'wp-genius' ); ?>">
			<i class="fa-solid fa-trash-can"></i> <span class="w2p-album-discard-label"></span>
		</button>
	</template>

	<template id="w2p-album-badge-tpl">
		<span class="w2p-badge"></span>
	</template>

	<template id="w2p-album-tag-tpl">
		<span class="w2p-badge w2p-album-tag">
			<span class="w2p-album-tag-label"></span>
			<button type="button" class="w2p-album-tag-x" aria-label="<?php esc_attr_e( 'Remove', 'wp-genius' ); ?>">&times;</button>
		</span>
	</template>

	<template id="w2p-album-dir-tpl">
		<button type="button" class="w2p-album-dir-item">
			<i class="fa-solid fa-folder w2p-album-dir-icon"></i>
			<span class="w2p-album-dir-name"></span>
		</button>
	</template>
</div>
