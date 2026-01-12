/**
 * WPGenius Admin UI
 * 
 * Global notification and interaction system.
 */
(function (window, document, $) {
    'use strict';

    const W2P_UI = {
        /**
         * Initialize the UI system
         */
        init: function () {
            this.createToastContainer();
            this.createModalOverlay();
        },

        /**
         * Create the container for toast notifications
         */
        createToastContainer: function () {
            if (!document.querySelector('.w2p-toast-container')) {
                const container = document.createElement('div');
                container.className = 'w2p-toast-container';
                document.body.appendChild(container);
            }
        },

        /**
         * Create the modal overlay structure
         */
        createModalOverlay: function () {
            if (!document.querySelector('.w2p-modal-overlay')) {
                const overlay = document.createElement('div');
                overlay.className = 'w2p-modal-overlay';
                overlay.innerHTML = `
                    <div class="w2p-confirm-modal">
                        <div class="w2p-modal-header">
                            <h3 class="w2p-modal-title"></h3>
                            <button type="button" class="w2p-modal-close dashicons dashicons-no-alt" style="background:none;border:none;cursor:pointer;"></button>
                        </div>
                        <div class="w2p-modal-body"></div>
                        <div class="w2p-modal-footer">
                            
                            <button type="button" class="w2p-btn w2p-btn-secondary w2p-modal-cancel">
                            <i class="fa-solid fa-xmark"></i>
                            ${window.w2p_ui_i18n ? window.w2p_ui_i18n.cancel : 'Cancel'}</button>

                            <button type="button" class="w2p-btn w2p-btn-primary w2p-modal-confirm">
                            <i class="fa-solid fa-check"></i>
                            ${window.w2p_ui_i18n ? window.w2p_ui_i18n.confirm : 'Confirm'}</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(overlay);

                // Bind close events
                overlay.querySelector('.w2p-modal-close').addEventListener('click', () => this.closeModal());
                overlay.querySelector('.w2p-modal-cancel').addEventListener('click', () => this.closeModal());
            }
        },

        /**
         * Show a toast notification
         * 
         * @param {string} message Message to display
         * @param {string} type 'info', 'success', 'warning', 'error'
         * @param {number} duration Duration in ms (default 3000)
         */
        toast: function (message, type = 'info', duration = 3000) {
            const container = document.querySelector('.w2p-toast-container');
            const toast = document.createElement('div');

            // Get icon
            let iconClass = 'dashicons-info';
            if (type === 'success') iconClass = 'dashicons-yes-alt';
            else if (type === 'warning') iconClass = 'dashicons-warning';
            else if (type === 'error') iconClass = 'dashicons-dismiss';

            toast.className = `w2p-toast ${type}`;
            toast.innerHTML = `
                <div class="w2p-toast-icon"><span class="dashicons ${iconClass}"></span></div>
                <div class="w2p-toast-message">${message}</div>
                <div class="w2p-toast-close"><i class="fa-solid fa-xmark"></i></div>
            `;

            container.appendChild(toast);

            // Animate in
            requestAnimationFrame(() => toast.classList.add('show'));

            // Auto dismiss
            const timer = setTimeout(() => {
                this.dismissToast(toast);
            }, duration);

            // Manual dismiss
            toast.querySelector('.w2p-toast-close').addEventListener('click', () => {
                clearTimeout(timer);
                this.dismissToast(toast);
            });
        },

        dismissToast: function (toast) {
            toast.classList.add('fade-out');
            toast.addEventListener('transitionend', () => {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, { once: true });

            // Fallback timeout in case transitionend fails
            setTimeout(() => {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 400);
        },

        /**
         * Show a confirm modal
         * 
         * @param {string} message Confirmation message
         * @param {Function} onConfirm Callback when confirmed
         * @param {Function} onCancel Callback when cancelled
         */
        confirm: function (message, onConfirm, onCancel) {
            const overlay = document.querySelector('.w2p-modal-overlay');
            const title = overlay.querySelector('.w2p-modal-title');
            const body = overlay.querySelector('.w2p-modal-body');
            const confirmBtn = overlay.querySelector('.w2p-modal-confirm');
            const cancelBtn = overlay.querySelector('.w2p-modal-cancel');

            title.textContent = window.w2p_ui_i18n ? window.w2p_ui_i18n.confirm_title : 'Confirm Action';
            body.textContent = message;

            // Reset clones to remove old listeners
            const newConfirmBtn = confirmBtn.cloneNode(true);
            const newCancelBtn = cancelBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

            newConfirmBtn.addEventListener('click', () => {
                this.closeModal();
                if (typeof onConfirm === 'function') onConfirm();
            });

            newCancelBtn.addEventListener('click', () => {
                this.closeModal();
                if (typeof onCancel === 'function') onCancel();
            });

            overlay.classList.add('active');
        },

        closeModal: function () {
            document.querySelector('.w2p-modal-overlay').classList.remove('active');
        },

        /**
         * Toggle loading state for a button
         * 
         * @param {HTMLElement|string} selector Button element or selector
         * @param {boolean} isLoading Whether to show loading state
         */
        loading: function (selector, isLoading) {
            let btn = (typeof selector === 'string') ? document.querySelector(selector) : selector;

            // Handle jQuery object
            if (btn && btn.jquery && btn.length > 0) {
                btn = btn[0];
            }

            if (!btn || !btn.classList) return;

            if (isLoading) {
                btn.classList.add('w2p-btn-loading');
                btn.setAttribute('disabled', 'disabled');
            } else {
                btn.classList.remove('w2p-btn-loading');
                btn.removeAttribute('disabled');
            }
        },

        showLoading: function (selector) {
            this.loading(selector, true);
        },

        hideLoading: function (selector) {
            this.loading(selector, false);
        }
    };

    // Expose global API immediately
    window.w2p = {
        toast: W2P_UI.toast.bind(W2P_UI),
        confirm: W2P_UI.confirm.bind(W2P_UI),
        loading: W2P_UI.loading.bind(W2P_UI),
        showLoading: W2P_UI.showLoading.bind(W2P_UI),
        hideLoading: W2P_UI.hideLoading.bind(W2P_UI)
    };

    // Auto init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            W2P_UI.init();
            checkUpdatedParam();
        });
    } else {
        W2P_UI.init();
        checkUpdatedParam();
    }

    function checkUpdatedParam() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('updated') === '1') {
            // Remove param from URL without refresh
            const newUrl = window.location.href.replace(/[?&]updated=1/, '');
            window.history.replaceState({}, document.title, newUrl);

            W2P_UI.toast(window.w2p_ui_i18n ? window.w2p_ui_i18n.settings_saved : 'Settings Saved!', 'success');
        }
    }

})(window, document, jQuery);
