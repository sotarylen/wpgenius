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
                theme: 'light'
            };

            // Use config defaults if available, otherwise use defaults
            this.state = {
                ...defaults,
                ...this.loadSettings(),
                ...(window.wpgReaderDefaults || {})
            };

            this.init();
        }

        init() {
            console.log('WP Genius Reader: Starting initialization...');

            // 严格检查：只允许在存在 #w2p-book-chapters 的页面激活
            this.$container = $(this.containerSelector);

            if (this.$container.length === 0) {
                console.log('WP Genius Reader: #w2p-book-chapters container not found. Reader will not be initialized on this page.');
                console.log('WP Genius Reader: This is expected behavior for pages without book chapters.');
                return; // 直接退出，不做任何操作
            }

            console.log('WP Genius Reader: Target container #w2p-book-chapters found. Initializing reader functionality...');

            // 只有在找到目标容器时才初始化功能
            this.createProgressBar();
            this.createToolbar();

            // Force apply initially to sync UI and Content
            this.applyStyles();
            this.bindEvents();
            this.restorePosition();

            console.log('WP Genius Reader: Initialization completed successfully!');
        }



        loadSettings() {
            try {
                const saved = localStorage.getItem('wpg_reader_settings');
                return saved ? JSON.parse(saved) : {};
            } catch (e) {
                console.error('WP Genius Reader: Error loading settings', e);
                return {};
            }
        }

        saveSettings() {
            localStorage.setItem('wpg_reader_settings', JSON.stringify(this.state));
        }

        createProgressBar() {
            $('#wpg-reader-progress-bar').remove();
            this.$progressBar = $('<div>', { id: 'wpg-reader-progress-bar' });
            $('body').append(this.$progressBar);
        }

        createToolbar() {
            console.log('WP Genius Reader: Creating toolbar...');

            // Remove any existing toolbar
            $('#wpg-reader-toolbar, #wpg-reader-toolbar-container').remove();

            const $toolbar = $('<div>', { id: 'wpg-reader-toolbar' });

            // --- Section 1: Font Size ---
            const $sizeSection = $('<div>', { class: 'wpg-reader-section' });
            const $sizeControl = $('<div>', { class: 'wpg-reader-size-control' });

            // Use type="button" to prevent form submission logic if placed inside form
            const $btnDecrease = $('<button>', { type: 'button', class: 'wpg-reader-btn-icon', text: '−', title: '减小字体' });
            const $sizeDisplay = $('<span>', { class: 'wpg-reader-size-display', text: this.state.fontSize + 'px' });
            const $btnIncrease = $('<button>', { type: 'button', class: 'wpg-reader-btn-icon', text: '+', title: '增大字体' });

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
            $fontSection.append($('<span>', { class: 'wpg-reader-label', text: '字体' }));

            const $fontSelect = $('<select>', { class: 'wpg-reader-select' });
            const fonts = [
                { id: 'sans', label: '系统默认' },
                { id: 'heiti', label: '黑体' },
                { id: 'songti', label: '宋体' },
                { id: 'kaiti', label: '楷体' },
                { id: 'lishu', label: '隶书' },
                { id: 'yahei', label: '微软雅黑' },
                { id: 'droidsans', label: '思源黑体' }
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
                console.log('Font family changed to:', selectedFont);

                // Early return if same font
                if (this.state.fontFamily === selectedFont) return;

                this.setFontFamily(selectedFont);
            });
            $fontSection.append($fontSelect);

            // --- Section 3: Themes ---
            const $themeSection = $('<div>', { class: 'wpg-reader-section' });
            const $themeGroup = $('<div>', { class: 'wpg-reader-themes' });

            const themes = [
                { id: 'light', icon: 'fas fa-sun', class: 'wpg-theme-btn-light', title: '明亮模式' },
                { id: 'sepia', icon: 'fas fa-book-open', class: 'wpg-theme-btn-sepia', title: '护眼模式' },
                { id: 'green', icon: 'fas fa-leaf', class: 'wpg-theme-btn-green', title: '自然模式' },
                { id: 'dark', icon: 'fas fa-moon', class: 'wpg-theme-btn-dark', title: '暗黑模式' }
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
                title: '全屏/专注模式',
                'data-fullscreen': 'false'
            });

            $fullscreenBtn.append($('<i>', { class: 'fas fa-expand-alt' }));

            $fullscreenBtn.on('click', (e) => {
                e.preventDefault();
                this.toggleFullscreen();
            });

            $fullscreenSection.append($fullscreenBtn);

            // Store reference for later use
            this.$fullscreenBtn = $fullscreenBtn;

            // Update local refs
            this.$toolbar = $toolbar;
            this.$sizeDisplay = $sizeDisplay;

            // Assemble toolbar
            $toolbar.append($sizeSection, $fontSection, $themeSection, $fullscreenSection);

            // Insert toolbar BEFORE content container
            this.$container.before($toolbar);

            // Ensure toolbar is visible
            $toolbar.show();

            console.log('WP Genius Reader: Toolbar created and inserted successfully!');
        }

        changeFontSize(delta) {
            let currentSize = parseInt(this.state.fontSize) || 18;
            let newSize = currentSize + (delta * 2); // 步进为2

            // Boundary checks
            if (newSize < 12) newSize = 12;
            if (newSize > 40) newSize = 40; // 最大限制为40px

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

            console.log('Font size changed to:', newSize + 'px');
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
                console.log('Same theme, skipping...');
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

            console.log('Theme switched successfully to:', theme);
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

            // 键盘支持：ESC退出全屏模式
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
            console.log('Entering fullscreen/focus mode...');

            try {
                // Update button state
                this.$fullscreenBtn.data('fullscreen', 'true');
                this.$fullscreenBtn.find('i').removeClass('fas fa-expand-alt').addClass('fas fa-compress-alt');
                this.$fullscreenBtn.attr('title', '退出全屏模式');

                // Add fullscreen mode class
                $('body').addClass('wpg-reader-fullscreen');

                console.log('Fullscreen mode activated successfully');
            } catch (error) {
                console.error('Error entering fullscreen mode:', error);
            }
        }

        exitFullscreen() {
            console.log('Exiting fullscreen/focus mode...');

            try {
                // Update button state
                this.$fullscreenBtn.data('fullscreen', 'false');
                this.$fullscreenBtn.find('i').removeClass('fas fa-compress-alt').addClass('fas fa-expand-alt');
                this.$fullscreenBtn.attr('title', '全屏/专注模式');

                // Remove fullscreen mode class
                $('body').removeClass('wpg-reader-fullscreen');

                console.log('Fullscreen mode exited successfully');
            } catch (error) {
                console.error('Error exiting fullscreen mode:', error);
            }
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function () {
        // Check if we have configuration
        if (typeof wpgReaderConfig !== 'undefined') {
            console.log('WP Genius Reader: Configuration found, initializing...');
            new WPGeniusReader(wpgReaderConfig);
        } else {
            console.warn('WP Genius Reader: No configuration found. Reader functionality will not be initialized.');
        }
    });

})(jQuery);
