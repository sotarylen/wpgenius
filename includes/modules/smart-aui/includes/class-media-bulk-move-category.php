<?php
/**
 * WPGenius Smart AUI — 媒体库列表模式批量操作：移动到分类
 *
 * 由子主题 Impreza-child inc/media-bulk-move-category.php 迁移而来，行为对齐：
 *  - bulk_actions-upload 增加「移动到分类」选项；
 *  - 批量操作区注入「目标分类」下拉（仅列表模式，admin_footer JS 注入）；
 *  - handle_bulk_actions-upload 执行 wp_set_object_terms（替换语义，非追加）；
 *  - admin_notices 回显移动结果。
 *
 * taxonomy 为 us_media_category（媒体专用分类，层级式，label「媒体分类」），
 * 与子主题 W2P_MEDIA_TAX 常量同值；本文件以类常量 MEDIA_TAX 承载，
 * 不再定义全局常量，避免并存期常量重复定义。
 *
 * 相对子主题的修正（详见交付说明）：
 *  - 目标分类改从 $_REQUEST 读取：upload.php 列表模式的批量操作表单是
 *    method="get"，子主题读 $_POST 恒为空，导致「永远走 nocat 分支」的潜伏 bug；
 *  - 顶/底两份同名下拉做 JS 双向值同步，避免重复表单字段提交时
 *    PHP 以后出现者为准、底部默认值 0 覆盖用户选择；
 *  - 去掉内联 style="..."，显隐改用 class（.w2p-hidden）+ <style> 块；
 *  - admin_notices 不再裸输出用户输入（sanitize_text_field + esc_html）；
 *  - 两份下拉 select id 去重；wp_json_encode 加 JSON_HEX_* 防 term 名注入脚本块。
 *
 * 并存期让位：四个回调运行时先做 function_exists（bulk_actions 用 isset）守卫，
 * 子主题同名实现存在时插件直接返回，由先加载的子主题生效；
 * 删除子主题对应代码后本类自动接管。
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 媒体库批量「移动到分类」功能类。
 */
class W2P_SmartAUI_Media_Bulk_Move_Category {

	/**
	 * 媒体分类 taxonomy 名（层级式，label「媒体分类」）。
	 *
	 * @var string
	 */
	const MEDIA_TAX = 'us_media_category';

	/**
	 * 批量动作 key（与子主题一致，AC-2.x 据此断言）。
	 *
	 * @var string
	 */
	const BULK_ACTION = 'move_to_us_media_category';

	/**
	 * 构造器：挂载四个钩子。
	 */
	public function __construct() {
		// 1) 批量操作下拉增加「移动到分类」。
		add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_action' ), 10, 1 );

		// 2) 处理批量操作提交（WP 核心在触发前已校验 bulk-media nonce）。
		// 优先级 20：晚于子主题（10）执行——并存期子主题旧版读 $_POST 恒空只会
		// 返回 nocat（不移动任何附件），随后本插件修复版（读 $_REQUEST）真正执行
		// 移动并覆盖 w2p_moved 结果参数，功能在并存期也可用；子主题删除后独立接管。
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_move' ), 20, 3 );

		// 3) 注入前端 JS：仅 upload.php 列表模式。
		add_action( 'admin_footer', array( $this, 'inject_dropdown_js' ), 10 );

		// 4) 回显批量移动结果。
		add_action( 'admin_notices', array( $this, 'show_result_notice' ), 10 );
	}

	/**
	 * 批量操作下拉增加「移动到分类」选项。
	 *
	 * @param array $actions 现有批量操作列表。
	 * @return array 追加后的批量操作列表。
	 */
	public function add_bulk_action( $actions ) {
		// 已存在同名选项（并存期子主题或其它来源已添加）则不覆盖。
		if ( isset( $actions[ self::BULK_ACTION ] ) ) {
			return $actions;
		}

		$actions[ self::BULK_ACTION ] = __( '移动到分类', 'wp-genius' );

		return $actions;
	}

