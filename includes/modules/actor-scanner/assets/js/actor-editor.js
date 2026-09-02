/**
 * WP-Genius: Actor Scanner Editor & Selected Bulk Progress Controller
 *
 * @package WP_Genius
 * @subpackage Modules/ActorScanner
 */

( function ( $ ) {
	'use strict';

	var ActorScanner = {
		isProcessing: false,
		cancelRequested: false,
		concurrency: 2,

		init: function () {
			if ( ! window.w2pActorEditor ) {
				return;
			}

			$( document ).ready( function () {
				if ( window.w2pActorEditor.pageType === 'list' ) {
					ActorScanner.initList();
				} else {
					ActorScanner.initEditor();
				}
			} );
		},

		// ==========================================
		//  Single Post Editor Flow
		// ==========================================

		initEditor: function () {
			var $metabox = $( '#tagsdiv-humans' );
			if ( ! $metabox.length || $( '#wpg-actor-detect-btn' ).length ) {
				return;
			}

			var $targetContainer = $metabox.find( '.hide-if-no-js' ).last();
			if ( ! $targetContainer.length ) {
				$targetContainer = $metabox.find( '.inside' );
			}

			var $container = $( '<span />', { class: 'wpg-actor-detect-container' } );
			var $btn = $( '<button />', {
				type: 'button',
				id: 'wpg-actor-detect-btn',
				class: 'button button-small wpg-actor-detect-btn',
				text: window.w2pActorEditor.i18n.buttonText || '识别人物',
			} );
			var $spinner = $( '<span />', { class: 'spinner wpg-actor-detect-spinner' } );
			var $feedback = $( '<span />', { id: 'wpg-actor-detect-feedback', class: 'wpg-actor-detect-feedback' } );

			$container.append( $btn ).append( $spinner );
			$targetContainer.append( $container ).append( $feedback );

			$btn.on( 'click', ActorScanner.handleSingleDetect );
		},

		getEditorContent: function () {
			if (
				window.tinyMCE &&
				typeof window.tinyMCE.get === 'function' &&
				window.tinyMCE.get( 'content' ) &&
				! window.tinyMCE.get( 'content' ).isHidden()
			) {
				return window.tinyMCE.get( 'content' ).getContent();
			}
			return $( '#content' ).val() || '';
		},

		getEditorPostId: function () {
			return parseInt( $( '#post_ID' ).val(), 10 ) || 0;
		},

		handleSingleDetect: function ( e ) {
			if ( e && typeof e.preventDefault === 'function' ) {
				e.preventDefault();
			}

			var $btn = $( '#wpg-actor-detect-btn' );
			var $spinner = $( '.wpg-actor-detect-spinner' );
			var $feedback = $( '#wpg-actor-detect-feedback' );

			$btn.prop( 'disabled', true );
			$spinner.addClass( 'is-active' );
			$feedback.removeClass( 'wpg-feedback-success wpg-feedback-error' ).text( '' );

			$.ajax( {
				url: window.w2pActorEditor.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'w2p_actor_detect_post',
					nonce: window.w2pActorEditor.nonce,
					post_id: ActorScanner.getEditorPostId(),
					content: ActorScanner.getEditorContent(),
				},
				success: function ( res ) {
					$btn.prop( 'disabled', false );
					$spinner.removeClass( 'is-active' );

					if ( res && res.success && res.data ) {
						$feedback.addClass( 'wpg-feedback-success' ).text( res.data.message || '识别成功' );
						if ( Array.isArray( res.data.terms ) && res.data.terms.length ) {
							ActorScanner.appendTerms( res.data.terms );
						}
					} else {
						var errMsg = ( res && res.data && res.data.message ) ? res.data.message : ( window.w2pActorEditor.i18n.error || '未能识别到人物' );
						$feedback.addClass( 'wpg-feedback-error' ).text( errMsg );
					}
				},
				error: function ( jqXHR, status, errorThrown ) {
					$btn.prop( 'disabled', false );
					$spinner.removeClass( 'is-active' );
					$feedback.addClass( 'wpg-feedback-error' ).text( errorThrown || '网络错误' );
				},
			} );
		},

		appendTerms: function ( terms ) {
			if ( ! Array.isArray( terms ) ) {
				return;
			}
			terms.forEach( function ( term ) {
				if ( term && term.name ) {
					var $newTag = $( '#new-tag-humans' );
					var $tagAddBtn = $( '#humans .tagadd, #tagsdiv-humans .tagadd' );
					if ( $newTag.length && $tagAddBtn.length ) {
						$newTag.val( term.name );
						$tagAddBtn.trigger( 'click' );
					}
				}
			} );
		},

		// ==========================================
		//  Post List (edit.php) Selected Bulk Flow
		// ==========================================

		initList: function () {
			ActorScanner.bindListEvents();
		},

		bindListEvents: function () {
			// Trigger: Selected Bulk Action from dropdown
			$( document ).on( 'click', '#doaction, #doaction2', function ( e ) {
				var selectName = ( this.id === 'doaction' ) ? 'action' : 'action2';
				if ( $( 'select[name="' + selectName + '"]' ).val() === 'w2p_actor_detect' ) {
					e.preventDefault();
					e.stopImmediatePropagation();
					ActorScanner.startManualBulk();
					return false;
				}
			} );

			$( document ).on( 'click', '#w2p-actor-close-btn, #w2p-actor-cancel-btn', function () {
				ActorScanner.cancelOrClose();
			} );

			$( document ).on( 'click', '#w2p-actor-done-btn', function () {
				location.reload();
			} );
		},

		getSelectedIds: function () {
			var ids = [];
			var $checked = $( 'input[name="post[]"]:checked, input[name="media[]"]:checked, input[name="ids[]"]:checked, tbody input[type="checkbox"]:checked, input[id^="cb-select-"]:checked' );

			$checked.each( function () {
				var id = parseInt( $( this ).val(), 10 );
				if ( id > 0 && ! isNaN( id ) && ids.indexOf( id ) === -1 ) {
					ids.push( id );
				}
			} );
			return ids;
		},

		updateStats: function ( total, success, skipped, failed, active, max ) {
			$( '#w2p-actor-total' ).text( total );
			$( '#w2p-actor-success' ).text( success );
			$( '#w2p-actor-skipped' ).text( skipped );
			$( '#w2p-actor-failed' ).text( failed );
			$( '#w2p-actor-active-threads' ).text( active !== undefined ? active : 0 );
			$( '#w2p-actor-threads' ).text( max !== undefined ? max : ActorScanner.concurrency );

			var processed = success + skipped + failed;
			var pct = total > 0 ? Math.round( ( processed / total ) * 100 ) : 0;
			$( '.w2p-actor-progress-fill' ).css( 'width', pct + '%' );
		},

		updateStatus: function ( text ) {
			$( '.w2p-actor-status-text' ).text( text );
		},

		appendLog: function ( iconClass, message ) {
			var $li = $( '<li />' )
				.append( $( '<span />', { class: 'dashicons ' + iconClass } ) )
				.append( $( '<span />', { text: message } ) );

			var $list = $( '#w2p-actor-log-list' );
			$list.append( $li );

			var $container = $( '#w2p-actor-preview-area' );
			if ( $container.length ) {
				$container.scrollTop( $container[0].scrollHeight );
			}
		},

		// Manual Bulk Detection (Selected Checkboxes)
		startManualBulk: function () {
			var postIds = ActorScanner.getSelectedIds();

			if ( ! postIds.length ) {
				alert( window.w2pActorEditor.i18n.noSelection || '请先勾选需要识别人物的文章！' );
				return;
			}

			$( '#w2p-actor-modal-title' ).text( '识别人物（选定文章）' );
			ActorScanner.runQueue( postIds );
		},

		// Core Queue Runner
		runQueue: function ( postIds ) {
			ActorScanner.isProcessing = true;
			ActorScanner.cancelRequested = false;

			// Reset UI & Show Modal
			$( '#w2p-actor-backdrop' ).removeClass( 'w2p-hidden' );
			$( '#w2p-actor-log-list' ).empty();
			$( '#w2p-actor-done-btn' ).addClass( 'w2p-hidden' );
			$( '#w2p-actor-cancel-btn' ).removeClass( 'w2p-hidden' );

			var total = postIds.length;
			var successCount = 0;
			var skippedCount = 0;
			var failedCount = 0;
			var processedCount = 0;
			var activeWorkers = 0;
			var queue = postIds.slice();

			ActorScanner.updateStats( total, 0, 0, 0, 0, ActorScanner.concurrency );
			ActorScanner.updateStatus( window.w2pActorEditor.i18n.processing || '正在识别人物...' );

			var processNext = function () {
				if ( ActorScanner.cancelRequested || ! ActorScanner.isProcessing ) {
					return;
				}

				if ( queue.length === 0 && activeWorkers === 0 ) {
					ActorScanner.isProcessing = false;
					ActorScanner.updateStatus( window.w2pActorEditor.i18n.completed || '识别任务全部完成！' );
					$( '#w2p-actor-cancel-btn' ).addClass( 'w2p-hidden' );
					$( '#w2p-actor-done-btn' ).removeClass( 'w2p-hidden' );

					setTimeout( function () {
						if ( ! ActorScanner.cancelRequested ) {
							location.reload();
						}
					}, 1800 );
					return;
				}

				while ( activeWorkers < ActorScanner.concurrency && queue.length > 0 ) {
					var currentId = queue.shift();
					activeWorkers++;

					var $rowTitle = $( '#post-' + currentId + ' .row-title, tr[id*="-' + currentId + '"] .row-title, #post-' + currentId + ' td.column-title a, tr[id*="-' + currentId + '"] td.column-title a' );
					var postTitle = $rowTitle.length ? $rowTitle.first().text().trim() : ( '#' + currentId );

					ActorScanner.updateStatus(
						'[' + ( processedCount + 1 ) + '/' + total + '] 正在识别：' + postTitle
					);
					ActorScanner.updateStats( total, successCount, skippedCount, failedCount, activeWorkers, ActorScanner.concurrency );

					( function ( postId, title ) {
						$.ajax( {
							url: window.w2pActorEditor.ajaxUrl,
							type: 'POST',
							dataType: 'json',
							data: {
								action: 'w2p_actor_detect_post',
								nonce: window.w2pActorEditor.nonce,
								post_id: postId,
							},
							success: function ( response ) {
								processedCount++;
								activeWorkers--;

								if ( response && response.success && response.data ) {
									var data = response.data;
									if ( Array.isArray( data.terms ) && data.terms.length ) {
										successCount++;
										var names = data.terms.map( function ( t ) {
											return t.name;
										} ).join( '、' );
										ActorScanner.appendLog( 'dashicons-yes', '[' + title + '] ' + names );
									} else {
										skippedCount++;
										ActorScanner.appendLog( 'dashicons-warning', '[' + title + '] 未提取到人物名字' );
									}
								} else {
									failedCount++;
									var errMsg = ( response && response.data && response.data.message ) ? response.data.message : '未能识别到人物';
									ActorScanner.appendLog( 'dashicons-no', '[' + title + '] ' + errMsg );
								}

								ActorScanner.updateStats( total, successCount, skippedCount, failedCount, activeWorkers, ActorScanner.concurrency );
								processNext();
							},
							error: function ( jqXHR, status, errorThrown ) {
								processedCount++;
								activeWorkers--;
								failedCount++;
								ActorScanner.appendLog( 'dashicons-no', '[' + title + '] 网络错误: ' + ( errorThrown || status ) );
								ActorScanner.updateStats( total, successCount, skippedCount, failedCount, activeWorkers, ActorScanner.concurrency );
								processNext();
							},
						} );
					} )( currentId, postTitle );
				}
			};

			processNext();
		},

		cancelOrClose: function () {
			if ( ActorScanner.isProcessing ) {
				if ( confirm( window.w2pActorEditor.i18n.confirmCancel || '确定要停止识别吗？已处理的文章将被保留。' ) ) {
					ActorScanner.cancelRequested = true;
					ActorScanner.isProcessing = false;
					$( '#w2p-actor-backdrop' ).addClass( 'w2p-hidden' );
					location.reload();
				}
			} else {
				$( '#w2p-actor-backdrop' ).addClass( 'w2p-hidden' );
			}
		},
	};

	ActorScanner.init();
} )( jQuery );
