// Frontend helper for rsrsHomeLoader interactions in the RSRS interface.

(function () {
    const LOADER_MIN_VISIBLE_MS = 1400;
    const LOADER_TIMEOUT_MS = 9000;

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function initHomeLoader() {
        const loader = document.querySelector('[data-home-map-loader]');
        const mapRoot = document.getElementById('mainPublicMap');

        if (!loader || !mapRoot) {
            return;
        }

        let hasHiddenLoader = false;
        const loaderStartedAt = Date.now();

        // Encapsulate one UI behavior so the page stays easier to maintain.

        const finalizeHide = function () {
            if (hasHiddenLoader || !loader.isConnected) {
                return;
            }

            hasHiddenLoader = true;
            document.body.classList.remove('home-loader-active');
            loader.classList.add('is-hidden');
            loader.setAttribute('aria-hidden', 'true');

            window.setTimeout(function () {
                loader.remove();
            }, 320);
        };

        // Encapsulate one UI behavior so the page stays easier to maintain.

        const hideLoader = function () {
            if (hasHiddenLoader) {
                return;
            }

            const elapsed = Date.now() - loaderStartedAt;
            const remaining = Math.max(0, LOADER_MIN_VISIBLE_MS - elapsed);

            if (remaining > 0) {
                window.setTimeout(finalizeHide, remaining);
                return;
            }

            finalizeHide();
        };

        document.body.classList.add('home-loader-active');
        document.addEventListener('rsrs:home-location-ready', hideLoader, { once: true });
        window.setTimeout(hideLoader, LOADER_TIMEOUT_MS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHomeLoader, { once: true });
        return;
    }

    initHomeLoader();
})();
