<?php
/**
 * Smart AUI — 媒体附加筛选（find_posts 弹窗增强）
 *
 * 迁移自子主题 Impreza-child functions.php [任务3]（行 476–770），行为对齐：
 *  - pre_get_posts 改写 wp_ajax_find_posts 查询：post_type 白名单多选 / ID 范围 / post_status 筛选；
 *  - 搜索词只匹配 post_title（posts_where 一次性注入 LIKE），posts_per_page 50 → 25；
 *  - admin_print_footer_scripts 注入三档筛选 UI（Type / ID 范围 / Status）与 Search 劫持 JS。
 *
 * 并存策略：回调运行时若子主题同名全局函数（w2p_filter_find_posts_query /
 * w2p_force_title_only / w2p_inject_find_posts_ui）已声明，插件侧直接让位返回，由子主题生效；
 * JS 幂等标志复用 window.w2pFindPostsInjected，并存期 UI 天然单份。
 *
 * 仅当 Smart AUI 设置开关 smart_aui_media_find_posts_filter 启用时由 module.php 挂载。
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 类 W2P_SmartAUI_Media_FindPosts_Filter
 *
 * 媒体附加筛选：find_posts AJAX 查询增强 + Attach 弹窗筛选 UI 注入。
 */
class W2P_SmartAUI_Media_FindPosts_Filter {

	/**
	 * 查询白名单（与子主题一字不差）。
	 *
	 * 用 get_post_types( array( 'public' => true ) ) 会漏掉没设 public 的自定义类型
	 * （如 novel/albums），还会拉进 wp_template/wp_block 等 block theme internal 类型
	 * 干扰 UI；chapter 永远进黑名单；attachment 是被查找对象本身，绝不能查。
	 *
	 * @var array
	 */
	private $allowed_types = array( 'post', 'page', 'novel', 'albums' );

	/**
	 * post_status 白名单（publish|draft|any|all；默认 any）。
	 *
	 * @var array
	 */
	private $allowed_status = array( 'publish', 'draft', 'any', 'all' );

	/**
	 * 每页条数（子主题 posts_per_page 50 -> 25）。
	 *
	 * @var int
	 */
	private $per_page = 25;

	/**
	 * post__in 条数上限（安全护栏：range() 展开超过该值则放弃 ID 条件，防超大 IN 子句）。
	 *
	 * @var int
	 */
	private $max_post_ids = 5000;

	/**
	 * 构造器：挂载钩子。
	 *
	 * posts_where 不在构造器注册，而是在 filter_find_posts_query 内按需 add_filter，
	 * 且回调首次执行后自移除（沿用子主题一次性注入模式）。
	 */
	public function __construct() {
		// 后端查询改写：wp_ajax_find_posts 请求。
		add_action( 'pre_get_posts', array( $this, 'filter_find_posts_query' ), 10 );

		// 弹窗筛选 UI 注入：仅 upload.php 且已加载 media 脚本时输出。
		add_action( 'admin_print_footer_scripts', array( $this, 'inject_find_posts_ui' ), 10 );
	}

