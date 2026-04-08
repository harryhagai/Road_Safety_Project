(function () {
    const navigationStorageKey = 'hgss-inline-spinner-navigation';
    const disableNavigationOverlay = document.body?.dataset?.disableNavigationOverlay === '1';
    const enableLinkSpinners = document.body?.dataset?.inlineSpinnerLinks !== '0';
    const spinnerTheme = document.body?.dataset?.inlineSpinnerTheme || 'default';

    function ensureSpinnerStyles() {
        if (document.getElementById('inline-dotted-spinner-styles')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'inline-dotted-spinner-styles';
        style.textContent = `
            .inline-dotted-spinner {
                position: relative;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 18px;
                height: 18px;
                margin-right: 0.45rem;
                flex-shrink: 0;
            }

            .inline-dotted-spinner__track {
                display: none;
            }

            .inline-dotted-spinner__dots {
                position: absolute;
                inset: 0;
                border-radius: 50%;
                animation: inlineDottedSpinnerRotate 1.35s linear infinite;
            }

            .inline-dotted-spinner__dots span {
                position: absolute;
                width: 3px;
                height: 3px;
                border-radius: 50%;
                background: currentColor;
                opacity: 0.9;
            }

            .inline-dotted-spinner__dots span:nth-child(1) {
                top: 0;
                left: 50%;
                transform: translateX(-50%);
            }

            .inline-dotted-spinner__dots span:nth-child(2) {
                top: 2px;
                right: 2px;
            }

            .inline-dotted-spinner__dots span:nth-child(3) {
                right: 0;
                top: 50%;
                transform: translateY(-50%);
            }

            .inline-dotted-spinner__dots span:nth-child(4) {
                right: 2px;
                bottom: 2px;
            }

            .inline-dotted-spinner__dots span:nth-child(5) {
                bottom: 0;
                left: 50%;
                transform: translateX(-50%);
            }

            .inline-dotted-spinner__dots span:nth-child(6) {
                left: 2px;
                bottom: 2px;
            }

            .inline-dotted-spinner__dots span:nth-child(7) {
                left: 0;
                top: 50%;
                transform: translateY(-50%);
            }

            .inline-dotted-spinner__dots span:nth-child(8) {
                top: 2px;
                left: 2px;
            }

            @keyframes inlineDottedSpinnerRotate {
                to {
                    transform: rotate(360deg);
                }
            }

            .inline-spinner-navigation-overlay {
                position: fixed;
                inset: 0;
                z-index: 4000;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(255, 255, 255, 0.52);
                backdrop-filter: blur(2px);
                opacity: 0;
                visibility: hidden;
                transition: opacity 0.22s ease, visibility 0.22s ease;
            }

            .inline-spinner-navigation-overlay.is-visible {
                opacity: 1;
                visibility: visible;
            }

            .inline-spinner-navigation-overlay__spinner {
                color: #1098d4;
                width: 28px;
                height: 28px;
                margin-right: 0;
            }

            .inline-spinner-button-active {
                background-color: #0d6efd !important;
                border-color: #0d6efd !important;
                color: #ffffff !important;
                box-shadow: none !important;
            }

            .inline-spinner-button-active:hover,
            .inline-spinner-button-active:focus,
            .inline-spinner-button-active:active {
                background-color: #0b5ed7 !important;
                border-color: #0a58ca !important;
                color: #ffffff !important;
                box-shadow: none !important;
            }

            .inline-spinner-button-active .inline-dotted-spinner__dots span {
                background: currentColor;
            }
        `;

        document.head.appendChild(style);
    }

    function spinnerMarkup() {
        return `
            <span class="inline-dotted-spinner" aria-hidden="true">
                <span class="inline-dotted-spinner__track"></span>
                <span class="inline-dotted-spinner__dots">
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </span>
        `;
    }

    function activateButton(button) {
        if (!button || button.dataset.noSpinner !== undefined || button.dataset.spinnerActive === '1') {
            return;
        }

        ensureSpinnerStyles();
        button.dataset.spinnerActive = '1';
        button.disabled = true;

        if (spinnerTheme === 'blue') {
            button.classList.add('inline-spinner-button-active');
        }

        const loadingText = button.getAttribute('data-loading-text')
            || button.dataset.loadingText
            || (button.tagName === 'INPUT' ? button.value : button.textContent.trim())
            || 'Processing...';

        if (button.tagName === 'INPUT') {
            button.dataset.originalValue = button.value;
            button.value = loadingText;
            return;
        }

        button.dataset.originalHtml = button.innerHTML;
        button.innerHTML = `
            ${spinnerMarkup()}
            <span style="font-weight:400;">${loadingText}</span>
        `;
    }

    function shouldHandleLink(link, event) {
        if (!link || link.dataset.noSpinner !== undefined || link.dataset.spinnerActive === '1') {
            return false;
        }

        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return false;
        }

        if (link.target && link.target !== '_self') {
            return false;
        }

        if (link.hasAttribute('download')) {
            return false;
        }

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
            return false;
        }

        const url = new URL(link.href, window.location.origin);
        if (url.origin !== window.location.origin) {
            return false;
        }

        if (url.href === window.location.href) {
            return false;
        }

        return true;
    }

    function activateLink(link) {
        if (!link || link.dataset.spinnerActive === '1') {
            return;
        }

        ensureSpinnerStyles();
        link.dataset.spinnerActive = '1';
        link.dataset.originalHtml = link.innerHTML;
        link.style.pointerEvents = 'none';
        link.innerHTML = `
            ${spinnerMarkup()}
            <span style="font-weight:400;">${link.dataset.loadingText || link.textContent.trim() || 'Loading...'}</span>
        `;
    }

    function ensureNavigationOverlay() {
        let overlay = document.getElementById('inline-spinner-navigation-overlay');
        if (overlay) {
            return overlay;
        }

        ensureSpinnerStyles();
        overlay = document.createElement('div');
        overlay.id = 'inline-spinner-navigation-overlay';
        overlay.className = 'inline-spinner-navigation-overlay';
        overlay.innerHTML = `
            <span class="inline-dotted-spinner inline-spinner-navigation-overlay__spinner" aria-hidden="true">
                <span class="inline-dotted-spinner__track"></span>
                <span class="inline-dotted-spinner__dots">
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </span>
        `;

        document.body.appendChild(overlay);
        return overlay;
    }

    function showNavigationOverlay() {
        if (disableNavigationOverlay) {
            return;
        }
        const overlay = ensureNavigationOverlay();
        overlay.classList.add('is-visible');
    }

    function hideNavigationOverlay() {
        const overlay = document.getElementById('inline-spinner-navigation-overlay');
        if (overlay) {
            overlay.classList.remove('is-visible');
        }
    }

    function markNavigationPending() {
        if (disableNavigationOverlay) {
            return;
        }

        try {
            sessionStorage.setItem(navigationStorageKey, '1');
        } catch (error) {
            // Ignore storage issues.
        }

        window.setTimeout(function () {
            showNavigationOverlay();
        }, 180);
    }

    function clearPendingNavigation() {
        try {
            sessionStorage.removeItem(navigationStorageKey);
        } catch (error) {
            // Ignore storage issues.
        }
    }

    try {
        if (!disableNavigationOverlay && sessionStorage.getItem(navigationStorageKey) === '1') {
            showNavigationOverlay();
            window.addEventListener('load', function () {
                hideNavigationOverlay();
                clearPendingNavigation();
            }, { once: true });
        }
    } catch (error) {
        // Ignore storage issues.
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('button[type="submit"], input[type="submit"], button[data-inline-spinner]');
        if (button && button.dataset.noSpinner === undefined) {
            if (button.form) {
                button.form.__lastClickedSubmit = button;
            } else {
                activateButton(button);
            }
        }

        if (!enableLinkSpinners) {
            return;
        }

        const link = event.target.closest('.header-nav a, .footer-wrapper a, a.btn, .hero-chip-link, .auth-text-link, .auth-back-link, .auth-login-back-link, a[data-inline-spinner-link]');
        if (!shouldHandleLink(link, event)) {
            return;
        }

        event.preventDefault();
        activateLink(link);
        markNavigationPending();

        window.setTimeout(function () {
            window.location.href = link.href;
        }, 120);
    }, true);

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-disable-inline-spinner')) {
            return;
        }

        const submitter = event.submitter
            || form.__lastClickedSubmit
            || form.querySelector('button[type="submit"]:not([data-no-spinner]), input[type="submit"]:not([data-no-spinner])');

        activateButton(submitter);
        markNavigationPending();
    }, true);
})();
