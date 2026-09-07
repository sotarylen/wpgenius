<?php
/**
 * Novel Manager — Tab Upload & Import View
 *
 * 文档导入与两阶段预览微调视图
 *
 * @package WP_Genius
 * @subpackage Modules/NovelManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_task = W2P_Novel_Importer::get_active_task();

// 获取默认分类 general-novels
$default_cat_term = get_term_by( 'slug', 'general-novels', 'category' );
$default_cat_id   = ( $default_cat_term && ! is_wp_error( $default_cat_term ) ) ? $default_cat_term->term_id : 0;

// 获取 tag-to-posttype-relationship 为“小说（Novels）”的专用标签
$novel_tags = get_terms(
	array(
		'taxonomy'   => 'post_tag',
		'hide_empty' => false,
		'meta_query' => array(
			array(
				'key'     => 'tag-to-posttype-relationship',
				'value'   => '小说（Novels）',
				'compare' => '=',
			),
		),
	)
);
?>

<div id="w2p-tab-novel-upload" class="w2p-wrapper">

	<!-- 断点续传任务提示卡片（检测到中断任务时展示） -->
	<div id="w2p-active-task-alert" class="w2p-active-task-card" style="<?php echo empty( $active_task ) ? 'display:none;' : ''; ?>">
		<div class="w2p-active-task-icon">
			<i class="fa-solid fa-clock-rotate-left"></i>
		</div>
		<div class="w2p-active-task-content">
			<div class="w2p-active-task-title">
				<strong><?php esc_html_e( 'Unfinished Import Task Detected', 'wp-genius' ); ?></strong>
				<span class="w2p-active-task-time" id="w2p-task-updated-at">
					<?php echo ! empty( $active_task['updated_at'] ) ? esc_html( $active_task['updated_at'] ) : ''; ?>
				</span>
			</div>
			<p class="w2p-active-task-desc">
				<?php
				$task_novel_title = ! empty( $active_task['novel_title'] ) ? $active_task['novel_title'] : '';
				$task_imported    = ! empty( $active_task['imported_count'] ) ? intval( $active_task['imported_count'] ) : 0;
				$task_total       = ! empty( $active_task['total_chapters'] ) ? intval( $active_task['total_chapters'] ) : 0;
				$task_pct         = $task_total > 0 ? round( ( $task_imported / $task_total ) * 100, 1 ) : 0;
				?>
				<?php esc_html_e( 'Novel:', 'wp-genius' ); ?> <strong id="w2p-task-novel-name">《<?php echo esc_html( $task_novel_title ); ?>》</strong> 
				| <?php esc_html_e( 'Progress:', 'wp-genius' ); ?> <span id="w2p-task-progress-text"><strong><?php echo esc_html( number_format( $task_imported ) ); ?></strong> / <?php echo esc_html( number_format( $task_total ) ); ?> <?php esc_html_e( 'Chapters', 'wp-genius' ); ?> (<?php echo esc_html( $task_pct ); ?>%)</span>
			</p>
			<div class="w2p-active-task-actions">
				<button type="button" id="w2p-resume-task-btn" class="w2p-btn w2p-btn-primary w2p-btn-sm">
					<i class="fa-solid fa-play"></i> <?php esc_html_e( 'Resume Import Now', 'wp-genius' ); ?>
				</button>
				<button type="button" id="w2p-discard-task-btn" class="w2p-btn w2p-btn-secondary w2p-btn-sm">
					<i class="fa-solid fa-trash-can"></i> <?php esc_html_e( 'Discard Task', 'wp-genius' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- 第一步：文件上传与小说元数据配置（单列清晰流式排版） -->
	<div id="w2p-novel-step-upload" class="w2p-section">
		<div class="w2p-section-body">
			<div id="w2p-novel-upload-panel" class="w2p-single-column-form">
				<input type="hidden" id="w2p_current_task_id" value="">

				<!-- 导入目标模式选择卡片导航 (复用 Media Engine 经典工作流步骤设计系统) -->
				<div class="w2p-workflow-steps-nav w2p-target-steps-nav">
					<button type="button" class="w2p-workflow-step-btn active" data-target="new">
						<span class="w2p-step-num">1</span>
						<span class="w2p-step-info">
							<span class="w2p-step-title"><?php esc_html_e( 'Create New Novel', 'wp-genius' ); ?></span>
							<span class="w2p-step-desc"><?php esc_html_e( 'Create a brand new novel and import chapters', 'wp-genius' ); ?></span>
						</span>
					</button>
					<button type="button" class="w2p-workflow-step-btn" data-target="existing">
						<span class="w2p-step-num">2</span>
						<span class="w2p-step-info">
							<span class="w2p-step-title"><?php esc_html_e( 'Import to Existing Novel', 'wp-genius' ); ?></span>
							<span class="w2p-step-desc"><?php esc_html_e( 'Append missing chapters or truncate & re-import', 'wp-genius' ); ?></span>
						</span>
					</button>
				</div>

				<!-- 现有书籍专属：书籍选择与导入策略容器 (初始隐藏) -->
				<div id="w2p-existing-novel-wrap" class="w2p-existing-novel-wrap">
					<input type="hidden" id="w2p_selected_existing_novel_id" value="">

					<!-- 1. 搜索定位已有书籍 -->
					<div class="w2p-form-row" id="w2p-existing-search-row">
						<div class="csf-title">
							<label for="w2p-upload-search-novel-input"><?php esc_html_e( 'Select Target Novel', 'wp-genius' ); ?> <span class="w2p-required">*</span></label>
						</div>
						<div class="w2p-form-control">
							<div class="w2p-fix-search-input-wrap">
								<i class="fa-solid fa-magnifying-glass w2p-fix-search-icon"></i>
								<input type="text" id="w2p-upload-search-novel-input" class="w2p-input-full" placeholder="<?php esc_attr_e( 'Enter novel title keyword or exact Novel ID (e.g. 102)...', 'wp-genius' ); ?>" autocomplete="off">
								<button type="button" id="w2p-upload-search-clear-btn" class="w2p-fix-clear-btn" title="<?php esc_attr_e( 'Clear', 'wp-genius' ); ?>">&times;</button>
								<div id="w2p-upload-search-results" class="w2p-search-dropdown-results w2p-hidden"></div>
							</div>
						</div>
					</div>

					<!-- 2. 已选书籍展示卡片 (初始隐藏，选定后展示) -->
					<div class="w2p-form-row w2p-hidden" id="w2p-selected-novel-card-row">
						<div class="csf-title">
							<label><?php esc_html_e( 'Selected Novel', 'wp-genius' ); ?></label>
						</div>
						<div class="w2p-form-control">
							<div id="w2p-selected-novel-card" class="w2p-selected-novel-card">
								<div class="w2p-selected-novel-thumb" id="w2p-selected-novel-thumb">
									<i class="fa-solid fa-book"></i>
								</div>
								<div class="w2p-selected-novel-details">
									<div class="w2p-selected-novel-title-wrap">
										<h4 id="w2p-selected-novel-title"></h4>
										<span class="w2p-badge w2p-badge-secondary" id="w2p-selected-novel-id-badge"></span>
									</div>
									<div class="w2p-selected-novel-meta">
										<span><i class="fa-solid fa-user"></i> <span id="w2p-selected-novel-author">-</span></span>
										<span><i class="fa-solid fa-list-ol"></i> <strong id="w2p-selected-novel-chapters">0</strong> <?php esc_html_e( 'Chapters', 'wp-genius' ); ?></span>
										<span><i class="fa-solid fa-bookmark"></i> <?php esc_html_e( 'Last Index:', 'wp-genius' ); ?> <strong id="w2p-selected-novel-last-index">-</strong></span>
									</div>
								</div>
								<div class="w2p-selected-novel-action">
									<button type="button" id="w2p-change-target-novel-btn" class="w2p-btn w2p-btn-secondary w2p-btn-sm">
										<i class="fa-solid fa-arrows-rotate"></i> <?php esc_html_e( 'Change', 'wp-genius' ); ?>
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- 3. 操作类型三选一 (补充章节 / 清空重导 / 管理现有章节) -->
					<div class="w2p-form-row w2p-hidden" id="w2p-strategy-row">
						<div class="csf-title">
							<label><?php esc_html_e( 'Action', 'wp-genius' ); ?></label>
						</div>
						<div class="w2p-form-control">
							<div class="w2p-strategy-cards">
								<label class="w2p-strategy-card active" data-strategy="append">
									<input type="radio" name="import_strategy" value="append" checked class="w2p-hidden-radio">
									<div class="w2p-strategy-card-inner">
										<div class="w2p-strategy-icon"><i class="fa-solid fa-circle-plus"></i></div>
										<div class="w2p-strategy-text">
											<strong><?php esc_html_e( 'Append Missing Chapters', 'wp-genius' ); ?></strong>
											<span><?php esc_html_e( 'Append new chapters after existing ones', 'wp-genius' ); ?></span>
										</div>
									</div>
								</label>
								<label class="w2p-strategy-card w2p-strategy-danger" data-strategy="truncate">
									<input type="radio" name="import_strategy" value="truncate" class="w2p-hidden-radio">
									<div class="w2p-strategy-card-inner">
										<div class="w2p-strategy-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
										<div class="w2p-strategy-text">
											<strong><?php esc_html_e( 'Truncate & Re-import', 'wp-genius' ); ?></strong>
											<span><?php esc_html_e( 'Delete all chapters then re-import from document', 'wp-genius' ); ?></span>
										</div>
									</div>
								</label>
								<label class="w2p-strategy-card" data-strategy="manage">
									<input type="radio" name="import_strategy" value="manage" class="w2p-hidden-radio">
									<div class="w2p-strategy-card-inner">
										<div class="w2p-strategy-icon"><i class="fa-solid fa-pen-ruler"></i></div>
										<div class="w2p-strategy-text">
											<strong><?php esc_html_e( 'Manage Existing Chapters', 'wp-genius' ); ?></strong>
											<span><?php esc_html_e( 'Adjust volumes, titles and indexes without uploading', 'wp-genius' ); ?></span>
										</div>
									</div>
								</label>
							</div>
						</div>
					</div>

					<!-- C 线路：管理现有章节工作台（初始隐藏，复用 Fix Chapter Index 编辑逻辑）-->
					<div id="w2p-manage-chapters-wrap">
						<div class="w2p-preview-toolbar">
							<div class="w2p-toolbar-left">
								<button type="button" id="w2p-manage-batch-vol-btn" class="w2p-btn w2p-btn-secondary w2p-btn-sm">
									<i class="fa-solid fa-pen-to-square"></i> <?php esc_html_e( 'Batch Set Volume', 'wp-genius' ); ?>
								</button>
								<button type="button" id="w2p-manage-batch-title-btn" class="w2p-btn w2p-btn-secondary w2p-btn-sm">
									<i class="fa-solid fa-i-cursor"></i> <?php esc_html_e( 'Batch Modify Titles', 'wp-genius' ); ?>
								</button>
								<button type="button" id="w2p-manage-regen-index-btn" class="w2p-btn w2p-btn-secondary w2p-btn-sm">
									<i class="fa-solid fa-list-ol"></i> <?php esc_html_e( 'Regenerate Index', 'wp-genius' ); ?>
								</button>
							</div>
							<div class="w2p-toolbar-right">
								<button type="button" id="w2p-manage-save-btn" class="w2p-btn w2p-btn-primary">
									<i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e( 'Save Changes', 'wp-genius' ); ?>
								</button>
							</div>
						</div>

						<div class="w2p-log-container w2p-preview-table-wrapper">
							<table class="w2p-preview-table">
								<thead>
									<tr>
										<th width="40"><input type="checkbox" id="w2p-manage-check-all"></th>
										<th width="60"><?php esc_html_e( '#', 'wp-genius' ); ?></th>
										<th width="120"><?php esc_html_e( 'Index', 'wp-genius' ); ?></th>
										<th width="160"><?php esc_html_e( 'Volume', 'wp-genius' ); ?></th>
										<th><?php esc_html_e( 'Chapter Title', 'wp-genius' ); ?></th>
										<th width="90"><?php esc_html_e( 'Words', 'wp-genius' ); ?></th>
										<th width="70"><?php esc_html_e( 'Action', 'wp-genius' ); ?></th>
									</tr>
								</thead>
								<tbody id="w2p-manage-chapters-tbody"></tbody>
							</table>
						</div>
					</div>
				</div>

				<!-- 0. 上传文件区域 (独立公共模块：新建模式与现有书籍 A/B 线路共享，C 线路隐藏) -->
				<div id="w2p-upload-file-section" class="w2p-form-row w2p-upload-dropzone-row">
					<div class="csf-title">
						<label for="w2p_novel_file"><?php esc_html_e( 'Document File (.docx / .txt)', 'wp-genius' ); ?> <span class="w2p-required">*</span></label>
					</div>
					<div class="w2p-form-control">
						<div class="w2p-file-dropzone">
							<input type="file" name="novel_file" id="w2p_novel_file" accept=".docx,.txt">
							<div class="w2p-dropzone-inner">
								<i class="fa-solid fa-cloud-arrow-up w2p-dropzone-icon"></i>
								<p class="w2p-dropzone-text"><?php esc_html_e( 'Click to select or drag and drop a .docx or .txt novel file here', 'wp-genius' ); ?></p>
								<span id="w2p-selected-filename" class="w2p-filename-tag w2p-hidden"></span>
							</div>
						</div>
					</div>
				</div>

				<!-- 新建小说专属字段容器 (当选择导入现有书籍时自动折叠隐藏) -->
				<div id="w2p-new-novel-fields-wrap">

					<!-- 1. 小说标题 -->
					<div class="w2p-form-row">
						<div class="csf-title">
							<label for="w2p_novel_title"><?php esc_html_e( 'Novel Title', 'wp-genius' ); ?> <span class="w2p-required">*</span></label>
						</div>
						<div class="w2p-form-control">
							<input type="text" name="novel_title" id="w2p_novel_title" class="w2p-input-half" placeholder="<?php esc_attr_e( 'Auto-extracted from file or enter custom title', 'wp-genius' ); ?>">
						</div>
					</div>

					<!-- 2. 小说状态 -->
					<div class="w2p-form-row">
						<div class="csf-title">
							<label for="w2p_novel_status"><?php esc_html_e( 'Novel Status', 'wp-genius' ); ?></label>
						</div>
						<div class="w2p-form-control">
							<select name="novel_status" id="w2p_novel_status" class="w2p-input-half">
								<option value="已完结" selected><?php esc_html_e( 'Completed (已完结)', 'wp-genius' ); ?></option>
								<option value="连载中"><?php esc_html_e( 'Ongoing (连载中)', 'wp-genius' ); ?></option>
							</select>
						</div>
					</div>

					<!-- 3. 小说分类 (默认选中 general-novels) -->
					<div class="w2p-form-row">
						<div class="csf-title">
							<label for="w2p_novel_category"><?php esc_html_e( 'Novel Category', 'wp-genius' ); ?></label>
						</div>
						<div class="w2p-form-control">
							<?php
							wp_dropdown_categories(
								array(
									'name'             => 'novel_category',
									'id'               => 'w2p_novel_category',
									'taxonomy'         => 'category',
									'hide_empty'       => 0,
									'selected'         => $default_cat_id,
									'show_option_none' => __( '== Select Category ==', 'wp-genius' ),
									'class'            => 'w2p-input-half',
								)
							);
							?>
						</div>
					</div>

					<!-- 4. 小说标签 (文章编辑页标签样式) -->
					<div class="w2p-form-row">
						<div class="csf-title">
							<label for="w2p_novel_new_tag"><?php esc_html_e( 'Tags', 'wp-genius' ); ?></label>
						</div>
						<div class="w2p-form-control">
							<div class="w2p-tag-group">
								<input type="text" id="w2p_novel_new_tag" class="w2p-input-half" placeholder="<?php esc_attr_e( 'Add new tag', 'wp-genius' ); ?>" autocomplete="off">
								<button type="button" id="w2p-add-tag-btn" class="button button-secondary"><?php esc_html_e( 'Add', 'wp-genius' ); ?></button>
							</div>
							<div id="w2p-tags-list" class="w2p-tags-list"></div>
							<?php if ( ! empty( $novel_tags ) && ! is_wp_error( $novel_tags ) ) : ?>
								<div class="w2p-quick-tags">
									<?php foreach ( $novel_tags as $novel_tag ) : ?>
										<button type="button" class="w2p-quick-tag" data-tag="<?php echo esc_attr( $novel_tag->name ); ?>"><?php echo esc_html( $novel_tag->name ); ?></button>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- 5. 作者 / 人物 (文本输入，存在复用不存在新建) -->
					<div class="w2p-form-row">
						<div class="csf-title">
							<label for="w2p_novel_author"><?php esc_html_e( 'Author / Human', 'wp-genius' ); ?></label>
						</div>
						<div class="w2p-form-control">
							<input type="text" name="novel_author" id="w2p_novel_author" class="w2p-input-half" placeholder="<?php esc_attr_e( 'Enter author name (reuses if exists, creates if new)', 'wp-genius' ); ?>">
						</div>
					</div>
					<!-- 6. 小说简介 -->
					<div class="w2p-form-row">
						<div class="csf-title">
							<label for="w2p_novel_intro"><?php esc_html_e( 'Novel Introduction / Summary', 'wp-genius' ); ?></label>
						</div>
						<div class="w2p-form-control">
							<textarea name="novel_intro" id="w2p_novel_intro" rows="4" class="w2p-input-half" placeholder="<?php esc_attr_e( 'Introduction or synopsis of the novel...', 'wp-genius' ); ?>"></textarea>
						</div>
					</div>
					<!-- 7. 小说封面 -->
					<div class="w2p-form-row">
						<div class="csf-title">
							<label><?php esc_html_e( 'Novel Cover', 'wp-genius' ); ?></label>
						</div>
						<div class="w2p-form-control">
							<input type="hidden" name="novel_cover_id" id="w2p_novel_cover_id" value="">
							<div class="w2p-cover-uploader-box">
								<div id="w2p-cover-preview" class="w2p-cover-preview" style="display:none;">
									<img src="" alt="Cover Preview">
									<button type="button" id="w2p-cover-remove-btn" class="w2p-cover-remove" title="<?php esc_attr_e( 'Remove Cover', 'wp-genius' ); ?>"><i class="fa-solid fa-xmark"></i></button>
								</div>
								<button type="button" id="w2p-cover-select-btn" class="w2p-btn w2p-btn-secondary">
									<i class="fa-solid fa-image"></i> <?php esc_html_e( 'Choose Cover Image', 'wp-genius' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>

				

				<div id="w2p-upload-actions" class="w2p-form-actions">
					<button type="button" id="w2p-novel-parse-btn" class="w2p-btn w2p-btn-primary">
						<i class="fa-solid fa-wand-magic-sparkles"></i> <?php esc_html_e( 'Upload & Parse Document', 'wp-genius' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- 第二步：解析预览与手动微调工作台 (初始隐藏，解析成功后展示) -->
	<div id="w2p-novel-step-preview" class="w2p-section" style="display:none">
		<div class="w2p-section-header w2p-flex-between">
			<div class="w2p-preview-summary-badges">
				<span class="w2p-badge w2p-badge-primary"><i class="fa-solid fa-book"></i> <strong id="w2p-stat-chapters">0</strong> <?php esc_html_e( 'Chapters', 'wp-genius' ); ?></span>
				<span class="w2p-badge w2p-badge-info"><i class="fa-solid fa-layer-group"></i> <strong id="w2p-stat-volumes">0</strong> <?php esc_html_e( 'Volumes', 'wp-genius' ); ?></span>
				<span class="w2p-badge w2p-badge-success"><i class="fa-solid fa-font"></i> <strong id="w2p-stat-words">0</strong> <?php esc_html_e( 'Words', 'wp-genius' ); ?></span>
			</div>
		</div>

		<div class="w2p-section-body">
			<div class="w2p-preview-toolbar">
				<div class="w2p-toolbar-left">
					<button type="button" id="w2p-preview-batch-vol-btn" class="w2p-btn w2p-btn-secondary w2p-btn-sm">
						<i class="fa-solid fa-pen-to-square"></i> <?php esc_html_e( 'Batch Set Volume', 'wp-genius' ); ?>
					</button>
					<button type="button" id="w2p-preview-batch-title-btn" class="w2p-btn w2p-btn-secondary w2p-btn-sm">
						<i class="fa-solid fa-i-cursor"></i> <?php esc_html_e( 'Batch Modify Titles', 'wp-genius' ); ?>
					</button>
					<button type="button" id="w2p-preview-regen-index-btn" class="w2p-btn w2p-btn-secondary w2p-btn-sm">
						<i class="fa-solid fa-list-ol"></i> <?php esc_html_e( 'Regenerate Chapter Index', 'wp-genius' ); ?>
					</button>
					<button type="button" id="w2p-preview-batch-del-btn" class="w2p-btn w2p-btn-danger w2p-btn-sm">
						<i class="fa-solid fa-trash-can"></i> <?php esc_html_e( 'Delete Selected', 'wp-genius' ); ?>
					</button>
				</div>
				<div class="w2p-toolbar-right">
					<button type="button" id="w2p-preview-reparse-btn" class="w2p-btn w2p-btn-secondary w2p-btn-sm">
						<i class="fa-solid fa-arrow-rotate-left"></i> <?php esc_html_e( 'Cancel & Re-upload', 'wp-genius' ); ?>
					</button>
					<button type="button" id="w2p-novel-commit-import-btn" class="w2p-btn w2p-btn-primary">
						<i class="fa-solid fa-circle-check"></i> <?php esc_html_e( 'Confirm & Begin Import', 'wp-genius' ); ?>
					</button>
				</div>
			</div>

			<!-- 导入执行进度条 (导入时显示) -->
			<div id="w2p-import-progress-container" class="w2p-progress-container" style="display:none; margin: 20px 0;">
				<div class="w2p-progress-info">
					<span id="w2p-import-progress-status"><?php esc_html_e( 'Preparing to import...', 'wp-genius' ); ?></span>
					<span id="w2p-import-progress-count">0 / 0</span>
				</div>
				<div class="w2p-progress-bar-bg">
					<div id="w2p-import-progress-bar" class="w2p-progress-bar-fill" style="width: 0%;"></div>
				</div>
			</div>

			<!-- 章节预览与微调表格 -->
			<div class="w2p-log-container w2p-preview-table-wrapper">
				<table class="w2p-preview-table">
					<thead>
						<tr>
							<th width="40"><input type="checkbox" id="w2p-check-all-chapters"></th>
							<th width="60"><?php esc_html_e( '#', 'wp-genius' ); ?></th>
							<th width="120" class="w2p-sortable-th" data-sort="index" title="<?php esc_attr_e( 'Click to sort by index', 'wp-genius' ); ?>">
								<?php esc_html_e( 'Index', 'wp-genius' ); ?> <i class="fa-solid fa-sort w2p-sort-icon"></i>
							</th>
							<th width="160"><?php esc_html_e( 'Volume', 'wp-genius' ); ?></th>
							<th><?php esc_html_e( 'Chapter Title', 'wp-genius' ); ?></th>
							<th width="90"><?php esc_html_e( 'Words', 'wp-genius' ); ?></th>
							<th width="70"><?php esc_html_e( 'Action', 'wp-genius' ); ?></th>
						</tr>
					</thead>
					<tbody id="w2p-chapters-preview-tbody">
						<!-- 动态渲染 -->
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- Novel Manager 专属模态框挂载容器 (隔离全局 DOM，防止被全局 UI 选择器劫持) -->
	<div id="w2p-novel-manager-modals-portal" class="w2p-novel-manager-modals">

		<!-- 1. 导入完成仪式感成功模态弹出层 -->
		<div id="w2p-import-success-modal" class="w2p-novel-modal-overlay">
			<div class="w2p-novel-modal-card w2p-success-modal-card">
				<div class="w2p-modal-header">
					<h3 class="w2p-modal-title"><?php esc_html_e( 'Report', 'wp-genius' ); ?></h3>
					<button type="button" class="w2p-modal-close dashicons dashicons-no-alt" id="w2p-success-modal-close" title="<?php esc_attr_e( 'Close', 'wp-genius' ); ?>"></button>
				</div>
				<div class="w2p-modal-body w2p-success-modal-body">
					<div class="w2p-success-icon-wrapper">
						<i class="fa-solid fa-circle-check"></i>
					</div>
					<h3 class="w2p-success-title"><?php esc_html_e( 'Successful!', 'wp-genius' ); ?></h3>
					<p class="w2p-success-desc"><?php esc_html_e( 'Import complete! Successfully published novel and all chapters.', 'wp-genius' ); ?></p>
				</div>
				<div class="w2p-modal-footer w2p-success-modal-footer">
					<a href="#" id="w2p-view-novel-btn" target="_blank" class="w2p-btn w2p-btn-secondary">
						<i class="fa-solid fa-book-open"></i> <?php esc_html_e( 'View', 'wp-genius' ); ?>
					</a>
					<button type="button" id="w2p-import-another-btn" class="w2p-btn w2p-btn-primary">
						<i class="fa-solid fa-rotate-right"></i> <?php esc_html_e( 'Continue', 'wp-genius' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- 2. 清空重导高危操作二次确认模态弹出层 -->
		<div id="w2p-truncate-confirm-modal" class="w2p-novel-modal-overlay">
			<div class="w2p-novel-modal-card">
				<div class="w2p-modal-header">
					<h3 class="w2p-modal-title w2p-text-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php esc_html_e( 'Confirm Truncate & Re-import', 'wp-genius' ); ?></h3>
					<button type="button" class="w2p-modal-close dashicons dashicons-no-alt" id="w2p-truncate-modal-close" title="<?php esc_attr_e( 'Close', 'wp-genius' ); ?>"></button>
				</div>
				<div class="w2p-modal-body">
					<p>
						<?php esc_html_e( 'You have selected Truncate & Re-import. This will PERMANENTLY DELETE all existing chapters of this novel before importing new chapters:', 'wp-genius' ); ?>
					</p>
					<div class="w2p-warning-box">
						<p><strong><?php esc_html_e( 'Target Novel:', 'wp-genius' ); ?></strong> <span id="w2p-truncate-modal-novel-name"></span></p>
						<p><strong><?php esc_html_e( 'Existing Chapters to be DELETED:', 'wp-genius' ); ?></strong> <span id="w2p-truncate-modal-chapters-count" class="w2p-text-danger"></span></p>
					</div>
					<p class="w2p-warning-note">
						<?php esc_html_e( 'This operation is IRREVERSIBLE. Are you sure you want to proceed?', 'wp-genius' ); ?>
					</p>
				</div>
				<div class="w2p-modal-footer">
					<button type="button" id="w2p-truncate-modal-cancel" class="w2p-btn w2p-btn-secondary">
						<?php esc_html_e( 'Cancel', 'wp-genius' ); ?>
					</button>
					<button type="button" id="w2p-truncate-modal-confirm" class="w2p-btn w2p-btn-danger">
						<i class="fa-solid fa-trash-can"></i> <?php esc_html_e( 'Yes, Delete & Re-import', 'wp-genius' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- 3. 上传解析批量修改章节标题模态弹出层 -->
		<div id="w2p-upload-batch-title-modal" class="w2p-novel-modal-overlay w2p-batch-title-modal">
			<div class="w2p-novel-modal-card w2p-batch-title-modal-card">
				<div class="w2p-modal-header">
					<h3 class="w2p-modal-title"><i class="fa-solid fa-pen-ruler"></i> <?php esc_html_e( 'Batch Modify Chapter Titles', 'wp-genius' ); ?></h3>
					<button type="button" class="w2p-modal-close dashicons dashicons-no-alt w2p-batch-title-modal-close" id="w2p-upload-batch-title-modal-close" title="<?php esc_attr_e( 'Close', 'wp-genius' ); ?>"></button>
				</div>
				<div class="w2p-modal-body">
					<p class="w2p-batch-title-tip" id="w2p-upload-batch-title-scope-tip">
						<?php esc_html_e( 'Will be applied to selected chapters (or all chapters if none selected).', 'wp-genius' ); ?>
					</p>

					<!-- Mode 1: 查找替换 -->
					<div class="w2p-batch-form-group">
						<label><strong><?php esc_html_e( 'Find & Replace Text', 'wp-genius' ); ?></strong></label>
						<div class="w2p-batch-inputs-row">
							<input type="text" id="w2p-upload-batch-title-find" class="w2p-input-full" placeholder="<?php esc_attr_e( 'Find text (e.g. [Ad Text])...', 'wp-genius' ); ?>">
							<input type="text" id="w2p-upload-batch-title-replace" class="w2p-input-full" placeholder="<?php esc_attr_e( 'Replace with (leave empty to delete)...', 'wp-genius' ); ?>">
						</div>
					</div>

					<!-- Mode 2: 添加前后缀 -->
					<div class="w2p-batch-form-group">
						<label><strong><?php esc_html_e( 'Add Prefix / Suffix (Optional)', 'wp-genius' ); ?></strong></label>
						<div class="w2p-batch-inputs-row">
							<input type="text" id="w2p-upload-batch-title-prefix" class="w2p-input-full" placeholder="<?php esc_attr_e( 'Add prefix text...', 'wp-genius' ); ?>">
							<input type="text" id="w2p-upload-batch-title-suffix" class="w2p-input-full" placeholder="<?php esc_attr_e( 'Add suffix text...', 'wp-genius' ); ?>">
						</div>
					</div>
				</div>
				<div class="w2p-modal-footer">
					<button type="button" id="w2p-upload-batch-title-cancel-btn" class="w2p-btn w2p-btn-secondary w2p-batch-title-cancel-btn">
						<?php esc_html_e( 'Cancel', 'wp-genius' ); ?>
					</button>
					<button type="button" id="w2p-upload-batch-title-apply-btn" class="w2p-btn w2p-btn-primary w2p-batch-title-apply-btn">
						<i class="fa-solid fa-check"></i> <?php esc_html_e( 'Apply Changes', 'wp-genius' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- 4. C 线路：管理现有章节批量修改标题模态弹出层 -->
		<div id="w2p-manage-batch-title-modal" class="w2p-novel-modal-overlay w2p-batch-title-modal">
			<div class="w2p-novel-modal-card w2p-batch-title-modal-card">
				<div class="w2p-modal-header">
					<h3 class="w2p-modal-title"><i class="fa-solid fa-pen-ruler"></i> <?php esc_html_e( 'Batch Modify Chapter Titles', 'wp-genius' ); ?></h3>
					<button type="button" class="w2p-modal-close dashicons dashicons-no-alt w2p-manage-batch-title-modal-close" title="<?php esc_attr_e( 'Close', 'wp-genius' ); ?>"></button>
				</div>
				<div class="w2p-modal-body">
					<p class="w2p-batch-title-tip" id="w2p-manage-batch-title-scope-tip">
						<?php esc_html_e( 'Will be applied to selected chapters (or all chapters if none selected).', 'wp-genius' ); ?>
					</p>
					<div class="w2p-batch-form-group">
						<label><strong><?php esc_html_e( 'Find & Replace Text', 'wp-genius' ); ?></strong></label>
						<div class="w2p-batch-inputs-row">
							<input type="text" id="w2p-manage-batch-title-find" class="w2p-input-full" placeholder="<?php esc_attr_e( 'Find text (e.g. [Ad Text])...', 'wp-genius' ); ?>">
							<input type="text" id="w2p-manage-batch-title-replace" class="w2p-input-full" placeholder="<?php esc_attr_e( 'Replace with (leave empty to delete)...', 'wp-genius' ); ?>">
						</div>
					</div>
					<div class="w2p-batch-form-group">
						<label><strong><?php esc_html_e( 'Add Prefix / Suffix (Optional)', 'wp-genius' ); ?></strong></label>
						<div class="w2p-batch-inputs-row">
							<input type="text" id="w2p-manage-batch-title-prefix" class="w2p-input-full" placeholder="<?php esc_attr_e( 'Add prefix text...', 'wp-genius' ); ?>">
							<input type="text" id="w2p-manage-batch-title-suffix" class="w2p-input-full" placeholder="<?php esc_attr_e( 'Add suffix text...', 'wp-genius' ); ?>">
						</div>
					</div>
				</div>
				<div class="w2p-modal-footer">
					<button type="button" class="w2p-btn w2p-btn-secondary w2p-manage-batch-title-modal-close">
						<?php esc_html_e( 'Cancel', 'wp-genius' ); ?>
					</button>
					<button type="button" id="w2p-manage-batch-title-apply-btn" class="w2p-btn w2p-btn-primary w2p-batch-title-apply-btn">
						<i class="fa-solid fa-check"></i> <?php esc_html_e( 'Apply Changes', 'wp-genius' ); ?>
					</button>
				</div>
			</div>
		</div>

	</div>

</div>
