/**
 * WP Genius Lightbox
 * Frontend image viewer with advanced features
 * 
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */

(function($) {
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
            console.log('WP Genius Lightbox: Initializing instance...');
            
            // Create overlay DOM structure
            this.createOverlay();
            console.log('WP Genius Lightbox: Overlay created');
            
            // Collect images from content
            this.collectImages();
            
            // Bind events
            this.bindEvents();
            console.log('WP Genius Lightbox: Events bound');
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
            console.log('WP Genius Lightbox: Starting to collect images...');
            
            // Priority 1: Custom container with specific ID (recommended)
            let $articleContainer = $('#w2p-post-content').first();
            
            // Priority 2: Standard WordPress content classes
            if ($articleContainer.length === 0) {
                $articleContainer = $('.entry-content, .post-content, article.post, article[id^="post-"]').first();
            }
            
            // Priority 3: Try w-grid-item articles (theme-specific)
            if ($articleContainer.length === 0) {
                $articleContainer = $('article.w-grid-item').first();
            }
            
            // Priority 4: Fallback to body for single post/page
            if ($articleContainer.length === 0 && $('body.single, body.single-post, body.page').length > 0) {
                $articleContainer = $('body');
                console.log('WP Genius Lightbox: Using body as container for single post/page');
            }
            
            if ($articleContainer.length === 0) {
                console.warn('WP Genius Lightbox: No article container found');
                console.log('WP Genius Lightbox: Available containers:', {
                    '#w2p-post-content': $('#w2p-post-content').length,
                    '.entry-content': $('.entry-content').length,
                    '.post-content': $('.post-content').length,
                    'article.post': $('article.post').length,
                    'article[id^="post-"]': $('article[id^="post-"]').length,
                    'article.w-grid-item': $('article.w-grid-item').length,
                    'body.single': $('body.single').length
                });
                return;
            }
            
            console.log('WP Genius Lightbox: Found article container:', $articleContainer[0]);
            console.log('WP Genius Lightbox: Container selector used:', $articleContainer.attr('id') || $articleContainer.attr('class') || $articleContainer[0].tagName);
            
            $articleContainer.find('img').each(function() {
                const $img = $(this);
                const src = $img.attr('src');
                const dataSrc = $img.attr('data-src'); // For lazy loading
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
                
                // Try to extract from parent link if it's a WordPress attachment page
                const $link = $img.closest('a');
                if (!attachmentId && $link.length) {
                    const hrefMatch = $link.attr('href')?.match(/attachment_id=(\d+)/) || 
                                     $link.attr('href')?.match(/\/(\d+)\/?$/);
                    if (hrefMatch) {
                        attachmentId = hrefMatch[1];
                    }
                }

                // Get full size URL if available
                let fullSrc = src || dataSrc;
                if ($link.length && $link.attr('href') && $link.attr('href').match(/\.(jpg|jpeg|png|gif|webp)$/i)) {
                    fullSrc = $link.attr('href');
                }

                self.images.push({
                    src: fullSrc,
                    thumb: src || dataSrc,
                    alt: alt,
                    attachmentId: attachmentId,
                    element: $img[0],
                    loaded: false // Track if full image is loaded
                });
            });
            
            console.log('WP Genius Lightbox: Collected ' + self.images.length + ' images from article');
            if (self.images.length > 0) {
                console.log('WP Genius Lightbox: First image:', self.images[0]);
                console.log('WP Genius Lightbox: Attachment IDs:', self.images.map(img => ({ 
                    src: img.src.substring(img.src.lastIndexOf('/') + 1), 
                    attachmentId: img.attachmentId 
                })));
            } else {
                console.warn('WP Genius Lightbox: No images found in container. Total img elements in page:', $('img').length);
            }
        }

        /**
         * Bind events
         */
        bindEvents() {
            const self = this;

            // Image click - respond to all images (even without links)
            // Priority selector: custom container ID first, then fallbacks
            const clickSelector = '#w2p-post-content img, body.single img, body.single-post img, body.page img, .entry-content img, .post-content img, article.post img, article[id^="post-"] img, article.w-grid-item img';
            
            $(document).on('click', clickSelector, function(e) {
                console.log('WP Genius Lightbox: Image clicked!', this);
                e.preventDefault();
                e.stopPropagation(); // Prevent link navigation
                
                const index = self.images.findIndex(img => img.element === this);
                console.log('WP Genius Lightbox: Image index:', index);
                
                if (index >= 0) {
                    console.log('WP Genius Lightbox: Opening lightbox for image:', self.images[index]);
                    self.open(index);
                } else {
                    console.warn('WP Genius Lightbox: Image not found in collection');
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
                    
                    switch(e.key) {
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
            this.$toolbar.on('click', '.wpg-lightbox-btn', function(e) {
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
                
                console.log('Wheel event:', { 
                    targetIsImage, 
                    target: e.target.tagName,
                    targetClass: e.target.className 
                });
                
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

            // Image dragging when zoomed
            this.$content.on('mousedown', 'img', (e) => {
                const $img = $(e.currentTarget);
                if (this.zoomLevel > 1 && this.isImageOverflowing($img)) {
                    this.startDrag(e);
                }
            });
            
            $(document).on('mousemove.lightbox-drag', (e) => {
                if (this.isDragging) {
                    this.onDrag(e);
                }
            });
            
            $(document).on('mouseup.lightbox-drag', () => {
                this.stopDrag();
            });

            // Image click for zoom toggle
            this.$content.on('click', 'img', () => {
                if (!this.isDragging && this.settings.lightbox_zoom_enabled) {
                    this.toggleZoom();
                }
            });
        }

        /**
         * Handle toolbar actions
         */
        handleToolbarAction(action, $btn) {
            switch(action) {
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
                // Update cursor based on zoom state
                const $img = this.$content.find('img');
                if (this.zoomLevel > 1 && this.isImageOverflowing($img)) {
                    $img.css('cursor', 'grab');
                } else if (this.zoomLevel > 1) {
                    $img.css('cursor', 'zoom-out');
                } else {
                    $img.css('cursor', 'zoom-in');
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
            
            console.log('showImage called, useTransition:', useTransition, 'animation:', animation);
            
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
                    switch(animation) {
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
                        switch(animation) {
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
                console.error('Failed to load image:', error);
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
            if (this.zoomLevel > 1 && this.isImageOverflowing($img)) {
                $img.css('cursor', 'grab');
            } else if (this.zoomLevel > 1) {
                $img.css('cursor', 'zoom-out');
            } else {
                $img.css('cursor', 'zoom-in');
            }
        }

        /**
         * Set as featured image
         */
        setAsFeatured($btn) {
            if (!this.postId || this.images.length === 0) {
                alert(this.i18n.error || 'No post or images available.');
                return;
            }

            const image = this.images[this.currentIndex];
            console.log('WP Genius Lightbox: Set as featured - image data:', image);
            
            if (!image.attachmentId) {
                alert(this.i18n.noAttachmentId || 'Cannot set this image as featured. Image attachment ID not found.');
                console.warn('WP Genius Lightbox: Image has no attachment ID:', image);
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
                    console.log('WP Genius Lightbox: Set featured response:', response);
                    if (response.success) {
                        alert(response.data.message || this.i18n.success || 'Featured image updated!');
                    } else {
                        alert(response.data || this.i18n.error || 'Failed to update featured image.');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('WP Genius Lightbox: AJAX error:', { xhr, status, error });
                    alert(this.i18n.error || 'Request failed.');
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
    $(document).ready(function() {
        console.log('WP Genius Lightbox: Starting initialization...');
        
        // Check if config is available
        if (typeof wpgLightboxConfig === 'undefined') {
            console.error('WP Genius Lightbox: wpgLightboxConfig is not defined. Lightbox module may not be enabled.');
            return;
        }
        
        console.log('WP Genius Lightbox: Config found:', wpgLightboxConfig);

        // Check if other lightbox plugins exist
        const detectedPlugins = [];
        if (window.lightGallery) detectedPlugins.push('lightGallery');
        if (window.PhotoSwipe) detectedPlugins.push('PhotoSwipe');
        if (window.fancybox) detectedPlugins.push('fancybox');
        if ($.magnificPopup) detectedPlugins.push('Magnific Popup');
        if ($.fn.magnificPopup) detectedPlugins.push('Magnific Popup (jQuery)');
        
        if (detectedPlugins.length > 0) {
            console.warn('WP Genius Lightbox: Other lightbox plugins detected:', detectedPlugins.join(', '));
            console.warn('WP Genius Lightbox: Attempting to disable conflicts and initialize WP Genius Lightbox...');
            
            // Try to disable Magnific Popup on article images
            if ($.fn.magnificPopup) {
                console.log('WP Genius Lightbox: Unbinding Magnific Popup from article images...');
                
                // Unbind magnificPopup from links
                $('.entry-content a, .post-content a, article a').each(function() {
                    const $link = $(this);
                    if ($link.find('img').length > 0) {
                        // This link contains an image
                        try {
                            $link.magnificPopup('close');
                            $link.off('.mfp'); // Remove magnificPopup events
                            console.log('WP Genius Lightbox: Unbound Magnific Popup from:', $link[0]);
                        } catch(e) {
                            // Ignore errors
                        }
                    }
                });
                
                // Prevent magnificPopup from auto-binding
                $(document).off('click.magnificPopup');
            }
        }
        
        console.log('WP Genius Lightbox: No conflicts detected, initializing...');

        // Initialize Lightbox
        window.wpgLightbox = new WPGeniusLightbox(wpgLightboxConfig);
        
        console.log('WP Genius Lightbox: Initialization complete. Total images:', window.wpgLightbox.images.length);
    });

})(jQuery);
