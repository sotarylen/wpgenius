/**
 * Actor Scanner: Deduplication & Governance Tool Controller
 *
 * @package WP_Genius
 * @subpackage Modules\ActorScanner
 */

( function ( $ ) {
	'use strict';

	var ActorGovernance = {
		clusters: [],

		init: function () {
			if ( ! window.w2pActorScanner ) {
				return;
			}

			$( document ).ready( function () {
				ActorGovernance.bindEvents();
			} );
		},

		bindEvents: function () {
			$( '#actor-refresh-gf-btn' ).on( 'click', ActorGovernance.handleRefreshGf );
			$( '#actor-scan-dupes-btn' ).on( 'click', ActorGovernance.handleScanDupes );
			$( '#actor-merge-dupes-btn' ).on( 'click', ActorGovernance.handleMergeDupes );
		},

		handleRefreshGf: function ( e ) {
			e.preventDefault();
			var $btn = $( '#actor-refresh-gf-btn' );
			$btn.prop( 'disabled', true ).find( 'i' ).addClass( 'fa-spin' );

			$.ajax( {
				url: window.w2pActorScanner.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'w2p_actor_prepare_index',
					nonce: window.w2pActorScanner.nonce,
					force: 1,
				},
				success: function ( res ) {
					$btn.prop( 'disabled', false ).find( 'i' ).removeClass( 'fa-spin' );
					if ( res && res.success && res.data ) {
						$( '#actor-gf-status .w2p-status-label' ).html(
							'<i class="fa-solid fa-circle-check" style="color:var(--w2p-color-success);"></i> ' +
							'官方女优/演员索引已刷新：' + res.data.actors + ' 位'
						);
						alert( window.w2pActorScanner.i18n.indexReady || 'Gfriends 索引同步完成！' );
					}
				},
				error: function () {
					$btn.prop( 'disabled', false ).find( 'i' ).removeClass( 'fa-spin' );
					alert( '网络错误，刷新索引失败。' );
				},
			} );
		},

		handleScanDupes: function ( e ) {
			e.preventDefault();
			var $btn = $( '#actor-scan-dupes-btn' );
			var $status = $( '#actor-dedupe-status-text' );

			$btn.prop( 'disabled', true ).find( 'i' ).addClass( 'fa-spin' );
			$status.html( '<i class="fa-solid fa-spinner fa-spin"></i> ' + ( window.w2pActorScanner.i18n.scanning || '正在扫描全库重复人物...' ) );
			$( '#actor-dupes-table-wrapper' ).hide();
			$( '#actor-merge-log-wrapper' ).hide();
			$( '#actor-merge-dupes-btn' ).hide();

			$.ajax( {
				url: window.w2pActorScanner.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'w2p_actor_dedupe_scan',
					nonce: window.w2pActorScanner.nonce,
				},
				success: function ( res ) {
					$btn.prop( 'disabled', false ).find( 'i' ).removeClass( 'fa-spin' );
					if ( res && res.success && res.data ) {
						ActorGovernance.clusters = res.data.clusters || [];
						var count = res.data.total_clusters || 0;

						if ( count === 0 ) {
							$status.html( '<i class="fa-solid fa-circle-check" style="color:var(--w2p-color-success);"></i> ' + ( window.w2pActorScanner.i18n.noDuplicates || '太棒了！全库未发现重复的人物条目。' ) );
						} else {
							$status.html(
								'<i class="fa-solid fa-triangle-exclamation" style="color:var(--w2p-color-warning);"></i> ' +
								( window.w2pActorScanner.i18n.foundPrefix || '扫描完成，共发现 ' ) +
								'<strong>' + count + '</strong>' +
								( window.w2pActorScanner.i18n.foundSuffix || ' 组重复人物条目。' )
							);
							ActorGovernance.renderDupesTable( ActorGovernance.clusters );
							$( '#actor-merge-dupes-btn' ).show();
						}
					} else {
						$status.text( '扫描失败：' + ( ( res && res.data && res.data.message ) ? res.data.message : '未知错误' ) );
					}
				},
				error: function ( jqXHR, status, errorThrown ) {
					$btn.prop( 'disabled', false ).find( 'i' ).removeClass( 'fa-spin' );
					$status.text( '网络请求失败：' + ( errorThrown || status ) );
				},
			} );
		},

		renderDupesTable: function ( clusters ) {
			var $tbody = $( '#actor-dupes-tbody' );
			$tbody.empty();

			clusters.forEach( function ( c, idx ) {
				var typeLabel = ( c.type === 'exact_name' ) ? '同名重复' : '同人别名';
				var winnerStr = '[' + c.winner.term_id + '] <strong>' + c.winner.name + '</strong> (文章数: ' + c.winner.count + ')';

				var dupsStr = c.duplicates.map( function ( d ) {
					return '[' + d.term_id + '] ' + d.name + ' (文章数: ' + d.count + ')';
				} ).join( '<br>' );

				var nickStr = c.plan.merged_nickname || '—';

				var $tr = $( '<tr />' )
					.append( $( '<td />', { text: idx + 1 } ) )
					.append( $( '<td />', { text: typeLabel } ) )
					.append( $( '<td />', { html: winnerStr } ) )
					.append( $( '<td />', { html: dupsStr, style: 'color:var(--w2p-color-error);' } ) )
					.append( $( '<td />', { text: nickStr } ) );

				$tbody.append( $tr );
			} );

			$( '#actor-dupes-table-wrapper' ).show();
		},

		handleMergeDupes: function ( e ) {
			e.preventDefault();

			if ( ! confirm( window.w2pActorScanner.i18n.confirmMerge || '确定要对扫描出的重复人物执行一键合并吗？此操作将自动迁移文章关联并删除多余条目。' ) ) {
				return;
			}

			var $btn = $( '#actor-merge-dupes-btn' );
			var $status = $( '#actor-dedupe-status-text' );

			$btn.prop( 'disabled', true ).find( 'i' ).addClass( 'fa-spin' );
			$status.html( '<i class="fa-solid fa-spinner fa-spin"></i> ' + ( window.w2pActorScanner.i18n.merging || '正在合并重复人物并重挂载文章...' ) );

			$.ajax( {
				url: window.w2pActorScanner.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'w2p_actor_dedupe_merge',
					nonce: window.w2pActorScanner.nonce,
				},
				success: function ( res ) {
					$btn.prop( 'disabled', false ).find( 'i' ).removeClass( 'fa-spin' );
					if ( res && res.success && res.data ) {
						var d = res.data;
						$status.html(
							'<i class="fa-solid fa-circle-check" style="color:var(--w2p-color-success);"></i> ' +
							( window.w2pActorScanner.i18n.mergeSuccess || '重复人物合并完成！' ) +
							' 共合并 <strong>' + d.clusters_merged + '</strong> 组人物，' +
							'平滑重挂载 <strong>' + d.posts_migrated + '</strong> 篇文章，' +
							'彻底清理删除 <strong>' + d.deleted_terms + '</strong> 个冗余分类项。'
						);

						$( '#actor-dupes-table-wrapper' ).hide();
						$( '#actor-merge-dupes-btn' ).hide();

						// Render Detailed Report
						if ( Array.isArray( d.log ) && d.log.length ) {
							var $list = $( '#actor-merge-log-list' ).empty();
							d.log.forEach( function ( l ) {
								$list.append( $( '<li />', {
									text: '已合并至 [' + l.winner_id + '] ' + l.winner_name + '：重挂载 ' + l.posts_migrated + ' 篇文章，清理删除：' + ( l.deleted_names || [] ).join( ', ' ),
								} ) );
							} );
							$( '#actor-merge-log-wrapper' ).show();
						}
					} else {
						$status.text( '合并失败：' + ( ( res && res.data && res.data.message ) ? res.data.message : '未知错误' ) );
					}
				},
				error: function ( jqXHR, status, errorThrown ) {
					$btn.prop( 'disabled', false ).find( 'i' ).removeClass( 'fa-spin' );
					$status.text( '网络请求失败：' + ( errorThrown || status ) );
				},
			} );
		},
	};

	ActorGovernance.init();
} )( jQuery );
