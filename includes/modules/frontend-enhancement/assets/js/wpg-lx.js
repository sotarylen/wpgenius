/**
 * WP Genius LX Music Connector
 * Local dock for the site owner's LX Music desktop player (P3, machine-local).
 *
 * Talks only to http://127.0.0.1:<port> from the browser; the server never
 * proxies localhost. Offline state offers a launch button that triggers the
 * lxmusic:// scheme and polls until the player answers.
 *
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */
(function () {
    'use strict';

    function Config() {
        var c = window.wpgLxConfig || {};
        this.port = c.port || 23330;
        this.timeout = c.timeout || 2000;
        this.useSse = c.useSse !== false;
        this.i18n = c.i18n || {};
        this.base = 'http://127.0.0.1:' + this.port;
    }

    function WpgLxConnector() {
        this.cfg = new Config();
        this.state = { online: false, status: 'stoped', name: '', singer: '', progress: 0, duration: 0, mute: false };
        this.timers = [];
        this.sse = null;
        this.buildDock();
        this.probe();
    }

    /* ---------------- Dock UI (DOM APIs, no innerHTML) ---------------- */

    WpgLxConnector.prototype.buildDock = function () {
        var cfg = this.cfg, t = cfg.i18n;
        var dock = document.createElement('div');
        dock.className = 'wpg-lx-dock';
        dock.setAttribute('role', 'region');
        dock.setAttribute('aria-label', t.ariaDock || 'LX Music player');

        this.$cover = document.createElement('img');
        this.$cover.className = 'wpg-lx-dock__cover';
        this.$cover.alt = '';

        this.$title = document.createElement('span');
        this.$title.className = 'wpg-lx-dock__title';

        this.$singer = document.createElement('span');
        this.$singer.className = 'wpg-lx-dock__singer';

        this.$bar = document.createElement('div');
        this.$bar.className = 'wpg-lx-dock__bar';
        this.$barFill = document.createElement('div');
        this.$barFill.className = 'wpg-lx-dock__bar-fill';
        this.$bar.appendChild(this.$barFill);

        this.$time = document.createElement('span');
        this.$time.className = 'wpg-lx-dock__time';

        // Controls
        this.$prev = this.button('prev', t.prev, 'wpg-lx-dock__btn');
        this.$play = this.button('play', t.play, 'wpg-lx-dock__btn wpg-lx-dock__btn--play');
        this.$next = this.button('next', t.next, 'wpg-lx-dock__btn');
        this.$mute = this.button('mute', t.mute, 'wpg-lx-dock__btn');

        this.$offline = document.createElement('div');
        this.$offline.className = 'wpg-lx-dock__offline';
        this.$offlineNote = document.createElement('span');
        this.$offlineNote.className = 'wpg-lx-dock__offline-note';
        var launch = document.createElement('button');
        launch.className = 'wpg-lx-dock__launch';
        launch.type = 'button';
        launch.textContent = t.launch || 'Start LX Music';
        this.$offline.appendChild(this.$offlineNote);
        this.$offline.appendChild(launch);

        this.$info = document.createElement('div');
        this.$info.className = 'wpg-lx-dock__info';

        var header = document.createElement('div');
        header.className = 'wpg-lx-dock__head';
        header.appendChild(this.$title);
        header.appendChild(this.$singer);

        this.$info.appendChild(header);
        this.$info.appendChild(this.$bar);
        var foot = document.createElement('div');
        foot.className = 'wpg-lx-dock__foot';
        foot.appendChild(this.$time);
        foot.appendChild(this.$prev);
        foot.appendChild(this.$play);
        foot.appendChild(this.$next);
        foot.appendChild(this.$mute);
        this.$info.appendChild(foot);

        this.$body = document.createElement('div');
        this.$body.className = 'wpg-lx-dock__body';
        this.$body.appendChild(this.$cover);
        this.$body.appendChild(this.$info);

        dock.appendChild(this.$body);
        dock.appendChild(this.$offline);
        document.body.appendChild(dock);

        var self = this;
        var map = {
            prev: function () { return self.control('skip-prev'); },
            play: function () { self.control(self.state.status === 'playing' ? 'pause' : 'play'); },
            next: function () { return self.control('skip-next'); },
            mute: function () { return self.control('mute?mute=' + (self.state.mute ? 'false' : 'true')); }
        };
        this.$prev.addEventListener('click', function () { map.prev(); });
        this.$next.addEventListener('click', function () { map.next(); });
        this.$play.addEventListener('click', function () { map.play(); });
        this.$mute.addEventListener('click', function () { map.mute(); });
        launch.addEventListener('click', function () {
            self.$offlineNote.textContent = t.launching || 'Waiting for LX Music…';
            if (window.location && window.location.href) {
                window.location.href = 'lxmusic://player/togglePlay';
            }
            self.pollUntilOnline();
        });
    };

    WpgLxConnector.prototype.button = function (name, label, cls) {
        var b = document.createElement('button');
        b.className = cls;
        b.type = 'button';
        b.setAttribute('aria-label', label || name);
        b.appendChild(this.icon(name));
        return b;
    };

    WpgLxConnector.prototype.icon = function (name) {
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('width', '18');
        svg.setAttribute('height', '18');
        var p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        var d = {
            prev: 'M6 6h2v12H6zM20 6l-9 6 9 6V6z',
            play: 'M8 5v14l11-7z',
            next: 'M16 6h2v12h-2zM4 6l9 6-9 6z',
            mute: 'M3 9v6h4l5 5V4L7 9H3zM16 8c1.5 1.5 1.5 4.5 0 6M18.5 5.5c3 3 3 8 0 11'
        }[name] || '';
        p.setAttribute('d', d);
        svg.appendChild(p);
        return svg;
    };

    WpgLxConnector.prototype.setOffline = function (offline, note) {
        var self = this;
        this.$offline.classList.toggle('wpg-lx-dock__offline--show', offline);
        this.$body.classList.toggle('wpg-lx-dock__body--hidden', offline);
        if (note) {
            this.$offlineNote.textContent = note;
        } else if (offline) {
            this.$offlineNote.textContent = this.cfg.i18n.offlineTitle || 'LX Music is not running';
        }
        if (!offline) {
            // Redraw title once online.
            self.renderStatus();
        }
    };

    WpgLxConnector.prototype.renderStatus = function () {
        var s = this.state;
        this.$title.textContent = s.name || '';
        this.$singer.textContent = s.singer || '';
        var pct = s.duration > 0 ? Math.min(100, (s.progress / s.duration) * 100) : 0;
        this.$barFill.style.setProperty('--wpg-lx-pct', pct.toFixed(1) + '%');
        this.$time.textContent = this.fmt(s.progress) + ' / ' + this.fmt(s.duration);
        this.$play.setAttribute('aria-label', s.status === 'playing' ? (this.cfg.i18n.pause || 'Pause') : (this.cfg.i18n.play || 'Play'));
        this.$mute.setAttribute('aria-label', s.mute ? (this.cfg.i18n.unmute || 'Unmute') : (this.cfg.i18n.mute || 'Mute'));
        this.$body.classList.toggle('wpg-lx-dock__body--playing', s.status === 'playing');
    };

    WpgLxConnector.prototype.fmt = function (sec) {
        sec = Math.max(0, Math.floor(sec || 0));
        var m = Math.floor(sec / 60);
        var ss = String(sec % 60).padStart(2, '0');
        return m + ':' + ss;
    };

    /* ---------------- Network ---------------- */

    WpgLxConnector.prototype.probe = function () {
        var self = this;
        var ctl = new AbortController();
        var timer = setTimeout(function () { ctl.abort(); }, this.cfg.timeout);

        fetch(this.cfg.base + '/status', { signal: ctl.signal, cache: 'no-store' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                clearTimeout(timer);
                if (!data) {
                    self.setOffline(true);
                    return;
                }
                self.applyStatus(data);
                self.setOffline(false);
                if (self.cfg.useSse) {
                    self.startSse();
                } else {
                    self.startPolling();
                }
            })
            .catch(function () {
                clearTimeout(timer);
                self.setOffline(true);
            });
    };

    WpgLxConnector.prototype.applyStatus = function (data) {
        var self = this;
        if (!data || typeof data !== 'object') {
            return;
        }
        if (typeof data.status === 'string') {
            self.state.status = data.status;
        }
        if (typeof data.name === 'string') {
            self.state.name = data.name;
        }
        if (typeof data.singer === 'string') {
            self.state.singer = data.singer;
        }
        if (typeof data.progress === 'number') {
            self.state.progress = data.progress;
        }
        if (typeof data.duration === 'number') {
            self.state.duration = data.duration;
        }
        if (typeof data.mute === 'boolean') {
            self.state.mute = data.mute;
        }
        self.renderStatus();
    };

    WpgLxConnector.prototype.startSse = function () {
        var self = this;
        if (this.sse || typeof EventSource === 'undefined') {
            return;
        }
        var es = new EventSource(this.cfg.base + '/subscribe-player-status');
        this.sse = es;
        es.onmessage = function (e) {
            try {
                self.applyKeyed(JSON.parse(e.data));
            } catch (err) { /* ignore */ }
        };
    };

    WpgLxConnector.prototype.applyKeyed = function (data) {
        var self = this;
        if (!data || typeof data !== 'object') {
            return;
        }
        Object.keys(data).forEach(function (k) {
            self.state[k] = data[k];
        });
        self.renderStatus();
    };

    WpgLxConnector.prototype.startPolling = function () {
        var self = this;
        this.stopTimers();
        var iv = setInterval(function () {
            self.fetchStatus().catch(function () { self.setOffline(true); });
        }, 2000);
        this.timers.push(iv);
    };

    WpgLxConnector.prototype.fetchStatus = function () {
        var self = this;
        return fetch(this.cfg.base + '/status', { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) { self.applyStatus(d); self.setOffline(false); });
    };

    WpgLxConnector.prototype.stopTimers = function () {
        this.timers.forEach(clearInterval);
        this.timers = [];
    };

    WpgLxConnector.prototype.control = function (path) {
        return fetch(this.cfg.base + '/' + path, { cache: 'no-store' }).catch(function () { /* offline */ });
    };

    WpgLxConnector.prototype.pollUntilOnline = function () {
        var self = this;
        var tries = 0;
        var iv = setInterval(function () {
            tries += 1;
            self.fetchStatus()
                .then(function () {
                    clearInterval(iv);
                    if (self.cfg.useSse) {
                        self.startSse();
                    } else {
                        self.startPolling();
                    }
                })
                .catch(function () {
                    if (tries >= 12) {
                        clearInterval(iv);
                        self.setOffline(true);
                    }
                });
        }, 2000);
    };

    /* ---------------- Boot ---------------- */

    function boot() {
        if (typeof wpgLxConfig === 'undefined' || !wpgLxConfig.canSee) {
            return;
        }
        window.wpgLx = new WpgLxConnector();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();