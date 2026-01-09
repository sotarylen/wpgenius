/**
 * WP Genius Video Optimizer with Plyr
 * Frontend video player optimization using Plyr library
 * 
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */

(function ($) {
    'use strict';

    /**
     * Video Optimizer Class with Plyr Integration
     */
    class WPGeniusVideoOptimizer {
        constructor(config) {
            this.config = config;
            this.settings = config.settings || {};
            this.i18n = config.i18n || {};
            this.players = [];
            this.storageKey = 'wpg_video_progress_';

            this.init();
        }

        /**
         * Initialize Video Optimizer
         */
        init() {
            // Initialize players immediately
            this.initializePlayers();
        }

        /**
         * Initialize Plyr for all video elements
         */
        initializePlayers() {
            const self = this;

            // Find all video elements (native HTML5)
            const nativeVideos = document.querySelectorAll('video');

            // Find all iframes that might contain videos (WPBakery, etc.)
            const iframes = document.querySelectorAll('iframe');

            const allVideos = [];

            // Add native video elements
            nativeVideos.forEach(video => {
                if (self.shouldProcessVideo(video)) {
                    allVideos.push(video);
                }
            });

            // Process iframes for local video files
            iframes.forEach(iframe => {
                const src = iframe.src || iframe.getAttribute('data-src') || '';

                // Only process local video files (multiple formats supported)
                if (self.isLocalMP4(src)) {
                    // Convert iframe to video element
                    const video = self.convertIframeToVideo(iframe, src);
                    if (video) {
                        allVideos.push(video);
                    }
                }
            });

            if (allVideos.length === 0) {
                return;
            }

            allVideos.forEach((video) => {
                // Skip if already initialized
                if (video.plyr) {
                    return;
                }

                // Detect vertical video aspect ratio
                const detectVerticalVideo = (vid) => {
                    return new Promise((resolve) => {
                        if (vid.videoWidth && vid.videoHeight) {
                            const ratio = vid.videoWidth / vid.videoHeight;
                            resolve(ratio < 0.8); // Less than 4:5 is considered vertical
                        } else {
                            vid.addEventListener('loadedmetadata', () => {
                                const ratio = vid.videoWidth / vid.videoHeight;
                                resolve(ratio < 0.8);
                            }, { once: true });
                        }
                    });
                };

                try {
                    // Initialize Plyr with custom options
                    const player = new Plyr(video, {
                        controls: [
                            'play-large',
                            'play',
                            'progress',
                            'current-time',
                            'duration',
                            'mute',
                            'volume',
                            'settings',
                            'pip',           // Picture-in-Picture
                            'airplay',
                            'fullscreen'
                        ],
                        settings: ['speed', 'quality'], // Speed control
                        speed: {
                            selected: 1,
                            options: [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2]
                        },
                        keyboard: {
                            focused: true,
                            global: true
                        },
                        tooltips: {
                            controls: true,
                            seek: true
                        },
                        fullscreen: {
                            enabled: true,
                            fallback: true,
                            iosNative: true
                        },
                        volume: 1, // Default volume 100%
                        muted: false, // Not muted by default
                        // Don't set ratio - let video use its natural size
                        ratio: null,
                        // Ensure controls are always visible
                        hideControls: false
                    });

                    // Force resize after initialization
                    player.on('ready', () => {
                        // Let Plyr handle the layout
                        // Force video to use natural aspect ratio
                        const videoElement = player.elements.wrapper.querySelector('video');
                        if (videoElement) {
                            videoElement.style.aspectRatio = 'auto';
                            videoElement.style.objectFit = 'contain';

                            // Detect and mark vertical videos
                            detectVerticalVideo(videoElement).then(isVertical => {
                                if (isVertical) {
                                    const wrapper = videoElement.closest('.wpg-video-wrapper');
                                    if (wrapper) {
                                        wrapper.classList.add('vertical-video');
                                    }
                                }
                            });
                        }
                    });

                    // Force resize after initialization
                    player.on('ready', () => {
                        // Let Plyr handle the layout
                    });

                    // Get video ID for progress tracking
                    const videoId = self.getVideoId(video);

                    // Restore saved progress (wait for loadedmetadata)
                    if (videoId) {
                        const savedProgress = self.getSavedProgress(videoId);
                        if (savedProgress > 0) {
                            player.on('loadedmetadata', () => {
                                // Only restore if duration is valid and progress is within range
                                if (player.duration > 0 && savedProgress < player.duration) {
                                    player.currentTime = savedProgress;
                                } else {
                                    // Clear invalid progress
                                    self.clearProgress(videoId);
                                }
                            });
                        }
                    }

                    // Save progress on time update (throttle to every 2 seconds)
                    let lastSaveTime = 0;
                    player.on('timeupdate', () => {
                        const now = Date.now();
                        if (videoId && player.currentTime > 0 && now - lastSaveTime > 2000) {
                            self.saveProgress(videoId, player.currentTime);
                            lastSaveTime = now;
                        }
                    });

                    // Clear progress when video ends
                    player.on('ended', () => {
                        if (videoId) {
                            self.clearProgress(videoId);
                        }
                    });

                    // Store player instance
                    self.players.push(player);

                    // Add exclusive playback
                    if (self.settings.video_exclusive_playback) {
                        player.on('play', () => {
                            self.pauseOthers(player);
                        });
                    }
                } catch (error) {
                    // console.error('WP Genius: Failed to initialize Plyr:', error);
                }
            });

            // Auto-play first video if only one video on page
            if (allVideos.length === 1 && self.players.length === 1) {
                const firstPlayer = self.players[0];
                firstPlayer.on('loadedmetadata', () => {
                    // Try to autoplay after a small delay
                    setTimeout(() => {
                        // Try direct play first (works if user previously interacted)
                        firstPlayer.play().then(() => {
                        }).catch(err => {
                            // If direct play fails, try muted autoplay
                            firstPlayer.muted = true;
                            firstPlayer.play().then(() => {
                                // Unmute after 1 second if still playing
                                setTimeout(() => {
                                    if (!firstPlayer.paused) {
                                        firstPlayer.muted = false;
                                        firstPlayer.volume = 1;
                                    }
                                }, 1000);
                            }).catch(err2 => {
                                firstPlayer.muted = false;
                            });
                        });
                    }, 200);
                });
            }
        }

        /**
         * Check if should process this video
         */
        shouldProcessVideo(video) {
            // Process ALL video tags, regardless of source
            // Unless they have a specific class to exclude them (optional safety)
            if (video.classList.contains('no-plyr')) {
                return false;
            }

            return true;
        }

        /**
         * Check if URL is a local video file (supports multiple formats)
         */
        isLocalMP4(url) {
            if (!url) return false;

            // Exclude YouTube, Vimeo, and other video platforms
            const externalPlatforms = [
                'youtube.com',
                'youtu.be',
                'vimeo.com',
                'dailymotion.com',
                'facebook.com',
                'instagram.com',
                'tiktok.com'
            ];

            const lowerUrl = url.toLowerCase();

            // Check if it's from an external platform
            for (const platform of externalPlatforms) {
                if (lowerUrl.includes(platform)) {
                    return false;
                }
            }

            // Check if it's a local URL (same domain or relative path)
            const isLocal = url.startsWith('/') ||
                url.startsWith('./') ||
                url.includes(window.location.hostname);

            // Get supported formats from settings or use defaults
            const supportedFormatsString = this.settings.video_supported_formats || 'mp4,webm,ogg,ogv,mkv,mov,avi,m4v,3gp,flv';
            const videoFormats = supportedFormatsString.split(',').map(format => '.' + format.trim().replace(/^\./, ''));

            // Check if URL contains any supported video format
            const isVideoFile = videoFormats.some(format => lowerUrl.includes(format));

            if (isLocal && isVideoFile) {
                // Warn about formats with limited browser support
                if (lowerUrl.includes('.mkv')) {
                    // console.warn('WP Genius: MKV format detected. Browser support depends on installed codecs.');
                } else if (lowerUrl.includes('.avi') || lowerUrl.includes('.mov')) {
                    // console.warn('WP Genius: ' + (lowerUrl.includes('.avi') ? 'AVI' : 'MOV') + ' format detected. Browser support may be limited.');
                }
            }

            return isLocal && isVideoFile;
        }

        /**
         * Convert iframe to video element for local MP4
         */
        convertIframeToVideo(iframe, src) {
            try {
                // Create video element
                const video = document.createElement('video');
                video.controls = true;
                video.src = src;

                // Copy relevant attributes
                if (iframe.width) video.width = iframe.width;
                if (iframe.height) video.height = iframe.height;

                // Copy classes for styling
                if (iframe.className) {
                    video.className = iframe.className;
                }

                // Replace iframe with video
                iframe.parentNode.replaceChild(video, iframe);

                return video;
            } catch (error) {
                // console.error('WP Genius: Failed to convert iframe:', error);
                return null;
            }
        }

        /**
         * Pause other players
         */
        pauseOthers(activePlayer) {
            this.players.forEach((player) => {
                if (player !== activePlayer && !player.paused) {
                    player.pause();
                }
            });
        }

        /**
         * Get unique video ID for progress tracking
         */
        getVideoId(video) {
            // Try to get src from video element
            const sourceElement = video.querySelector('source');
            let src = video.src || (sourceElement ? sourceElement.src : '') || '';

            if (!src) return null;

            // Extract filename from URL
            const urlParts = src.split('/');
            const filename = urlParts[urlParts.length - 1];

            // Use filename as ID (or create hash)
            return filename.replace(/[^a-zA-Z0-9]/g, '_');
        }

        /**
         * Save video progress to localStorage
         */
        saveProgress(videoId, currentTime) {
            try {
                localStorage.setItem(this.storageKey + videoId, currentTime.toString());
            } catch (error) {
                // console.warn('WP Genius: Failed to save video progress:', error);
            }
        }

        /**
         * Get saved progress from localStorage
         */
        getSavedProgress(videoId) {
            try {
                const saved = localStorage.getItem(this.storageKey + videoId);
                return saved ? parseFloat(saved) : 0;
            } catch (error) {
                // console.warn('WP Genius: Failed to get saved progress:', error);
                return 0;
            }
        }

        /**
         * Clear saved progress
         */
        clearProgress(videoId) {
            try {
                localStorage.removeItem(this.storageKey + videoId);
            } catch (error) {
                // console.warn('WP Genius: Failed to clear progress:', error);
            }
        }
    }

    /**
     * Initialize on DOM ready and Plyr loaded
     */
    function initWhenReady() {
        // Check if config is available
        if (typeof wpgVideoConfig === 'undefined') {
            return;
        }

        // Check if Plyr is loaded
        if (typeof Plyr === 'undefined') {
            // Retry after a short delay
            setTimeout(initWhenReady, 100);
            return;
        }

        // Initialize Video Optimizer
        window.wpgVideoOptimizer = new WPGeniusVideoOptimizer(wpgVideoConfig);
    }

    // Start initialization when DOM is ready
    $(document).ready(initWhenReady);

})(jQuery);
