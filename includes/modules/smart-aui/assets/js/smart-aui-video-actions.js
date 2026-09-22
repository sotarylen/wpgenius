/**
 * Smart AUI — Frontend Video Action Buttons
 * Supports Plyr player container and native HTML5 video with zero intrusion.
 */
(function () {
    'use strict';

    if (typeof window.w2pSmartAuiVideo === 'undefined') {
        return;
    }

    var config = window.w2pSmartAuiVideo;
    var i18n = config.i18n || {};
    var siteUrl = config.site_url || window.location.origin;
    var baseUrl = (config.base_url || siteUrl).replace(/\/$/, '');

    /**
     * Create SVG Icon Element using standard DOM API
     */
    function createSvgIcon(type) {
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.classList.add('w2p-video-icon');

        if (type === 'download') {
            var p1 = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            p1.setAttribute('d', 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4');
            var poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
            poly.setAttribute('points', '7 10 12 15 17 10');
            var l = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            l.setAttribute('x1', '12');
            l.setAttribute('y1', '15');
            l.setAttribute('x2', '12');
            l.setAttribute('y2', '3');
            svg.appendChild(p1);
            svg.appendChild(poly);
            svg.appendChild(l);
        } else if (type === 'remove') {
            var poly2 = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
            poly2.setAttribute('points', '3 6 5 6 21 6');
            var p2 = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            p2.setAttribute('d', 'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2');
            svg.appendChild(poly2);
            svg.appendChild(p2);
        } else if (type === 'spin') {
            svg.classList.add('w2p-spin');
            var p3 = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            p3.setAttribute('d', 'M21 12a9 9 0 1 1-6.219-8.56');
            svg.appendChild(p3);
        }

        return svg;
    }

    /**
     * Get the active video source URL (clean without query params)
     */
    function getVideoSrc(videoEl) {
        var src = '';
        if (videoEl.currentSrc) {
            src = videoEl.currentSrc;
        } else if (videoEl.src) {
            src = videoEl.src;
        } else {
            var source = videoEl.querySelector('source');
            if (source && source.src) {
                src = source.src;
            }
        }
        if (src) {
            src = src.replace(/\?_=\d+$/, '').trim();
        }
        return src;
    }

    /**
     * Check if a video URL is locally hosted
     */
    function isLocalVideo(src, videoEl) {
        if (!src) return false;
        if (videoEl.dataset.id) return true;
        if (videoEl.className && /wp-video-\d+/.test(videoEl.className)) return true;
        if (src.indexOf(siteUrl) === 0 || src.indexOf(baseUrl) === 0) return true;
        if (src.indexOf('/wp-content/') === 0 || src.indexOf('/wp-media/') === 0) return true;
        return false;
    }

    /**
     * Update button state with text and icon
     */
    function setButtonState(btn, state) {
        while (btn.firstChild) {
            btn.removeChild(btn.firstChild);
        }

        btn.classList.remove('is-download', 'is-remove', 'is-loading');

        var iconType = 'download';
        var labelText = i18n.download || 'Download';

        if (state === 'download') {
            btn.classList.add('is-download');
            iconType = 'download';
            labelText = i18n.download || 'Download';
        } else if (state === 'remove') {
            btn.classList.add('is-remove');
            iconType = 'remove';
            labelText = i18n.remove || 'Remove';
        } else if (state === 'downloading') {
            btn.classList.add('is-download', 'is-loading');
            iconType = 'spin';
            labelText = i18n.downloading || 'Downloading...';
        } else if (state === 'removing') {
            btn.classList.add('is-remove', 'is-loading');
            iconType = 'spin';
            labelText = i18n.removing || 'Removing...';
        }

        var icon = createSvgIcon(iconType);
        var textNode = document.createTextNode(labelText);

        btn.appendChild(icon);
        btn.appendChild(textNode);
        btn.dataset.state = state;
    }

    /**
     * Create Action Button and Bind Handlers
     */
    function createActionButton(videoEl) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.classList.add('w2p-video-action-btn');

        var initialSrc = getVideoSrc(videoEl);
        var initialLocal = isLocalVideo(initialSrc, videoEl);

        setButtonState(btn, initialLocal ? 'remove' : 'download');

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var currentState = btn.dataset.state;
            var currentSrc = getVideoSrc(videoEl);

            if (currentState === 'download') {
                if (!currentSrc) {
                    alert(i18n.error || 'Video URL not found.');
                    return;
                }

                setButtonState(btn, 'downloading');

                var formData = new FormData();
                formData.append('action', 'w2p_smart_aui_download_video');
                formData.append('nonce', config.nonce);
                formData.append('post_id', config.post_id);
                formData.append('video_url', currentSrc);
                formData.append('update_post', '1');

                fetch(config.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                    .then(function (res) { return res.json(); })
                    .then(function (res) {
                        if (res.success && res.data && res.data.downloaded_url) {
                            var newUrl = res.data.downloaded_url;
                            if (videoEl.src) {
                                videoEl.src = newUrl;
                            }
                            var srcChild = videoEl.querySelector('source');
                            if (srcChild) {
                                srcChild.src = newUrl;
                            }
                            videoEl.load();

                            if (res.data.attachment_id) {
                                videoEl.dataset.id = res.data.attachment_id;
                                videoEl.classList.add('wp-video-' + res.data.attachment_id);
                            }

                            setButtonState(btn, 'remove');
                        } else {
                            setButtonState(btn, 'download');
                            var msg = (res.data && res.data.message) ? res.data.message : (i18n.error || 'Download failed');
                            alert(msg);
                        }
                    })
                    .catch(function () {
                        setButtonState(btn, 'download');
                        alert(i18n.error || 'Network error.');
                    });

            } else if (currentState === 'remove') {
                var confirmMsg = i18n.confirmRemove || 'Remove this video from media library? Local file will be deleted.';
                if (!window.confirm(confirmMsg)) {
                    return;
                }

                setButtonState(btn, 'removing');

                var removeData = new FormData();
                removeData.append('action', 'w2p_smart_aui_remove_video');
                removeData.append('nonce', config.nonce);
                removeData.append('post_id', config.post_id);
                removeData.append('video_url', currentSrc);
                if (videoEl.dataset.id) {
                    removeData.append('attachment_id', videoEl.dataset.id);
                }

                fetch(config.ajax_url, {
                    method: 'POST',
                    body: removeData
                })
                    .then(function (res) { return res.json(); })
                    .then(function (res) {
                        if (res.success && res.data) {
                            var restoredUrl = res.data.original_url || '';
                            if (restoredUrl) {
                                if (videoEl.src) {
                                    videoEl.src = restoredUrl;
                                }
                                var srcChild = videoEl.querySelector('source');
                                if (srcChild) {
                                    srcChild.src = restoredUrl;
                                }
                                videoEl.load();
                            }

                            delete videoEl.dataset.id;
                            if (videoEl.className) {
                                videoEl.className = videoEl.className.replace(/\bwp-video-\d+\b/g, '').trim();
                            }

                            setButtonState(btn, 'download');
                        } else {
                            setButtonState(btn, 'remove');
                            var msg = (res.data && res.data.message) ? res.data.message : (i18n.error || 'Removal failed');
                            alert(msg);
                        }
                    })
                    .catch(function () {
                        setButtonState(btn, 'remove');
                        alert(i18n.error || 'Network error.');
                    });
            }
        });

        return btn;
    }

    /**
     * Mount action button to video (either in Plyr container or native wrapper)
     */
    function attachVideoAction(videoEl) {
        if (!videoEl) return;

        // Check if already inside a Plyr player (DOM closest or Plyr instance container)
        var plyrContainer = videoEl.closest('.plyr') || (videoEl.plyr && videoEl.plyr.elements && videoEl.plyr.elements.container ? videoEl.plyr.elements.container : null);

        if (plyrContainer) {
            if (plyrContainer.querySelector(':scope > .w2p-video-action-btn')) {
                return;
            }
            // Remove any obsolete external wrapper button
            var oldWrapperBtn = plyrContainer.parentElement ? plyrContainer.parentElement.querySelector(':scope > .w2p-video-action-btn') : null;
            if (oldWrapperBtn) {
                oldWrapperBtn.remove();
            }

            var btn = createActionButton(videoEl);
            plyrContainer.appendChild(btn);
            videoEl.dataset.w2pActionMounted = 'true';
            return;
        }

        // If Plyr might be initializing, wait for it
        if (typeof window.Plyr !== 'undefined' || typeof window.wpgVideoConfig !== 'undefined') {
            setTimeout(function () {
                var p = videoEl.closest('.plyr') || (videoEl.plyr && videoEl.plyr.elements && videoEl.plyr.elements.container ? videoEl.plyr.elements.container : null);
                if (p) {
                    attachVideoAction(videoEl);
                } else if (!videoEl.dataset.w2pActionMounted) {
                    mountNativeWrapper(videoEl);
                }
            }, 300);
            return;
        }

        mountNativeWrapper(videoEl);
    }

    /**
     * Fallback: Mount inside a native wrapper
     */
    function mountNativeWrapper(videoEl) {
        if (videoEl.dataset.w2pActionMounted) return;
        videoEl.dataset.w2pActionMounted = 'true';

        var parent = videoEl.parentElement;
        var wrapper;

        if (parent && parent.classList.contains('w2p-video-action-wrapper')) {
            wrapper = parent;
        } else {
            wrapper = document.createElement('div');
            wrapper.classList.add('w2p-video-action-wrapper');
            parent.insertBefore(wrapper, videoEl);
            wrapper.appendChild(videoEl);
        }

        var btn = createActionButton(videoEl);
        wrapper.appendChild(btn);
    }

    /**
     * Scan and mount for all videos
     */
    function scanAndMount() {
        var plyrContainers = document.querySelectorAll('.plyr');
        for (var i = 0; i < plyrContainers.length; i++) {
            var v = plyrContainers[i].querySelector('video');
            if (v) attachVideoAction(v);
        }

        var videos = document.querySelectorAll('video');
        for (var j = 0; j < videos.length; j++) {
            attachVideoAction(videos[j]);
        }
    }

    // 1. Observe dynamic Plyr insertions
    if (window.MutationObserver) {
        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var mutation = mutations[i];
                for (var j = 0; j < mutation.addedNodes.length; j++) {
                    var node = mutation.addedNodes[j];
                    if (node.nodeType === 1) {
                        if (node.classList && node.classList.contains('plyr')) {
                            var vid = node.querySelector('video');
                            if (vid) attachVideoAction(vid);
                        } else if (node.querySelector) {
                            var plyrs = node.querySelectorAll('.plyr');
                            for (var k = 0; k < plyrs.length; k++) {
                                var v = plyrs[k].querySelector('video');
                                if (v) attachVideoAction(v);
                            }
                        }
                    }
                }
            }
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }

    // 2. Listen to Plyr ready event (can fire on <video> or container .plyr)
    document.addEventListener('ready', function (e) {
        if (!e.target) return;
        if (e.target.nodeName === 'VIDEO') {
            attachVideoAction(e.target);
        } else if (e.target.classList && e.target.classList.contains('plyr')) {
            var vid = e.target.querySelector('video');
            if (vid) attachVideoAction(vid);
        }
    }, true);

    // 3. Multi-phase scan on DOM ready to eliminate race conditions
    function initScanSchedule() {
        scanAndMount();
        setTimeout(scanAndMount, 200);
        setTimeout(scanAndMount, 600);
        setTimeout(scanAndMount, 1200);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initScanSchedule);
    } else {
        initScanSchedule();
    }
})();
