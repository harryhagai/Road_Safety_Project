(function () {
    const LOADER_MIN_VISIBLE_MS = 1400;
    const LOADER_TIMEOUT_MS = 5000;

    function initHomeLoader() {
        const loader = document.querySelector('[data-home-map-loader]');
        const mapRoot = document.getElementById('mainPublicMap');

        if (!loader || !mapRoot) {
            return;
        }

        let hasHiddenLoader = false;
        const loaderStartedAt = Date.now();

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

        if (mapRoot.mapApi) {
            hideLoader();
            return;
        }

        document.body.classList.add('home-loader-active');
        mapRoot.addEventListener('rsrs:map-ready', hideLoader, { once: true });
        window.addEventListener('load', hideLoader, { once: true });
        window.setTimeout(hideLoader, LOADER_TIMEOUT_MS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHomeLoader, { once: true });
        return;
    }

    initHomeLoader();
})();
