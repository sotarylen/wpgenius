/**
 * WP Genius Reader
 * Frontend book chapter reader enhancement
 * Updated to "Premium Toolbar" design
 * 
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */

(function ($) {
    'use strict';

    class WPGeniusReader {
        constructor(config) {
            this.config = config;
            this.settings = config.settings || {};
            this.containerSelector = '#w2p-book-chapters';

            // State defaults or loaded
            const defaults = {
                fontSize: 20,
                fontFamily: 'sans',
                theme: 'light',
                fullscreen: false
            };

            // Use config defaults if available, otherwise use defaults
            // FIX: User settings (loadSettings) must override server defaults (wpgReaderDefaults)
            this.state = {
                ...defaults,
                ...(window.wpgReaderDefaults || {}),
                ...this.loadSettings()
            };

            this.init();
        }

        init() {
            // Strict check: only activate on pages with the #w2p-book-chapters container
            this.$container = $(this.containerSelector);

            if (this.$container.length === 0) {
                return; // Exit directly without doing anything
            }

            // Only initialize the feature when the target container is found
            this.createProgressBar();
            this.createToolbar();

            // Force apply initially to sync UI and Content
            this.applyStyles();
            this.bindEvents();
            this.restorePosition();

            // Restore Fullscreen State
            if (this.state.fullscreen) {
                this.enterFullscreen();
            }
        }



        loadSettings() {
            try {
                const saved = localStorage.getItem('wpg_reader_settings');
                return saved ? JSON.parse(saved) : {};
            } catch (e) {
                return {};
            }
        }

        saveSettings() {
            // FIX: Only save user preferences, NOT content specific data (like links)
            const settingsToSave = {
                fontSize: this.state.fontSize,
                fontFamily: this.state.fontFamily,
                theme: this.state.theme,
                fullscreen: this.state.fullscreen
            };
            localStorage.setItem('wpg_reader_settings', JSON.stringify(settingsToSave));
        }

        createProgressBar() {
            $('#wpg-reader-progress-bar').remove();
            this.$progressBar = $('<div>', { id: 'wpg-reader-progress-bar' });
            $('body').append(this.$progressBar);
        }

        createToolbar() {

            // Remove any existing toolbar
            $('#wpg-reader-toolbar, #wpg-reader-toolbar-container').remove();

            const $toolbar = $('<div>', { id: 'wpg-reader-toolbar' });

            // --- Section 1: Font Size ---
            const $sizeSection = $('<div>', { class: 'wpg-reader-section' });
            const $sizeControl = $('<div>', { class: 'wpg-reader-size-control' });

            // Use type="button" to prevent form submission logic if placed inside form
            const $btnDecrease = $('<button>', { type: 'button', class: 'wpg-reader-btn-icon', text: '−', title: wpgReaderConfig.i18n.decreaseFont });
            const $sizeDisplay = $('<span>', { class: 'wpg-reader-size-display', text: this.state.fontSize + 'px' });
            const $btnIncrease = $('<button>', { type: 'button', class: 'wpg-reader-btn-icon', text: '+', title: wpgReaderConfig.i18n.increaseFont });

            $btnDecrease.on('click', (e) => {
                e.preventDefault();
                this.changeFontSize(-1);
            });

            $btnIncrease.on('click', (e) => {
                e.preventDefault();
                this.changeFontSize(1);
            });

            $sizeControl.append($btnDecrease, $sizeDisplay, $btnIncrease);
            $sizeSection.append($sizeControl);

            // --- Section 2: Font Family ---
            const $fontSection = $('<div>', { class: 'wpg-reader-section' });
            $fontSection.append($('<span>', { class: 'wpg-reader-label', text: wpgReaderConfig.i18n.font }));

            const $fontSelect = $('<select>', { class: 'wpg-reader-select' });
            const fonts = [
                { id: 'sans', label: wpgReaderConfig.i18n.fontSans },
                { id: 'heiti', label: wpgReaderConfig.i18n.fontHeiti },
                { id: 'songti', label: wpgReaderConfig.i18n.fontSongti },
                { id: 'kaiti', label: wpgReaderConfig.i18n.fontKaiti },
                { id: 'lishu', label: wpgReaderConfig.i18n.fontLishu },
                { id: 'yahei', label: wpgReaderConfig.i18n.fontYahei },
                { id: 'droidsans', label: wpgReaderConfig.i18n.fontDroidsans }
            ];

            fonts.forEach(f => {
                $fontSelect.append($('<option>', {
                    value: f.id,
                    text: f.label,
                    selected: this.state.fontFamily === f.id
                }));
            });

            $fontSelect.on('change', (e) => {
                const selectedFont = $(e.target).val();

                // Early return if same font
                if (this.state.fontFamily === selectedFont) return;

                this.setFontFamily(selectedFont);
            });
            $fontSection.append($fontSelect);

            // --- Section 3: Themes ---
            const $themeSection = $('<div>', { class: 'wpg-reader-section' });
            const $themeGroup = $('<div>', { class: 'wpg-reader-themes' });

            const themes = [
                { id: 'light', icon: 'fas fa-sun', class: 'wpg-theme-btn-light', title: wpgReaderConfig.i18n.themeLight },
                { id: 'sepia', icon: 'fas fa-book-open', class: 'wpg-theme-btn-sepia', title: wpgReaderConfig.i18n.themeSepia },
                { id: 'green', icon: 'fas fa-leaf', class: 'wpg-theme-btn-green', title: wpgReaderConfig.i18n.themeGreen },
                { id: 'dark', icon: 'fas fa-moon', class: 'wpg-theme-btn-dark', title: wpgReaderConfig.i18n.themeDark }
            ];

            themes.forEach(t => {
                const $btn = $('<button>', {
                    type: 'button',
                    class: `wpg-reader-theme-btn ${t.class}`,
                    title: t.title,
                    'data-theme': t.id
                });

                // Add Font Awesome icon
                $btn.append($('<i>', { class: t.icon }));

                // Set active state
                if (this.state.theme === t.id) {
                    $btn.addClass('active');
                }

                $btn.on('click', (e) => {
                    e.preventDefault();
                    this.setTheme(t.id);
                });

                $themeGroup.append($btn);
            });

            $themeSection.append($themeGroup);

            // --- Section 4: Fullscreen/Focus Mode ---
            const $fullscreenSection = $('<div>', { class: 'wpg-reader-section' });
            const $fullscreenBtn = $('<button>', {
                type: 'button',
                class: 'wpg-reader-btn-icon wpg-reader-fullscreen-btn',
                title: wpgReaderConfig.i18n.fullscreen,
                'data-fullscreen': 'false'
            });

            $fullscreenBtn.append($('<i>', { class: 'fas fa-expand-alt' }));

            $fullscreenBtn.on('click', (e) => {
                e.preventDefault();
                this.toggleFullscreen();
            });

            $fullscreenSection.append($fullscreenBtn);

            // Using stored reference needed for API
            this.$fullscreenBtn = $fullscreenBtn;

            // Update local refs
            this.$toolbar = $toolbar;
            this.$sizeDisplay = $sizeDisplay;

            // Assemble toolbar
            // [UX] Nav section added at the end
            const $navSection = this.createNavSection();
            $toolbar.append($sizeSection, $fontSection, $themeSection, $fullscreenSection, $navSection);

            // Insert toolbar BEFORE content container
            this.$container.before($toolbar);

            // Ensure toolbar is visible
            $toolbar.show();

            // --- Footer Toolbar (Bottom of Content) ---
            $('#wpg-reader-footer-toolbar').remove();
            const $footerToolbar = $('<div>', { id: 'wpg-reader-footer-toolbar' });
            const $footerNav = this.createNavSection();

            // Add specific class for footer styling if needed
            $footerNav.addClass('wpg-footer-nav');

            $footerToolbar.append($footerNav);
            this.$container.append($footerToolbar);
        }

        createNavSection() {
            const links = this.state.links || {};
            const $navSection = $('<div>', { class: 'wpg-reader-section wpg-reader-nav-section' });

            // Prev Button
            const $prevBtn = $('<button>', {
                type: 'button',
                class: 'wpg-reader-btn-icon',
                title: wpgReaderConfig.i18n.prevChapter,
                disabled: !links.prev
            });
            $prevBtn.append($('<i>', { class: 'fas fa-chevron-left' }));
            if (links.prev) {
                $prevBtn.on('click', () => window.location.href = links.prev);
            }

            // TOC Button (Smart Exit Focus Mode)
            const $tocBtn = $('<button>', {
                type: 'button',
                class: 'wpg-reader-btn-icon',
                title: wpgReaderConfig.i18n.toc,
                disabled: !links.toc
            });
            $tocBtn.append($('<i>', { class: 'fas fa-list' }));
            if (links.toc) {
                $tocBtn.on('click', () => {
                    this.state.fullscreen = false;
                    this.saveSettings();
                    window.location.href = links.toc;
                });
            }

            // Next Button
            const $nextBtn = $('<button>', {
                type: 'button',
                class: 'wpg-reader-btn-icon',
                title: wpgReaderConfig.i18n.nextChapter,
                disabled: !links.next
            });
            $nextBtn.append($('<i>', { class: 'fas fa-chevron-right' }));
            if (links.next) {
                $nextBtn.on('click', () => window.location.href = links.next);
            }

            $navSection.append($prevBtn, $tocBtn, $nextBtn);
            return $navSection;
        }

        changeFontSize(delta) {
            let currentSize = parseInt(this.state.fontSize) || 18;
            let newSize = currentSize + (delta * 2); // Step of 2

            // Boundary checks
            if (newSize < 12) newSize = 12;
            if (newSize > 40) newSize = 40; // Max limit of 40px

            // Early return if no change
            if (this.state.fontSize === newSize) return;

            this.state.fontSize = newSize;

            // Smooth visual updates using requestAnimationFrame
            requestAnimationFrame(() => {
                // Update display
                this.$sizeDisplay.text(newSize + 'px');

                // Apply font styles only (more efficient)
                this.applyFontStyles();

                // Save settings (non-blocking)
                setTimeout(() => this.saveSettings(), 0);
            });
        }

        setFontFamily(font) {
            // Early return if same font
            if (this.state.fontFamily === font) return;

            this.state.fontFamily = font;

            // Smooth update using requestAnimationFrame
            requestAnimationFrame(() => {
                this.applyFontStyles();
                setTimeout(() => this.saveSettings(), 0);
            });
        }

        setTheme(theme) {
            console.log('Switching to theme:', theme);

            // Early return if same theme
            if (this.state.theme === theme) {
                return;
            }

            // Update state immediately
            this.state.theme = theme;

            // Batch DOM updates for performance
            requestAnimationFrame(() => {
                // Update active theme button with smooth transition
                this.$toolbar.find('.wpg-reader-theme-btn').each((index, btn) => {
                    const $btn = $(btn);
                    const isActive = $btn.data('theme') === theme;

                    if (isActive) {
                        $btn.addClass('active');
                    } else {
                        $btn.removeClass('active');
                    }
                });

                // Apply theme styles with smooth transition
                this.applyThemeStyles();

                // Save settings (non-blocking)
                setTimeout(() => this.saveSettings(), 0);
            });
        }

        applyStyles() {
            // Apply font family and size
            this.applyFontStyles();
            // Apply theme
            this.applyThemeStyles();
        }

        applyFontStyles() {
            const $container = this.$container;

            // Remove all font classes
            $container.removeClass(
                'wpg-font-sans wpg-font-heiti wpg-font-songti wpg-font-kaiti wpg-font-lishu wpg-font-yahei wpg-font-droidsans wpg-font-serif'
            );

            // Add new font class
            $container.addClass(`wpg-font-${this.state.fontFamily}`);

            // Apply font size and line height
            $container[0].style.fontSize = `${this.state.fontSize}px`;
            $container[0].style.lineHeight = '1.8';
        }

        applyThemeStyles() {
            const $container = this.$container;

            // Remove all theme classes
            $container.removeClass(
                'wpg-theme-light wpg-theme-sepia wpg-theme-dark wpg-theme-green'
            );

            // Add new theme class
            $container.addClass(`wpg-theme-${this.state.theme}`);
        }

        bindEvents() {
            $(window).on('scroll', () => {
                this.updateProgress();
                this.savePosition();
            });

            // Keyboard support: ESC exits fullscreen mode
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.$fullscreenBtn && this.$fullscreenBtn.data('fullscreen') === 'true') {
                    this.exitFullscreen();
                }
            });
        }

        updateProgress() {
            const scrollTop = $(window).scrollTop();
            const docHeight = $(document).height();
            const winHeight = $(window).height();

            if (docHeight <= winHeight) return;

            const scrollPercent = (scrollTop / (docHeight - winHeight)) * 100;
            this.$progressBar.css('width', scrollPercent + '%');
        }

        savePosition() {
            const scrollTop = $(window).scrollTop();
            if (scrollTop > 0 && this.config.postId) {
                localStorage.setItem('wpg_reader_scroll_pos_' + this.config.postId, scrollTop);
            }
        }

        restorePosition() {
            if (!this.config.postId) return;
            const savedPos = localStorage.getItem('wpg_reader_scroll_pos_' + this.config.postId);
            if (savedPos) {
                // Use a slight delay to ensure layout is stable
                setTimeout(() => {
                    $('html, body').animate({ scrollTop: savedPos }, 500);
                }, 300);
            }
        }

        toggleFullscreen() {
            const isFullscreen = this.$fullscreenBtn.data('fullscreen') === 'true';

            if (isFullscreen) {
                this.exitFullscreen();
            } else {
                this.enterFullscreen();
            }
        }

        enterFullscreen() {
            try {
                // Update state
                this.state.fullscreen = true;
                this.saveSettings();

                // Update button state
                this.$fullscreenBtn.data('fullscreen', 'true');
                this.$fullscreenBtn.find('i').removeClass('fas fa-expand-alt').addClass('fas fa-compress-alt');
                this.$fullscreenBtn.attr('title', wpgReaderConfig.i18n.exitFullscreen);

                // Add fullscreen mode class
                $('body').addClass('wpg-reader-fullscreen');
            } catch (error) {
                // Ignore
            }
        }

        exitFullscreen() {

            try {
                // Update state
                this.state.fullscreen = false;
                this.saveSettings();

                // Update button state
                this.$fullscreenBtn.data('fullscreen', 'false');
                this.$fullscreenBtn.find('i').removeClass('fas fa-compress-alt').addClass('fas fa-expand-alt');
                this.$fullscreenBtn.attr('title', wpgReaderConfig.i18n.fullscreen);

                // Remove fullscreen mode class
                $('body').removeClass('wpg-reader-fullscreen');

            } catch (error) {
                // Ignore
            }
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function () {
        // Check if we have configuration
        if (typeof wpgReaderConfig !== 'undefined') {
            new WPGeniusReader(wpgReaderConfig);
        }
    });

})(jQuery);
