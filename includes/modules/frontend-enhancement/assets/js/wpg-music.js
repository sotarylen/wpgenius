/**
 * WP Genius Music
 * Frontend playlist player initializer (APlayer kernel).
 *
 * Theme is page-adaptive: the page background luminance decides whether the
 * player card uses the light (default) or dark palette, so the player never
 * clashes with its surroundings.
 *
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */
(function () {
    'use strict';

    /**
     * Rough page background luminance (0..1). Used to pick the player theme.
     *
     * @returns {number}
     */
    function pageBackgroundLuminance() {
        var el = document.body;
        var rgb = getComputedStyle(el).backgroundColor.match(/\d+(\.\d+)?/g) || ['255', '255', '255'];
        var r = parseInt(rgb[0], 10) / 255;
        var g = parseInt(rgb[1], 10) / 255;
        var b = parseInt(rgb[2], 10) / 255;
        // sRGB relative luminance (WCAG).
        var lin = function (c) {
            return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        };
        return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
    }

    /**
     * Apply .wpg-music-dark / .wpg-music-light to the card shell and the
     * APlayer container (and any detached fixed dock APlayer creates).
     *
     * @param {HTMLElement} container Player container.
     */
    function applyTheme(container) {
        var dark = pageBackgroundLuminance() < 0.4;
        var cls = dark ? 'wpg-music-dark' : 'wpg-music-light';
        var els = [];
        var shell = container.closest('.wpg-music-card');
        if (shell) {
            els.push(shell);
        }
        els.push(container);
        if (container.aplayer && container.aplayer.elements) {
            var elm = container.aplayer.elements.elm;
            if (elm && elm.classList.contains('aplayer-fixed')) {
                els.push(elm);
            }
        }
        els.forEach(function (el) {
            if (!el) {
                return;
            }
            el.classList.remove('wpg-music-dark', 'wpg-music-light');
            el.classList.add(cls);
        });
    }

    /**
     * Initialize every .wpg-music-playlist container with an APlayer instance.
     */
    function initPlayers() {
        if (typeof APlayer === 'undefined' || typeof wpgMusicConfig === 'undefined') {
            return;
        }

        var containers = document.querySelectorAll('.wpg-music-playlist');
        if (!containers.length) {
            return;
        }

        var theme = getComputedStyle(document.documentElement).getPropertyValue('--w2p-color-primary').trim();
        if (!theme) {
            theme = '#10b981';
        }

        Array.prototype.forEach.call(containers, function (container) {
            var tracks = [];
            try {
                tracks = JSON.parse(container.getAttribute('data-wpg-config') || '[]');
            } catch (e) {
                tracks = [];
            }
            if (!tracks.length) {
                return;
            }

            var order = container.getAttribute('data-wpg-order') || 'sequence';
            var mini = container.getAttribute('data-wpg-mode') === 'mini';

            var player = new APlayer({
                container: container,
                audio: tracks,
                lrcType: 3,
                order: order === 'random' ? 'random' : 'list',
                loop: order === 'sequence' ? 'none' : 'all',
                mini: mini,
                mutex: true,
                listFolded: false,
                theme: theme
            });

            container.aplayer = player;
            applyTheme(container);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPlayers);
    } else {
        initPlayers();
    }
})();