	/**
	 * 处理批量操作提交：把一批附件移到目标分类（替换现有 term）。
	 *
	 * 注意：upload.php 列表模式的批量操作表单 method="get"，目标分类值在
	 * 查询串中，故从 $_REQUEST 读取（子主题读 $_POST 恒为空，是原实现 bug）。
	 *
	 * @param string $redirect_to 重定向目标 URL。
	 * @param string $action      批量动作 key。
	 * @param array  $post_ids    附件 ID 数组（核心已 intval）。
	 * @return string 携带 w2p_moved 结果参数的重定向 URL。
	 */
	public function handle_bulk_move( $redirect_to, $action, $post_ids ) {
		if ( self::BULK_ACTION !== $action ) {
			return $redirect_to;
		}

		// 并存策略（不 defer）：本插件优先级 20 晚于子主题（10）执行。子主题旧版读
		// $_POST 恒为空（upload.php 列表批量表单 method=get），只会返回 nocat 且不
		// 移动任何附件；插件随后用 $_REQUEST 正确取值执行移动并覆盖结果参数。
		// 防御性 nonce 校验：WP 核心在触发 handle_bulk_actions-upload 前已对
		// bulk-media nonce 执行过 check_admin_referer（wp-admin/upload.php），
		// 此处重复校验同一 nonce 必然通过，仅为纵深防御，不与核心冲突。
		check_admin_referer( 'bulk-media' );

		// 目标分类 term_id：白名单为「属于 MEDIA_TAX 的真实 term」，经 absint 收敛。
		$target = isset( $_REQUEST['w2p_target_media_cat'] ) ? absint( wp_unslash( $_REQUEST['w2p_target_media_cat'] ) ) : 0;

		// 未选择目标分类。
		if ( $target <= 0 ) {
			return add_query_arg( 'w2p_moved', 'nocat', $redirect_to );
		}

		// 校验 term 真实存在且属于本 taxonomy（防跨 taxonomy 注入）。
		$term = get_term( $target, self::MEDIA_TAX );
		if ( ! $term || is_wp_error( $term ) ) {
			return add_query_arg( 'w2p_moved', 'badcat', $redirect_to );
		}

		$done = $this->move_attachments_to_category( $post_ids, $target );

		return add_query_arg( 'w2p_moved', $done, $redirect_to );
	}

	/**
	 * 核心执行：把一批附件移到目标分类（逐项校验 + 替换语义）。
	 *
	 * @param array $post_ids       附件 ID 数组。
	 * @param int   $target_term_id 目标分类 term_id（已校验有效）。
	 * @return int 成功移动的数量。
	 */
	protected function move_attachments_to_category( $post_ids, $target_term_id ) {
		$done = 0;

		foreach ( (array) $post_ids as $pid ) {
			$pid = absint( $pid );
			if ( $pid <= 0 ) {
				continue;
			}

			// 逐项权限校验：无编辑权限的对象跳过。
			if ( ! current_user_can( 'edit_post', $pid ) ) {
				continue;
			}

			// 仅处理附件对象，混选的非附件对象跳过。
			if ( 'attachment' !== get_post_type( $pid ) ) {
				continue;
			}

			// 第四个参数 false = 替换语义（移动），不是追加。
			$result = wp_set_object_terms( $pid, $target_term_id, self::MEDIA_TAX, false );
			if ( ! is_wp_error( $result ) ) {
				$done++;
			}
		}

		return $done;
	}

