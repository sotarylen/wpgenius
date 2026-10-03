<?php
/**
 * Album Importer Module
 *
 * 图集导入模组：扫描只读挂载目录下的图集子目录，按目录名解析元数据（工作室/模特/日期/编号/图片数量），
 * 生成待导入列表；导入时为每套图集创建一篇 albums，并把该目录下图片写入媒体库。
 *
 * 依赖：ACF（写字段）、ACF Post Type UI 注册的 albums 文章类型、photostudio / humans 分类法。
 * 挂载点可读性属运行期状态，刻意不放进 check_requirements（详见 class-album-importer.php 的 get_mount_status）。
 *
 * @package WP_Genius
 * @subpackage Modules/AlbumImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-album-name-parser.php';
require_once __DIR__ . '/includes/class-album-importer.php';

/**
 * Class W2P_AlbumImporterModule
 */
class W2P_AlbumImporterModule extends W2P_Abstract_Module {

	/**
	 * 模块唯一标识
	 */
	public static function id() {
		return 'album-importer';
	}

	/**
	 * 模块名称
	 */
	public static function name() {
		return __( 'Album Importer', 'wp-genius' );
	}

	/**
	 * 模块图标
	 */
	public static function icon() {
		return 'fa-solid fa-images';
	}

	/**
	 * 模块描述
	 */
	public static function description() {
		return __( 'Scan a local directory of photo sets, parse studio / model / date / serial from each subdirectory name, then create albums posts and import the images into the media library.', 'wp-genius' );
	}