	/**
	 * 在 wp_ajax_find_posts 请求里改写查询参数。
	 *
	 * 触发条件精确锁定 find_posts AJAX：is_admin() + DOING_AJAX 排除前台/常规后台查询；
	 * action === 'find_posts' 已足够唯一区分，无需检查 is_main_query()
	 * （wp_ajax_find_posts 内部用 get_posts() 创建的是「非主查询」，is_main_query() 恒为 false）。
	 *
	 * 并存让位：子主题 w2p_filter_find_posts_query 已声明时直接返回，由子主题改写查询。
	 *
	 * @param WP_Query $query 当前查询对象。
	 * @return void
	 */
	public function filter_find_posts_query( $query ) {
		// 并存期让位：子主题同名回调存在时由子主题生效，插件不重复介入。
		if ( function_exists( 'w2p_filter_find_posts_query' ) ) {
			return;
		}

		if ( ! is_admin() || ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			return;
		}
		if ( ( $_REQUEST['action'] ?? '' ) !== 'find_posts' ) {
			return;
		}

		$allowed_types = $this->allowed_types;

		// ----- post_type 多选（白名单）-----
		if ( isset( $_POST['ac_post_type'] ) ) {
			$requested = sanitize_key( wp_unslash( $_POST['ac_post_type'] ) );
			if ( 'all' === $requested ) {
				$post_types = $allowed_types;
			} elseif ( in_array( $requested, $allowed_types, true ) ) {
				$post_types = array( $requested );
			} else {
				$post_types = $allowed_types;
			}
		} else {
			$post_types = $allowed_types;
		}

		// chapter 始终排除（ac_exclude_chapter 默认 1，无论前端传否）。
		$post_types = array_values( array_diff( $post_types, array( 'chapter' ) ) );
		// 兜底：防用户只选 chapter → post_type 不为空（其实在白名单外时不会发生，但保留保险）。
		if ( empty( $post_types ) ) {
			$post_types = $allowed_types;
		}
		$query->set( 'post_type', $post_types );

		// ----- ID 范围（支持 100-500 或 12,34；解析失败忽略）-----
		$post_ids = array();
		if ( ! empty( $_POST['ac_post_id_range'] ) ) {
			$range = (string) wp_unslash( $_POST['ac_post_id_range'] );
			if ( preg_match( '/^\s*(\d+)\s*-\s*(\d+)\s*$/', $range, $m ) ) {
				$start = (int) $m[1];
				$end   = (int) $m[2];
				if ( $start <= $end ) {
					// 安全护栏：先按跨度预判（$end <= $start + 上限 - 1，避免加法溢出），
					// 超限直接放弃该条件，防止 range() 先展开超大数组导致内存耗尽（QA 必改项）。
					if ( $end <= $start + $this->max_post_ids - 1 ) {
						$post_ids = range( $start, $end );
					}
				}
			} elseif ( preg_match( '/^[\d,\s]+$/', $range ) ) {
				$post_ids = array_filter(
					array_map( 'absint', explode( ',', $range ) ),
					function ( $x ) {
						return $x > 0;
					}
				);
				$post_ids = array_values( $post_ids );
			}
			// 解析失败保持空数组 → 不设置 post__in（等价无 ID 过滤，不清空其它筛选）。
		}

		// 安全护栏（相对子主题新增）：range() 展开超过上限则放弃该条件，
		// 避免生成超大 IN 子句拖垮查询；解析失败/超限均等价「无 ID 过滤」。
		if ( ! empty( $post_ids ) && count( $post_ids ) <= $this->max_post_ids ) {
			$query->set( 'post__in', $post_ids );
		}

		// ----- post_status（publish|draft|any|all；默认 any）-----
		$post_status = 'any';
		if ( ! empty( $_POST['ac_post_status'] ) ) {
			$s = strtolower( sanitize_key( wp_unslash( $_POST['ac_post_status'] ) ) );
			if ( in_array( $s, $this->allowed_status, true ) ) {
				$post_status = $s;
			}
		}
		$query->set( 'post_status', $post_status );

		// ----- 分页 50 -> 25 -----
		$query->set( 'posts_per_page', $this->per_page );

		// ----- 搜索词只查 post_title（不查 post_content）-----
		if ( ! empty( $_POST['ps'] ) ) {
			// 清空默认 s 以移除 WP 对 title+content 的全文搜索，再由 posts_where 加回 title-only。
			$query->query_vars['s'] = '';
			// get_posts 默认 suppress_filters=true，posts_where 不触发，必须显式关掉。
			$query->set( 'suppress_filters', false );
			$query->set( '_w2p_smart_aui_title_only', true );
			// 必须在这里挂上 posts_where 回调；force_title_only 会从 $_POST['ps'] 取词注入 LIKE。
			// 回调内部一次性 remove_filter 自己，避免污染同进程后续查询。
			add_filter( 'posts_where', array( $this, 'force_title_only' ), 10, 2 );
		}
	}

	/**
	 * 一次性 posts_where 注入：把搜索限定为 post_title LIKE。
	 *
	 * 仅在被标记（_w2p_smart_aui_title_only）的查询上生效，并在首次执行后立即注销自己，
	 * 避免污染同进程其它查询。LIKE 写法与子主题一致（$wpdb->esc_like + prepare）。
	 *
	 * @param string   $where WHERE 子句。
	 * @param WP_Query $query 当前查询对象。
	 * @return string
	 */
	public function force_title_only( $where, $query ) {
		if ( ! $query->get( '_w2p_smart_aui_title_only' ) ) {
			return $where;
		}

		// 一次性：执行后立刻移除自身（防止多查询/多实例冲突）。
		remove_filter( 'posts_where', array( $this, 'force_title_only' ), 10 );

		global $wpdb;

		$term = wp_unslash( $_POST['ps'] ?? '' );
		if ( '' !== $term ) {
			$like   = '%' . $wpdb->esc_like( $term ) . '%';
			$where .= $wpdb->prepare( ' AND (post_title LIKE %s) ', $like );
		}

		return $where;
	}