	/**
	 * 注入前端 JS：批量操作区显示「目标分类」下拉。
	 *
	 * 仅 upload.php 列表模式（存在 .bulkactions 的 form）生效；网格模式 JS 自行跳过。
	 * 下拉 HTML 由 PHP wp_dropdown_categories 构建、wp_json_encode 注入，
	 * JS 不做复杂 HTML 拼接；并存期以 .w2p-move-cat-wrap 存在性做 DOM 幂等。
	 *
	 * @return void
	 */
	public function inject_dropdown_js() {
		global $pagenow;

		// 仅媒体库页生效。
		if ( 'upload.php' !== $pagenow ) {
			return;
		}

		// 并存守卫：子主题同名注入函数已声明（已挂到 admin_footer），
		// 让位由子主题注入，避免下拉出现两份。
		if ( function_exists( 'w2p_move_media_cat_inject_js' ) ) {
			return;
		}

		// taxonomy 缺失时直接放弃注入，避免生成空下拉与无谓查询告警。
		if ( ! taxonomy_exists( self::MEDIA_TAX ) ) {
			return;
		}

		// 生成分类下拉 HTML（hierarchical，显示层级缩进）。
		$dropdown = wp_dropdown_categories(
			array(
				'taxonomy'         => self::MEDIA_TAX,
				'name'             => 'w2p_target_media_cat',
				'id'               => 'w2p-target-media-cat',
				'show_option_none' => __( '— 选择目标分类 —', 'wp-genius' ),
				'hierarchical'     => true,
				'hide_empty'       => 0,
				'echo'             => false,
			)
		);

		if ( ! $dropdown ) {
			return;
		}

		// 压缩换行，便于随 JS 注入。
		$dropdown = str_replace( array( "\n", "\r" ), '', $dropdown );

		// 完整 HTML 片段（含外层 span）在 PHP 侧构建，JS 仅注入 JSON 编码后的整串。
		// JSON_HEX_* 把 < > & " ' 转义为 \uXXXX，杜绝 term 名含 </script> 等
		// 序列破坏脚本块（XSS 加固）。
		$wrap = '<span class="w2p-move-cat-wrap w2p-hidden">' . $dropdown . '</span>';
		?>
		<script type="text/javascript">
		(function ($) {
			$(function () {
				// 网格模式没有 .bulkactions 容器，列表模式才注入。
				if (!$('.bulkactions').length) return;
				// DOM 幂等：并存期子主题已注入（或脚本重复执行）则跳过，防双份。
				if ($('.w2p-move-cat-wrap').length) return;

				// 精准插到顶/底两个「应用」提交按钮（#doaction / #doaction2）前，
				// 确保下拉落在对应批量操作 <form> 内、随「应用」一起提交出去。
				$('#doaction, #doaction2').before(<?php echo wp_json_encode( $wrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>);

				var wraps = $('.w2p-move-cat-wrap');

				// 顶/底两份下拉的 select id 去重，避免重复 id。
				wraps.eq(1).find('select').attr('id', 'w2p-target-media-cat-2');

				// 两份下拉值双向同步：同名表单字段提交时 PHP 以后出现者为准，
				// 保持两份值一致，避免底部默认值 0 覆盖顶部的用户选择。
				wraps.find('select').on('change', function () {
					wraps.find('select').not(this).val($(this).val());
				});

				// 随「批量操作」下拉选中值显隐目标分类下拉。
				function sync() {
					var show = false;
					$('select[name="action"], select[name="action2"]').each(function () {
						if ($(this).val() === 'move_to_us_media_category') show = true;
					});
					wraps.toggleClass('w2p-hidden', !show);
				}
				$('select[name="action"], select[name="action2"]').on('change', sync);
				sync();
			});
		})(jQuery);
		</script>
		<style>
			.w2p-move-cat-wrap { margin-left: 8px; }
			.w2p-move-cat-wrap select { vertical-align: middle; }
			.w2p-move-cat-wrap.w2p-hidden { display: none; }
		</style>
		<?php
	}

	/**
	 * 批量移动结果提示（admin_notices）。
	 *
	 * 结果状态由 w2p_moved 查询参数携带：nocat / badcat / 成功计数 / 0 或非法值。
	 * 用户输入先清洗再参与判断，输出一律 esc_html，杜绝 XSS。
	 *
	 * @return void
	 */
	public function show_result_notice() {
		if ( ! isset( $_GET['w2p_moved'] ) ) {
			return;
		}

		// 并存守卫：子主题同名提示函数已声明（已挂到 admin_notices），
		// 让位由子主题输出提示。
		if ( function_exists( 'w2p_bulk_move_media_category_notice' ) ) {
			return;
		}

		// 清洗后再判断，不把用户输入直接回显。
		$value = sanitize_text_field( wp_unslash( $_GET['w2p_moved'] ) );
		$count = is_numeric( $value ) ? (int) $value : 0;

		if ( 'nocat' === $value ) {
			$type = 'notice-error';
			$text = __( '移动到分类：请先在批量操作旁选择目标分类，再点「应用」。', 'wp-genius' );
		} elseif ( 'badcat' === $value ) {
			$type = 'notice-error';
			$text = __( '移动到分类：目标分类无效，操作已取消。', 'wp-genius' );
		} elseif ( $count > 0 ) {
			$type = 'notice-success';
			/* translators: %d: 成功移动的媒体文件数量。 */
			$text = sprintf( __( '已成功将 %d 个媒体文件移动到目标分类。', 'wp-genius' ), $count );
		} else {
			$type = 'notice-warning';
			$text = __( '没有媒体文件被移动（可能无编辑权限或所选对象不是附件）。', 'wp-genius' );
		}

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $text )
		);
	}
}