	/**
	 * 硬依赖检查：缺失则禁止启用模块。
	 *
	 * 只放「语义依赖」——ACF、albums 文章类型、两个分类法。
	 * 挂载点可读属运行期环境状态，放进这里会导致挂载偶发不可读时模块被 loader 整块跳过、用户进不了设置页自救。
	 *
	 * @return true|WP_Error
	 */
	public function check_requirements() {
		$missing = array();

		if ( ! function_exists( 'acf' ) && ! class_exists( 'ACF' ) ) {
			$missing[] = __( 'Advanced Custom Fields (ACF) plugin is not active.', 'wp-genius' );
		}

		if ( ! post_type_exists( 'albums' ) ) {
			/* translators: %s: post type name */
			$missing[] = sprintf( __( 'Custom post type "%s" is not registered.', 'wp-genius' ), 'albums' );
		}

		foreach ( array( 'photostudio', 'humans' ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				/* translators: %s: taxonomy name */
				$missing[] = sprintf( __( 'Taxonomy "%s" is not registered.', 'wp-genius' ), $taxonomy );
			}
		}

		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'w2p_album_importer_requirements_failed',
				__( 'Album Importer requires:', 'wp-genius' ) . ' ' . implode( ' ', $missing )
			);
		}

		return true;
	}

	/**
	 * 环境检测页用的结构化依赖状态。
	 *
	 * @return array
	 */
	public static function get_requirements_status() {
		$acf_active = function_exists( 'acf' ) || class_exists( 'ACF' );
		$has_albums = post_type_exists( 'albums' );
		$has_studio = taxonomy_exists( 'photostudio' );
		$has_humans = taxonomy_exists( 'humans' );

		return array(
			'module_id'   => 'album-importer',
			'module_name' => __( 'Album Importer', 'wp-genius' ),
			'all_passed'  => $acf_active && $has_albums && $has_studio && $has_humans,
			'items'       => array(
				array(
					'name'        => __( 'Advanced Custom Fields (ACF)', 'wp-genius' ),
					'required'    => true,
					'status'      => $acf_active,
					'description' => __( 'Required for writing album number, release date and count.', 'wp-genius' ),
					'action_url'  => $acf_active ? '' : admin_url( 'plugin-install.php?s=Advanced+Custom+Fields&tab=search&type=term' ),
					'action_text' => $acf_active ? '' : __( 'Install ACF', 'wp-genius' ),
				),
				array(
					/* translators: %s: post type name */
					'name'        => sprintf( __( 'Custom Post Type: "%s"', 'wp-genius' ), 'albums' ),
					'required'    => true,
					'status'      => $has_albums,
					'description' => __( 'Target post type for imported albums.', 'wp-genius' ),
					'action_url'  => $has_albums ? '' : ( $acf_active ? admin_url( 'edit.php?post_type=acf-post-type' ) : '' ),
					'action_text' => $has_albums ? '' : __( 'Create Post Type', 'wp-genius' ),
				),
				array(
					/* translators: %s: taxonomy name */
					'name'        => sprintf( __( 'Taxonomy: "%s"', 'wp-genius' ), 'photostudio' ),
					'required'    => true,
					'status'      => $has_studio,
					'description' => __( 'Stores the studio parsed from the directory name.', 'wp-genius' ),
					'action_url'  => $has_studio ? '' : ( $acf_active ? admin_url( 'edit.php?post_type=acf-taxonomy' ) : '' ),
					'action_text' => $has_studio ? '' : __( 'Create Taxonomy', 'wp-genius' ),
				),
				array(
					/* translators: %s: taxonomy name */
					'name'        => sprintf( __( 'Taxonomy: "%s"', 'wp-genius' ), 'humans' ),
					'required'    => true,
					'status'      => $has_humans,
					'description' => __( 'Stores the model names matched from the directory name.', 'wp-genius' ),
					'action_url'  => $has_humans ? '' : ( $acf_active ? admin_url( 'edit.php?post_type=acf-taxonomy' ) : '' ),
					'action_text' => $has_humans ? '' : __( 'Create Taxonomy', 'wp-genius' ),
				),
			),
		);
	}

	/**
	 * 模块初始化
	 */
	public function init() {
		add_action( 'wp_ajax_w2p_album_browse', array( $this, 'ajax_browse' ) );
		add_action( 'wp_ajax_w2p_album_scan', array( $this, 'ajax_scan' ) );
		add_action( 'wp_ajax_w2p_album_create', array( $this, 'ajax_create_album' ) );
		add_action( 'wp_ajax_w2p_album_import_batch', array( $this, 'ajax_import_batch' ) );
		add_action( 'wp_ajax_w2p_album_discard', array( $this, 'ajax_discard_album' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * AJAX: 浏览一层子目录，供界面逐级下钻选目录。
	 */
	public function ajax_browse() {
		check_ajax_referer( 'w2p_album_importer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		// 用 sanitize_abs_path() 而不是 sanitize_text_field()：后者会把连续空白折叠成一个空格，
		// 把「2021 2022  陆萱萱」这类含双空格的真实目录名改坏，导致 is_dir() 失败。
		$path   = isset( $_POST['path'] ) ? W2P_Album_Importer::sanitize_abs_path( wp_unslash( $_POST['path'] ) ) : '';
		$result = W2P_Album_Importer::browse( $path );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		$scope                      = W2P_Album_Importer::resolve_scope( $result['path'] );
		$result['scope']            = $scope['mode'];
		$result['set_like_subdirs'] = $scope['set_like_subdirs'];

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: 扫描用户选中的目录。
	 */
	public function ajax_scan() {
		check_ajax_referer( 'w2p_album_importer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		// 大目录递归数图可能超过默认执行时限。
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- 部分环境禁用了该函数。
		}

		// 同 ajax_browse：不能用 sanitize_text_field()，它会折叠目录名里的连续空白。
		$path = isset( $_POST['path'] ) ? W2P_Album_Importer::sanitize_abs_path( wp_unslash( $_POST['path'] ) ) : '';

		if ( ! $this->is_path_allowed( $path ) ) {
			wp_send_json_error( __( 'Directory is outside the allowed browse root.', 'wp-genius' ) );
		}

		$rows = W2P_Album_Importer::scan( $path );

		if ( is_wp_error( $rows ) ) {
			wp_send_json_error( $rows->get_error_message() );
		}

		$scope = W2P_Album_Importer::resolve_scope( $path );

		wp_send_json_success(
			array(
				'path'  => $path,
				'scope' => $scope['mode'],
				'total' => count( $rows ),
				'rows'  => $rows,
			)
		);
	}

	/**
	 * AJAX: 为单个候选目录创建 albums 文章。
	 */
	public function ajax_create_album() {
		check_ajax_referer( 'w2p_album_importer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$payload = $this->read_payload();

		if ( is_wp_error( $payload ) ) {
			wp_send_json_error( $payload->get_error_message() );
		}

		$post_id = W2P_Album_Importer::create_album( $payload, get_current_user_id() );
		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( $post_id->get_error_message() );
		}

		$images = W2P_Album_Importer::collect_images( $payload['abs_path'] );

		wp_send_json_success(
			array(
				'album_id' => $post_id,
				'src_dir'  => $payload['abs_path'],
				'total'    => count( $images ),
			)
		);
	}

	/**
	 * AJAX: 单批图片入库（前端循环调用直到 done）。
	 */
	public function ajax_import_batch() {
		check_ajax_referer( 'w2p_album_importer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- 部分环境禁用了该函数。
		}

		// ⚠️ 批次游标参数名必须叫 w2p_offset，绝不能改回 offset。
		// us-core（Impreza 主题）的 US_Filter_Indexer::index() 会**无条件**读 $_POST['offset']，
		// 当作它自己「全站重建过滤器索引」的续跑游标，而它就挂在 save_post 上。
		// 于是本请求只要带过 offset > 0，这一批的 wp_update_post()（写正文那次）就会顺手被它
		// 拉去做全站重建：实测每批 32.7 万条 SQL、内存从 46MB 涨到 510MB 后 512MB 爆掉，
		// 单批 60~95s。第 1 批 offset=0 幸免，所以症状是「第一批正常、之后每批都卡」。
		$album_id = isset( $_POST['album_id'] ) ? absint( $_POST['album_id'] ) : 0;
		$offset   = isset( $_POST['w2p_offset'] ) ? absint( $_POST['w2p_offset'] ) : 0;
		// src_dir 是磁盘目录，同样不能用会折叠连续空白的 sanitize_text_field()（详见 ajax_browse）。
		$src_dir = isset( $_POST['src_dir'] ) ? W2P_Album_Importer::sanitize_abs_path( wp_unslash( $_POST['src_dir'] ) ) : '';

		if ( ! $album_id ) {
			wp_send_json_error( __( 'Invalid album ID.', 'wp-genius' ) );
		}

		if ( ! $this->is_path_allowed( $src_dir ) ) {
			wp_send_json_error( __( 'Source directory is outside the allowed scan root.', 'wp-genius' ) );
		}

		$result = W2P_Album_Importer::import_image_batch(
			$album_id,
			$src_dir,
			$offset,
			W2P_Album_Importer::DEFAULT_BATCH
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		// 本请求已到尾声，FPM 进程随后回收，运行时缓存不需要手动清。
		// 原 `wp_cache_flush()` 已移除，两个理由：
		//  ① 它是 flushDB —— 清的是**整个共享 Redis**，会把其它后台请求/其它标签页的缓存一起打掉；
		//  ② 一批图要跑 30~40s，期间 Redis 连接被服务端判为闲置；这里正好是那之后的**第一次**缓存操作，
		//     于是每批都稳定踩中 `RedisException: read error on connection`（只在错误日志里刷噪音，见 wp-config 的 graceful 设置）。
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: 丢弃一条导入记录（删文章 + 附件；源目录不动）。
	 *
	 * 给「中断后遗留的、状态永远停在 importing 的行」一个出口——这正是用户实测时卡住的场景。
	 */
	public function ajax_discard_album() {
		check_ajax_referer( 'w2p_album_importer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$album_id = isset( $_POST['album_id'] ) ? absint( $_POST['album_id'] ) : 0;

		if ( ! $album_id ) {
			wp_send_json_error( __( 'Invalid album ID.', 'wp-genius' ) );
		}

		$result = W2P_Album_Importer::discard_album( $album_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * 读取并校验前端提交的候选数据。
	 *
	 * @return array|WP_Error
	 */
	private function read_payload() {
		// 调用方（ajax_create_album）已执行 check_ajax_referer；本方法只负责读取与净化。
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce 由调用方校验。
		// 同 ajax_browse：abs_path 是磁盘路径，不能用会折叠连续空白的 sanitize_text_field()。
		$abs_path = isset( $_POST['abs_path'] ) ? W2P_Album_Importer::sanitize_abs_path( wp_unslash( $_POST['abs_path'] ) ) : '';

		if ( ! $this->is_path_allowed( $abs_path ) ) {
			return new WP_Error( 'w2p_album_path_denied', __( 'Source directory is outside the allowed scan root.', 'wp-genius' ) );
		}

		// 模特名单以 JSON 数组提交，服务端逐个用既有词条解析，不信任前端传来的词条 ID。
		$human_names = array();
		if ( ! empty( $_POST['human_names'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['human_names'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 逐个元素 sanitize_text_field。
			if ( is_array( $decoded ) ) {
				$human_names = array_filter( array_map( 'sanitize_text_field', $decoded ) );
			}
		}

		$payload = array(
			'abs_path'    => $abs_path,
			'title'       => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'number'      => isset( $_POST['number'] ) ? sanitize_text_field( wp_unslash( $_POST['number'] ) ) : '',
			'date'        => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
			'studio'      => isset( $_POST['studio'] ) ? sanitize_text_field( wp_unslash( $_POST['studio'] ) ) : '',
			'human_names' => array_values( $human_names ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $payload;
	}

	/**
	 * 路径白名单：必须落在配置的扫描根之内（realpath 前缀校验，防穿越）。
	 *
	 * @param string $path 待校验路径。
	 * @return bool
	 */
	private function is_path_allowed( $path ) {
		$path = W2P_Album_Importer::sanitize_abs_path( $path );
		if ( '' === $path ) {
			return false;
		}

		return W2P_Album_Importer::is_within( $path, W2P_Album_Importer::get_browse_root() );
	}

	/**
	 * 静态资产注册与参数注入
	 *
	 * @param string $hook 当前后台页面 hook。
	 */
	public function enqueue_admin_scripts( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'wp-genius-settings' ) ) {
			return;
		}

		$module_url = plugin_dir_url( __FILE__ );
		$css_file   = plugin_dir_path( __FILE__ ) . 'assets/css/admin.css';
		$js_file    = plugin_dir_path( __FILE__ ) . 'assets/js/album-importer.js';

		wp_enqueue_style(
			'w2p-album-importer-css',
			$module_url . 'assets/css/admin.css',
			array(),
			file_exists( $css_file ) ? filemtime( $css_file ) : W2P_VERSION
		);

		wp_enqueue_script(
			'w2p-album-importer-js',
			$module_url . 'assets/js/album-importer.js',
			array( 'jquery', 'w2p-admin-ui' ),
			file_exists( $js_file ) ? filemtime( $js_file ) : W2P_VERSION,
			true
		);

		wp_localize_script(
			'w2p-album-importer-js',
			'w2pAlbumParams',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'w2p_album_importer_nonce' ),
				'browseRoot' => W2P_Album_Importer::get_browse_root(),
				'i18n'       => array(
					'browsing'          => __( 'Opening directory...', 'wp-genius' ),
					'browseFailed'      => __( 'Failed to open the directory.', 'wp-genius' ),
					'emptyDir'          => __( 'No subdirectories here — this folder itself can be scanned.', 'wp-genius' ),
					'scopeSingle'       => __( 'This folder will be treated as ONE photo set.', 'wp-genius' ),
					/* translators: %s: number of photo sets in this folder */
					'scopeCollection'   => __( 'This folder will be expanded into %s photo sets.', 'wp-genius' ),
					'scanning'          => __( 'Scanning, please wait...', 'wp-genius' ),
					'scanEmpty'         => __( 'No photo sets found in this folder.', 'wp-genius' ),
					'scanFailed'        => __( 'Scan failed.', 'wp-genius' ),
					/* translators: 1: processed image count, 2: total image count */
					'importing'         => __( 'Importing images: %1$s / %2$s', 'wp-genius' ),
					'creatingAlbum'     => __( 'Creating album record...', 'wp-genius' ),
					'importDone'        => __( 'Import complete.', 'wp-genius' ),
					'importFailed'      => __( 'Import failed.', 'wp-genius' ),
					'importTimeout'     => __( 'The server did not respond in time. Sets already imported are kept — scan again to resume the rest.', 'wp-genius' ),
					'confirmImport'     => __( 'Import the selected sets now?', 'wp-genius' ),
					'nothingSelected'   => __( 'Please select at least one set.', 'wp-genius' ),
					'navFrozen'         => __( 'Please wait for the import to finish before changing directory.', 'wp-genius' ),
					'statusNew'         => __( 'New', 'wp-genius' ),
					'statusExists'      => __( 'Exists', 'wp-genius' ),
					'statusResume'      => __( 'Resume', 'wp-genius' ),
					'statusNoImage'     => __( 'No images', 'wp-genius' ),
					'statusReview'      => __( 'Needs review', 'wp-genius' ),
					'statusWarning'     => __( 'Warnings', 'wp-genius' ),
					'stop'              => __( 'Stop', 'wp-genius' ),
					'stopConfirm'       => __( 'Stop importing? Sets already finished stay imported; the one in progress can be resumed later.', 'wp-genius' ),
					'stopped'           => __( 'Stopped. Finished sets are kept — scan again to resume the rest.', 'wp-genius' ),
					'discard'           => __( 'Discard', 'wp-genius' ),
					'discardConfirm'    => __( 'Delete this album record and all images it imported? The source folder is NOT touched. This cannot be undone.', 'wp-genius' ),
					'discarded'         => __( 'Album record discarded.', 'wp-genius' ),
					'discardFailed'     => __( 'Failed to discard the album.', 'wp-genius' ),
					/* translators: 1: sets finished, 2: total sets */
					'progressSets'      => __( 'Sets: %1$s / %2$s', 'wp-genius' ),
					/* translators: 1: album title, 2: images done in it, 3: images in it */
					'progressCurrent'   => __( 'Current: %1$s — %2$s / %3$s', 'wp-genius' ),
					/* translators: %s: folder path the source was moved to */
					'sourceMoved'       => __( 'Source folder moved to %s', 'wp-genius' ),
					'sourceMoveFailed'  => __( 'Imported, but the source folder could not be moved.', 'wp-genius' ),
					'tagDesc'           => __( 'Looks like a description, not a model. Click to create it as a model anyway.', 'wp-genius' ),
					'studioNoTerm'      => __( 'No matching photostudio term; the studio will not be linked.', 'wp-genius' ),
					'studioNone'        => __( '— no studio —', 'wp-genius' ),
					/* translators: %s: studio name parsed from the directory name */
					'studioCreate'      => __( '＋ Create "%s"', 'wp-genius' ),
					'tagRemove'         => __( 'Remove', 'wp-genius' ),
					'tagLinked'         => __( 'Linked to an existing model.', 'wp-genius' ),
					'tagNotLinked'      => __( 'Not linked. Click to create it as a new model.', 'wp-genius' ),
					'tagWillCreate'     => __( 'Will be created as a new model.', 'wp-genius' ),
					'modelsNoTerm'      => __( 'Grey tokens have no matching humans term and will not be linked.', 'wp-genius' ),
					/* translators: %s: number of failed items */
					'importFailedCount' => __( '%s failed.', 'wp-genius' ),
					/* translators: %s: list of failed file names */
					'failedFiles'       => __( 'Failed files: %s', 'wp-genius' ),
				),
			)
		);
	}
}
