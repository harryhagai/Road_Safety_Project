// Home map speed widget and alert UI helpers.
(function (window) {
    const app = window.RSRSHomeMap;
    const state = app.state;

    function cacheSpeedWidget() {
        if (state.ui.speedWidget) return;

        state.ui.speedWidget = document.querySelector('[data-home-speed-widget]');
        state.ui.speedValueEl = document.querySelector('[data-home-speed-value]');
        state.ui.speedStatusEl = document.querySelector('[data-home-speed-status]');
        state.ui.speedAlertEl = document.querySelector('[data-home-speed-alert]');
        state.ui.speedAlertIconEl = document.querySelector('[data-home-speed-alert-icon]');
        state.ui.speedAlertLabelEl = document.querySelector('[data-home-speed-alert-label]');
        state.ui.speedAlertMessageEl = document.querySelector('[data-home-speed-alert-message]');
        state.ui.speedAlertLocationEl = document.querySelector('[data-home-speed-alert-location]');
        state.ui.speedAlertLimitEl = document.querySelector('[data-home-speed-alert-limit]');
    }

    function updateSpeedDisplay(speedKmh, statusText, isLive) {
        cacheSpeedWidget();

        if (!state.ui.speedWidget || !state.ui.speedValueEl || !state.ui.speedStatusEl) {
            return;
        }

        const safeSpeed = Number.isFinite(speedKmh) ? Math.max(0, speedKmh) : 0;
        const ringDuration = Math.max(0.45, 3.2 - Math.min(safeSpeed, 120) / 42);

        state.ui.speedValueEl.textContent = String(Math.round(safeSpeed));
        state.ui.speedStatusEl.textContent = statusText;
        state.ui.speedWidget.style.setProperty('--home-speed-ring-duration', `${ringDuration.toFixed(2)}s`);
        state.ui.speedWidget.classList.toggle('is-live', Boolean(isLive));
        state.ui.speedWidget.classList.toggle('is-idle', !isLive);
    }

    function updateSpeedAlert(options) {
        cacheSpeedWidget();

        if (
            !state.ui.speedAlertEl ||
            !state.ui.speedAlertIconEl ||
            !state.ui.speedAlertLabelEl ||
            !state.ui.speedAlertMessageEl
        ) {
            return;
        }

        const alertState = options?.state || 'idle';
        const icons = {
            idle: 'bi-info-circle-fill',
            info: 'bi-info-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            danger: 'bi-shield-exclamation',
            success: 'bi-check-circle-fill',
        };

        state.ui.speedAlertEl.classList.remove(
            'home-speed-alert--idle',
            'home-speed-alert--info',
            'home-speed-alert--warning',
            'home-speed-alert--danger',
            'home-speed-alert--success'
        );
        state.ui.speedAlertEl.classList.add(`home-speed-alert--${alertState}`);
        state.ui.speedAlertIconEl.innerHTML = `<i class="bi ${icons[alertState] || icons.info}" aria-hidden="true"></i>`;
        state.ui.speedAlertLabelEl.textContent = options?.label || 'Speed info';
        state.ui.speedAlertMessageEl.textContent = options?.message || 'We are checking your location and the nearest speed rule.';

        if (state.ui.speedAlertLocationEl) {
            state.ui.speedAlertLocationEl.textContent = `Segment: ${options?.location || 'waiting...'}`;
        }

        if (state.ui.speedAlertLimitEl) {
            state.ui.speedAlertLimitEl.textContent = `Speed limit: ${options?.limit || 'unknown'}`;
        }
    }

    app.ui = {
        cacheSpeedWidget,
        updateSpeedDisplay,
        updateSpeedAlert,
    };
})(window);