	/**
	 * 给 WP 原生 Attach 弹窗（find-posts / "Attach to existing content"）注入额外筛选 UI：
	 * 文章类型 / ID 范围 / 状态。
	 *
	 * 作用域：仅 wp-admin/upload.php 且已加载 media（find-posts 依赖）时注入。
	 * 不修改 WP 核心、wp-admin/js/media.js 或其它插件源码。
	 * 提交字段交由 core 在 wp_ajax_find_posts 中处理（无自建 AJAX 端点、无新 nonce，
	 * 原生 _ajax_nonce 由核心校验）。
	 *
	 * 并存让位：子主题 w2p_inject_find_posts_ui 已声明时直接返回，由子主题注入。
	 *
	 * @return void
	 */
	public function inject_find_posts_ui() {
		// 并存期让位：子主题同名注入回调存在时由子主题负责，插件不重复输出。
		if ( function_exists( 'w2p_inject_find_posts_ui' ) ) {
			return;
		}

		// 作用域控制 1：必须是上传管理页。
		global $pagenow;
		if ( ! isset( $pagenow ) || 'upload.php' !== $pagenow ) {
			return;
		}

		// 作用域控制 2：必须已加载原生的 media（find-posts）脚本。
		if ( ! function_exists( 'wp_script_is' ) || ! wp_script_is( 'media' ) ) {
			return;
		}

		// 4 档固定白名单（all/post/albums/novel），不查询 get_post_types——
		// 后端已对齐同一白名单，避免前后端不一致漏类型；chapter 后端永远排除。
		$options = sprintf(
			'<option value="all" selected>%1$s</option>' .
			'<option value="post">%2$s</option>' .
			'<option value="albums">%3$s</option>' .
			'<option value="novel">%4$s</option>',
			esc_html__( 'All', 'wp-genius' ),
			esc_html__( 'Posts', 'wp-genius' ),
			esc_html__( 'Albums', 'wp-genius' ),
			esc_html__( 'Novels', 'wp-genius' )
		);

		// 注入用 HTML 片段：PHP 构建 + wp_json_encode 注入，JS 只做 DOM 插入。
		// 样式走 .w2p-find-posts-extras 类 + 同页 <style> 块，不写内联 style（规则修正）。
		$html = '<div class="w2p-find-posts-extras">'
			. '<label>' . esc_html__( 'Type', 'wp-genius' )
			. '<select name="ac_post_type">' . $options . '</select></label>'
			. '<label>' . esc_html__( 'ID', 'wp-genius' )
			. '<input type="text" name="ac_post_id_range" placeholder="'
			. esc_attr__( '100-500 或 12,34', 'wp-genius' ) . '"></label>'
			. '<label>' . esc_html__( 'Status', 'wp-genius' )
			. '<select name="ac_post_status">'
			. '<option value="any">' . esc_html__( 'Default', 'wp-genius' ) . '</option>'
			. '<option value="publish">' . esc_html__( 'Published', 'wp-genius' ) . '</option>'
			. '<option value="draft">' . esc_html__( 'Draft', 'wp-genius' ) . '</option>'
			. '<option value="all">' . esc_html__( 'All', 'wp-genius' ) . '</option>'
			. '</select></label>'
			. '<input type="hidden" name="ac_exclude_chapter" value="1">'
			. '</div>';

		$html_json = wp_json_encode( $html );

		// JS 内可见文案统一走 i18n，经 wp_json_encode 注入（不硬编码进 JS）。
		$i18n = array(
			'searching' => __( '搜索中…', 'wp-genius' ),
			'no_items'  => __( 'No items found.', 'wp-genius' ),
			'error'     => __( '请求失败，请重试。', 'wp-genius' ),
		);
		$i18n_json = wp_json_encode( $i18n );
		?>
		<style id="w2p-find-posts-extras-css">
			.w2p-find-posts-extras {
				margin: 8px 0;
				padding: 8px;
				background: #f6f7f7;
				border: 1px solid #c3c4c7;
				display: flex;
				gap: 12px;
				flex-wrap: wrap;
				align-items: center;
				font-size: 13px;
			}
			.w2p-find-posts-extras select[name="ac_post_type"] {
				min-width: 120px;
			}
			.w2p-find-posts-extras input[name="ac_post_id_range"] {
				width: 160px;
			}
		</style>
		<script type="text/javascript">
		( function ( $ ) {
			// 幂等：防止重复注入 / 重复绑定（与子主题共用同一全局标志，并存期天然单份）。
			if ( window.w2pFindPostsInjected ) {
				return;
			}
			window.w2pFindPostsInjected = true;

			var w2pI18n = <?php echo $i18n_json; ?>;

			function w2pInjectExtras() {
				var $search = $( '#find-posts .find-box-search' );
				if ( ! $search.length ) {
					return false;
				}
				if ( ! $search.next( '.w2p-find-posts-extras' ).length ) {
					$search.after( <?php echo $html_json; ?> );
				}
				// 摘掉 media.js 在 #find-posts-search 上注册的 findPosts.send click handler，
				// 让我们自己的捕获阶段监听接管搜索逻辑。
				// WHY: media.js 的 .on('click') 不响应原生 stopPropagation/stopImmediatePropagation，
				//      必须显式 .off() 才能彻底摘掉。
				// 不动 #find-posts-submit：它绑的是 findPosts.update（塞 ID + 关闭弹窗 + 完成关联），
				// off 掉就会变成"点 Select = 再搜一次"，关联永远完不成。
				$( '#find-posts-search' ).off( 'click' );
				return true;
			}

			// 弹窗可能由 admin_footer 延迟输出，用 MutationObserver 兜底注入 + off。
			$( function () {
				if ( ! w2pInjectExtras() ) {
					var target = document.getElementById( 'find-posts' ) || document.body;
					var obs = new MutationObserver( function () {
						if ( w2pInjectExtras() ) {
							obs.disconnect();
						}
					} );
					obs.observe( target, { childList: true, subtree: true } );
				}
			} );

			// 只劫持 #find-posts-search（Search 按钮），完全不动 #find-posts-submit（Select 按钮）
			// ——Select 走 media.js 原生 findPosts.update 完成附件关联，不能碰。
			document.addEventListener( 'click', function ( e ) {
				var target = e.target;
				// 只匹配 Search 按钮；Select 按钮直接 return 让原生 handler 跑。
				if ( ! target || target.id !== 'find-posts-search' ) {
					return;
				}
				var $box = $( '#find-posts' );
				if ( ! $box.length ) {
					return;
				}

				e.preventDefault();
				e.stopImmediatePropagation();

				var nonce       = $box.find( 'input[name="_ajax_nonce"]' ).val() || '';
				var ps          = $box.find( '#find-posts-input' ).val() || '';
				var foundAction = $box.find( 'input[name="found_action"]' ).val() || '';
				var affected    = $box.find( '#affected' ).val() || '';

				var data = {
					action:       'find_posts',
					_ajax_nonce:  nonce,
					ps:           ps,
					found_action: foundAction,
					affected:     affected
				};

				// 收集注入字段（单选下拉，直接取 val）。
				data['ac_post_type']       = $box.find( 'select[name="ac_post_type"]' ).val() || 'all';
				data['ac_post_id_range']   = $box.find( 'input[name="ac_post_id_range"]' ).val() || '';
				data['ac_post_status']     = $box.find( 'select[name="ac_post_status"]' ).val() || 'any';
				data['ac_exclude_chapter'] = $box.find( 'input[name="ac_exclude_chapter"]' ).val() || '1';

				var $resp    = $( '#find-posts-response' );
				var $spinner = $box.find( '.spinner' );
				$spinner.addClass( 'is-active' );
				$resp.html( '<p>' + w2pI18n.searching + '</p>' );

				// 与媒体库 findPosts.send 同款：dataType: 'json'，拆 x.data 才是真 HTML。
				$.ajax( ajaxurl, {
					type: 'POST',
					data: data,
					dataType: 'json'
				} ).always( function () {
					$spinner.removeClass( 'is-active' );
				} ).done( function ( x ) {
					if ( ! x || ! x.success ) {
						$resp.html( '<div class="error"><p>' + w2pI18n.no_items + '</p></div>' );
						return;
					}
					$resp.html( x.data );
				} ).fail( function () {
					$resp.html( '<div class="error"><p>' + w2pI18n.error + '</p></div>' );
				} );
			}, true ); // 捕获阶段优先
		} )( jQuery );
		</script>
		<?php
	}
}
