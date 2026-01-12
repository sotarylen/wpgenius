/**
 * WP Genius Lightbox
 * Frontend image viewer with advanced features
 * 
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */

(function ($) {
    'use strict';

    /**
     * Lightbox Class
     */
    class WPGeniusLightbox {
        constructor(config) {
            this.config = config;
            this.settings = config.settings || {};
            this.i18n = config.i18n || {};
            this.postId = config.postId || 0;
            this.ajaxUrl = config.ajaxUrl || '';
            this.nonce = config.nonce || '';

            // State
            this.images = [];
            this.currentIndex = 0;
            this.zoomLevel = 1;
            this.isPlaying = false;
            this.playInterval = null;
            this.overlay = null;

            // Drag state
            this.isDragging = false;
            this.dragStart = { x: 0, y: 0 };
            this.dragOffset = { x: 0, y: 0 };
            this.currentTranslate = { x: 0, y: 0 };

            this.init();
        }

        /**
         * Initialize Lightbox
         */
        init() {


            // Create overlay DOM structure
            this.createOverlay();


            // Collect images from content
            this.collectImages();

            // Bind events
            // Bind events
            this.bindEvents();
        }

        /**
         * Create overlay DOM
         */
        createOverlay() {
            const animClass = 'anim-' + (this.settings.lightbox_animation || 'fade');

            this.overlay = $('<div>', {
                'class': 'wpg-lightbox-overlay ' + animClass
            });

            // Close button (with Font Awesome icon)
            const closeBtn = $('<button>', {
                'class': 'wpg-lightbox-close',
                'html': '<i class="fas fa-times"></i>',
                'title': this.i18n.close || 'Close'
            });

            // Content container
            const content = $('<div>', {
                'class': 'wpg-lightbox-content'
            });

            // Navigation arrows (with Font Awesome icons)
            const prevBtn = $('<button>', {
                'class': 'wpg-lightbox-nav wpg-lightbox-nav-prev',
                'html': '<i class="fas fa-chevron-left"></i>',
                'title': this.i18n.prev || 'Previous'
            });

            const nextBtn = $('<button>', {
                'class': 'wpg-lightbox-nav wpg-lightbox-nav-next',
                'html': '<i class="fas fa-chevron-right"></i>',
                'title': this.i18n.next || 'Next'
            });

            // Toolbar with counter
            const toolbar = this.createToolbar();

            // Append elements (no counter separately - it's in toolbar)
            this.overlay.append(closeBtn, prevBtn, nextBtn, content, toolbar);
            $('body').append(this.overlay);

            // Store references
            this.$close = closeBtn;
            this.$prevBtn = prevBtn;
            this.$nextBtn = nextBtn;
            this.$content = content;
            this.$toolbar = toolbar;
        }

        /**
         * Create toolbar with counter and buttons
         */
        createToolbar() {
            const toolbar = $('<div>', {
                'class': 'wpg-lightbox-toolbar'
            });

            const buttons = [];

            // Counter (added to toolbar)
            const counter = $('<div>', {
                'class': 'wpg-lightbox-counter'
            });
            buttons.push(counter);
            this.$counter = counter; // Store reference

            // Zoom controls
            if (this.settings.lightbox_zoom_enabled) {
                buttons.push(
                    this.createButton('zoom-in', this.i18n.zoomIn || 'Zoom In', '<i class="fas fa-search-plus"></i>'),
                    this.createButton('zoom-out', this.i18n.zoomOut || 'Zoom Out', '<i class="fas fa-search-minus"></i>'),
                    this.createButton('zoom-reset', this.i18n.zoomReset || 'Reset', '<i class="fas fa-compress"></i>')
                );
            }

            // Set as featured
            if (this.settings.lightbox_allow_set_featured && wpgLightboxConfig.postId) {
                buttons.push(
                    this.createButton('set-featured', this.i18n.setFeatured || 'Set Featured', '<i class="fas fa-star"></i>')
                );
            }

            // Autoplay
            if (this.settings.lightbox_autoplay_enabled) {
                buttons.push(
                    this.createButton('autoplay', this.i18n.autoplay || 'Autoplay', '<i class="fas fa-play"></i>')
                );
            }

            // Delete Image (Admin only + must be enabled in settings)
            if (this.settings.lightbox_allow_delete && wpgLightboxConfig.canDelete) {
                buttons.push(
                    this.createButton('delete', this.i18n.deleteImage || 'Delete Image', '<i class="fas fa-trash-alt"></i>')
                );
            }

            toolbar.append(buttons);
            return toolbar;
        }

        /**
         * Create toolbar button
         */
        createButton(action, title, label) {
            return $('<button>', {
                'class': 'wpg-lightbox-btn',
                'data-action': action,
                'title': title,
                'html': label
            });
        }

        /**
         * Collect images from content (only from article area)
         */
        collectImages() {
            const self = this;
            this.images = []; // Reset

            // Priority 1: Custom container with specific ID (Strict Mode)
            let $articleContainer = $('#w2p-post-content').first();

            if ($articleContainer.length === 0) {
                const fallbacks = ['.entry-content', '.post-content', 'article.post', 'article[id^="post-"]'];
                for (const selector of fallbacks) {
                    const $found = $(selector).first();
                    if ($found.length > 0) {
                        $articleContainer = $found;
                        break;
                    }
                }
            }

            if ($articleContainer.length === 0) {
                return;
            }

            $articleContainer.find('img').each(function () {
                const $img = $(this);

                // Skip if hidden or specifically excluded
                if ($img.hasClass('no-lightbox') || !$img.is(':visible')) {
                    return;
                }

                const src = $img.attr('src');
                const dataSrc = $img.attr('data-src') || $img.attr('data-original') || $img.attr('data-lazy-src');
                const alt = $img.attr('alt') || '';

                // Try multiple methods to get attachment ID
                let attachmentId = $img.attr('data-id') ||
                    $img.attr('data-attachment-id') ||
                    $img.closest('[data-id]').attr('data-id') ||
                    $img.closest('[data-attachment-id]').attr('data-attachment-id') ||
                    0;

                // Try to extract ID from WordPress image classes (wp-image-{ID})
                if (!attachmentId) {
                    const classMatch = $img.attr('class')?.match(/wp-image-(\d+)/);
                    if (classMatch) {
                        attachmentId = classMatch[1];
                    }
                }

                // Get full size URL if available
                let fullSrc = dataSrc || src;
                const $link = $img.closest('a');

                // If wrapped in a link pointing to an image, use it as full size
                if ($link.length && $link.attr('href') && $link.attr('href').match(/\.(jpg|jpeg|png|gif|webp)$/i)) {
                    fullSrc = $link.attr('href');
                }

                self.images.push({
                    src: fullSrc,
                    thumb: src || dataSrc,
                    alt: alt,
                    attachmentId: attachmentId,
                    element: $img[0],
                    loaded: false
                });
            });
        }

        /**
         * Bind events
         */
        bindEvents() {
            const self = this;

            // Image click - respond to all images (even without links)
            // Priority selector: custom container ID first, then fallbacks
            const clickSelector = '#w2p-post-content img, body.single img, body.single-post img, body.page img, .entry-content img, .post-content img, article.post img, article[id^="post-"] img, article.w-grid-item img';

            $(document).on('click', clickSelector, function (e) {

                e.preventDefault();
                e.stopPropagation(); // Prevent link navigation

                const index = self.images.findIndex(img => img.element === this);


                if (index >= 0) {
                    self.open(index);
                }
            });

            // Close button
            this.$close.on('click', () => this.close());

            // Backdrop click
            if (this.settings.lightbox_close_on_backdrop) {
                this.overlay.on('click', (e) => {
                    if ($(e.target).hasClass('wpg-lightbox-overlay')) {
                        this.close();
                    }
                });
            }

            // Navigation
            this.$prevBtn.on('click', () => this.prev());
            this.$nextBtn.on('click', () => this.next());

            // Keyboard navigation
            if (this.settings.lightbox_keyboard_nav) {
                $(document).on('keydown.lightbox', (e) => {
                    if (!this.overlay.hasClass('active')) return;

                    switch (e.key) {
                        case 'Escape':
                            this.close();
                            break;
                        case 'ArrowLeft':
                            this.prev();
                            break;
                        case 'ArrowRight':
                            this.next();
                            break;
                    }
                });
            }

            // Toolbar buttons
            this.$toolbar.on('click', '.wpg-lightbox-btn', function (e) {
                e.preventDefault();
                const action = $(this).attr('data-action');
                self.handleToolbarAction(action, $(this));
            });

            // Mouse wheel zoom/navigate - attached to overlay instead of content
            this.overlay.on('wheel', (e) => {
                if (!this.overlay.hasClass('active')) return;

                e.preventDefault();
                e.stopPropagation();

                const $img = this.$content.find('img');
                const targetIsImage = $(e.target).is('img') || $(e.target).closest('.wpg-lightbox-image').length > 0;



                // If over image and zoom enabled: zoom control
                // If NOT over image: navigate images
                if (targetIsImage && this.settings.lightbox_zoom_enabled) {
                    if (e.originalEvent.deltaY < 0) {
                        this.zoomIn();
                    } else {
                        this.zoomOut();
                    }
                } else {
                    // Navigate when mouse is NOT over image
                    if (e.originalEvent.deltaY < 0) {
                        this.prev();
                    } else {
                        this.next();
                    }
                }
            });

            // Image dragging
            this.$content.on('mousedown', 'img', (e) => {
                this.startDrag(e);
            });

            $(document).on('mousemove.lightbox-drag', (e) => {
                if (this.isDragging) {
                    this.onDrag(e);
                }
            });

            $(document).on('mouseup.lightbox-drag', () => {
                this.stopDrag();
            });
        }

        /**
         * Handle toolbar actions
         */
        handleToolbarAction(action, $btn) {
            switch (action) {
                case 'zoom-in':
                    this.zoomIn();
                    break;
                case 'zoom-out':
                    this.zoomOut();
                    break;
                case 'zoom-reset':
                    this.resetZoom();
                    break;
                case 'set-featured':
                    this.setAsFeatured($btn);
                    break;
                case 'autoplay':
                    this.toggleAutoplay($btn);
                    break;
                case 'delete':
                    this.deleteImage($btn);
                    break;
            }
        }

        /**
         * Check if image overflows viewport
         */
        isImageOverflowing($img) {
            if (!$img || !$img.length) return false;
            const rect = $img[0].getBoundingClientRect();
            return rect.width > window.innerWidth || rect.height > window.innerHeight;
        }

        /**
         * Start dragging
         */
        startDrag(e) {
            e.preventDefault();
            this.isDragging = true;
            this.dragStart = {
                x: e.clientX - this.currentTranslate.x,
                y: e.clientY - this.currentTranslate.y
            };
            this.$content.find('img').css('cursor', 'grabbing');
        }

        /**
         * On drag
         */
        onDrag(e) {
            if (!this.isDragging) return;

            e.preventDefault();

            // Direct transform update for better performance
            this.currentTranslate = {
                x: e.clientX - this.dragStart.x,
                y: e.clientY - this.dragStart.y
            };

            // Apply immediately without transition for smooth dragging
            const $img = this.$content.find('img');
            const transform = `translate(${this.currentTranslate.x}px, ${this.currentTranslate.y}px) scale(${this.zoomLevel})`;
            $img.css({
                'transform': transform,
                'transition': 'none' // Disable transition during drag
            });
        }

        /**
         * Stop dragging
         */
        stopDrag() {
            if (this.isDragging) {
                this.isDragging = false;
                // Re-enable transition after drag
                this.$content.find('img').css('transition', 'transform 0.3s ease');
                // Update cursor
                const $img = this.$content.find('img');
                if (this.isImageOverflowing($img)) {
                    $img.css('cursor', 'grab');
                } else {
                    $img.css('cursor', 'default');
                }
            }
        }

        /**
         * Open lightbox
         */
        open(index) {
            this.currentIndex = index;
            this.showImage();
            this.overlay.addClass('active');
            $('body').css('overflow', 'hidden');
        }

        /**
         * Close lightbox
         */
        close() {
            this.overlay.removeClass('active');
            $('body').css('overflow', '');
            this.stopAutoplay();
            this.resetZoom();

            // Reset autoplay button state
            this.$toolbar.find('[data-action="autoplay"]')
                .removeClass('active')
                .html('<i class="fas fa-play"></i>');
        }

        /**
         * Show current image
         */
        showImage(useTransition = true) {
            if (this.images.length === 0) return;

            const image = this.images[this.currentIndex];
            const animation = this.settings.lightbox_animation || 'fade';



            // Create loading placeholder
            const $placeholder = $('<div>', {
                'class': 'wpg-lightbox-loading',
                'text': 'Loading...'
            });

            // Add fade-out animation to current content if transition enabled
            if (useTransition && this.$content.children().length > 0) {
                // Apply animation class based on settings
                const animationClass = 'anim-' + animation + '-out';
                this.$content.addClass(animationClass);

                setTimeout(() => {
                    this.$content.removeClass(animationClass).html($placeholder);
                    this.loadAndShowImage(image, useTransition, animation);
                }, 250); // Half of total transition time
            } else {
                this.$content.html($placeholder);
                this.loadAndShowImage(image, useTransition, animation);
            }
        }

        /**
         * Load and show image with transition
         */
        loadAndShowImage(image, useTransition, animation) {
            // Lazy load full image
            this.loadFullImage(image).then(($img) => {
                // Add fade-in animation if transition enabled
                if (useTransition) {
                    // Initial state based on animation type
                    switch (animation) {
                        case 'slide':
                            $img.css({
                                'opacity': '1',
                                'transform': 'translateX(100%)'
                            });
                            break;
                        case 'zoom':
                            $img.css({
                                'opacity': '0',
                                'transform': 'scale(0.8)'
                            });
                            break;
                        case 'fade':
                        default:
                            $img.css({
                                'opacity': '0',
                                'transform': 'scale(1)'
                            });
                            break;
                    }

                    this.$content.html($img);

                    // Trigger animation
                    setTimeout(() => {
                        switch (animation) {
                            case 'slide':
                                $img.css({
                                    'transform': 'translateX(0)',
                                    'transition': 'transform 0.5s cubic-bezier(0.4, 0, 0.2, 1)'
                                });
                                break;
                            case 'zoom':
                                $img.css({
                                    'opacity': '1',
                                    'transform': 'scale(1)',
                                    'transition': 'opacity 0.5s ease, transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1)'
                                });
                                break;
                            case 'fade':
                            default:
                                $img.css({
                                    'opacity': '1',
                                    'transition': 'opacity 0.5s ease'
                                });
                                break;
                        }
                    }, 50);
                } else {
                    this.$content.html($img);
                }

                // Update counter
                if (this.settings.lightbox_show_counter) {
                    this.$counter.text((this.currentIndex + 1) + ' / ' + this.images.length);
                }

                // Update navigation visibility
                this.$prevBtn.toggle(this.images.length > 1);
                this.$nextBtn.toggle(this.images.length > 1);

                // Reset zoom and drag state
                this.zoomLevel = 1;
                this.currentTranslate = { x: 0, y: 0 };
            }).catch((error) => {
                this.$content.html('<div class="wpg-lightbox-error">Failed to load image</div>');
            });
        }

        /**
         * Load full image (lazy loading)
         */
        loadFullImage(image) {
            return new Promise((resolve, reject) => {
                // If already loaded, use cached
                if (image.loaded && image.$cachedImg) {
                    resolve(image.$cachedImg.clone());
                    return;
                }

                // Create new image element
                const $img = $('<img>', {
                    'class': 'wpg-lightbox-image',
                    'alt': image.alt
                });

                // Handle load success
                $img.on('load', () => {
                    image.loaded = true;
                    image.$cachedImg = $img.clone();
                    resolve($img);
                });

                // Handle load error
                $img.on('error', () => {
                    reject(new Error('Image failed to load'));
                });

                // Trigger load
                $img.attr('src', image.src);
            });
        }

        /**
         * Navigate to previous image
         */
        prev() {
            if (this.images.length <= 1) return;
            this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length;
            this.showImage(!this.isPlaying); // Manual: true, Autoplay: false
        }

        /**
         * Navigate to next image
         */
        next() {
            if (this.images.length <= 1) return;
            this.currentIndex = (this.currentIndex + 1) % this.images.length;
            this.showImage(!this.isPlaying); // Manual: true, Autoplay: false
        }

        /**
         * Zoom in
         */
        zoomIn() {
            const maxZoom = parseFloat(this.settings.lightbox_max_zoom) || 3;
            const step = parseFloat(this.settings.lightbox_zoom_step) || 0.2;

            if (this.zoomLevel < maxZoom) {
                this.zoomLevel = Math.min(this.zoomLevel + step, maxZoom);
                this.applyZoom();
            }
        }

        /**
         * Zoom out
         */
        zoomOut() {
            const step = parseFloat(this.settings.lightbox_zoom_step) || 0.2;

            if (this.zoomLevel > 1) {
                this.zoomLevel = Math.max(this.zoomLevel - step, 1);
                this.applyZoom();
            }
        }

        /**
         * Reset zoom
         */
        resetZoom() {
            this.zoomLevel = 1;
            this.currentTranslate = { x: 0, y: 0 };
            this.applyZoom();
        }

        /**
         * Toggle zoom
         */
        toggleZoom() {
            if (this.zoomLevel > 1) {
                this.resetZoom();
            } else {
                this.zoomIn();
            }
        }

        /**
         * Apply zoom transformation
         */
        applyZoom() {
            const $img = this.$content.find('img');
            const transform = `translate(${this.currentTranslate.x}px, ${this.currentTranslate.y}px) scale(${this.zoomLevel})`;
            $img.css('transform', transform);
            $img.toggleClass('zoomed', this.zoomLevel > 1);

            // Update cursor
            if (this.isImageOverflowing($img)) {
                $img.css('cursor', 'grab');
            } else {
                $img.css('cursor', 'default');
            }
        }

        /**
         * Set as featured image
         */
        setAsFeatured($btn) {
            if (!this.postId || this.images.length === 0) {
                if (window.w2p) {
                    window.w2p.toast(this.i18n.error || 'No post or images available.', 'error');
                } else {
                    alert(this.i18n.error || 'No post or images available.');
                }
                return;
            }

            const image = this.images[this.currentIndex];

            if (!image.attachmentId) {
                if (window.w2p) {
                    window.w2p.toast(this.i18n.noAttachmentId || 'Cannot set this image as featured. Image attachment ID not found.', 'warning');
                } else {
                    alert(this.i18n.noAttachmentId || 'Cannot set this image as featured. Image attachment ID not found.');
                }
                return;
            }

            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            $.ajax({
                url: this.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wpg_set_featured_image',
                    nonce: this.nonce,
                    post_id: this.postId,
                    attachment_id: image.attachmentId
                },
                success: (response) => {
                    if (response.success) {
                        if (window.w2p) {
                            window.w2p.toast(response.data.message || this.i18n.success || 'Featured image updated!', 'success');
                        } else {
                            alert(response.data.message || this.i18n.success || 'Featured image updated!');
                        }
                    } else {
                        if (window.w2p) {
                            window.w2p.toast(response.data || this.i18n.error || 'Failed to update featured image.', 'error');
                        } else {
                            alert(response.data || this.i18n.error || 'Failed to update featured image.');
                        }
                    }
                },
                error: (xhr, status, error) => {
                    if (window.w2p) {
                        window.w2p.toast(this.i18n.error || 'Request failed.', 'error');
                    } else {
                        alert(this.i18n.error || 'Request failed.');
                    }
                },
                complete: () => {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        }

        /**
         * Delete image from media library
         */
        deleteImage($btn) {
            const self = this; // Ensure 'this' context is preserved
            const image = this.images[this.currentIndex];

            if (!image) {
                console.error('WPGenius Lightbox: No image found at index ' + this.currentIndex);
                return;
            }

            if (!image.attachmentId) {
                console.warn('WPGenius Lightbox: Image has no attachment ID', image);
                const msg = this.i18n.noAttachmentId || 'Image attachment ID not found.';
                if (window.w2p) {
                    window.w2p.toast(msg, 'warning');
                } else {
                    alert(msg);
                }
                return;
            }

            const confirmMsg = this.i18n.confirmDelete || 'Are you sure you want to permanently delete this image?';

            if (window.w2p && window.w2p.confirm) {
                window.w2p.confirm(confirmMsg, function () { // Use regular function to allow explicit binding if needed, though arrow func usually inherits 'this' from lex scope
                    self.executeDelete(image, $btn);
                });
            } else if (confirm(confirmMsg)) {
                this.executeDelete(image, $btn);
            }
        }

        /**
         * Execute deletion AJAX
         */
        executeDelete(image, $btn) {
            const self = this;
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            $.ajax({
                url: this.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wpg_delete_attachment',
                    nonce: this.nonce,
                    attachment_id: image.attachmentId
                },
                success: (response) => { // Arrow function here preserves 'this' as the class instance (or 'self')
                    if (response.success) {
                        if (window.w2p) {
                            window.w2p.toast(response.data.message || self.i18n.deleteSuccess || 'Image deleted!', 'success');
                        } else {
                            // Fallback for success msg
                            console.log(response.data.message);
                        }

                        // Remove image from frontend collection
                        self.images.splice(self.currentIndex, 1);

                        // Also hide the original image in the post content
                        if (image.element) {
                            // Also try to remove the container if it's a figure
                            const $img = $(image.element);
                            const $figure = $img.closest('figure');
                            if ($figure.length > 0) {
                                $figure.fadeOut(function () { $(this).remove(); });
                            } else {
                                $img.fadeOut(function () { $(this).remove(); });
                            }
                        }

                        if (self.images.length === 0) {
                            self.close();
                        } else {
                            // Move to next image (or previous if it was the last one)
                            if (self.currentIndex >= self.images.length) {
                                self.currentIndex = self.images.length - 1;
                            }
                            self.showImage(true);
                        }
                    } else {
                        const errMsg = (response.data && response.data.message) ? response.data.message : (response.data || self.i18n.deleteError || 'Failed to delete image.');
                        if (window.w2p) {
                            window.w2p.toast(errMsg, 'error');
                        } else {
                            alert(errMsg);
                        }
                    }
                },
                error: (xhr, status, error) => {
                    console.error('WPGenius Lightbox Delete Error:', error);
                    if (window.w2p) {
                        window.w2p.toast(self.i18n.error || 'Request failed.', 'error');
                    } else {
                        alert(self.i18n.error || 'Request failed.');
                    }
                },
                complete: () => {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        }

        /**
         * Toggle autoplay
         */
        toggleAutoplay($btn) {
            if (this.isPlaying) {
                this.stopAutoplay();
                $btn.removeClass('active').html('<i class="fas fa-play"></i>');
            } else {
                this.startAutoplay();
                $btn.addClass('active').html('<i class="fas fa-pause"></i>');
            }
        }

        /**
         * Start autoplay
         */
        startAutoplay() {
            if (this.images.length <= 1) return;

            this.isPlaying = true;
            const interval = (parseInt(this.settings.lightbox_autoplay_interval) || 3) * 1000;

            this.playInterval = setInterval(() => {
                this.next();
            }, interval);
        }

        /**
         * Stop autoplay
         */
        stopAutoplay() {
            this.isPlaying = false;
            if (this.playInterval) {
                clearInterval(this.playInterval);
                this.playInterval = null;
            }
        }
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function () {
        // Check if config is available
        if (typeof wpgLightboxConfig === 'undefined') {
            console.warn('WP Genius Lightbox: Configuration (wpgLightboxConfig) missing.');
            return;
        }

        // Robust Initialization: Try multiple times if container not found immediately
        let initAttempts = 0;
        const maxAttempts = 10;

        function tryInit() {
            const $container = $('#w2p-post-content');

            if ($container.length > 0) {
                window.wpgLightbox = new WPGeniusLightbox(wpgLightboxConfig);
            } else if (initAttempts < maxAttempts) {
                initAttempts++;
                setTimeout(tryInit, 500); // Retry every 0.5s
            } else {
                // Fallback: Try to initialize anyway if it's a single post, searching for standard containers
                if ($('body.single, body.single-post, body.page').length > 0) {
                    window.wpgLightbox = new WPGeniusLightbox(wpgLightboxConfig);
                }
            }
        }

        tryInit();
    });

})(jQuery);
