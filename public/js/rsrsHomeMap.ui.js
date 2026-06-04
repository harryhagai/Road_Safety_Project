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
        state.ui.speedAlertLocationEl = document.querySelector('[data-home-speed-alert-location]');
        state.ui.speedAlertLimitEl = document.querySelector('[data-home-speed-alert-limit]');
        state.ui.speedAlertSymbolEl = document.querySelector('[data-home-speed-alert-symbol]');
        state.ui.speedAlertCountEl = document.querySelector('[data-home-speed-alert-count]');
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

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    }

    function countdownPopupHtml(options, seconds) {
        const ruleLabel = String(options?.ruleLabel || 'SPEED RULE').trim().toUpperCase() || 'SPEED RULE';
        const location = options?.location || 'waiting...';
        const limit = options?.limit || 'unknown';

        return `
            <div class="home-countdown-alert">
                <div class="home-countdown-alert__seconds">${Math.ceil(seconds)}</div>
                <div class="home-countdown-alert__meta">
                    <span>SEGMENT: ${escapeHtml(location)}</span>
                    <span>${escapeHtml(ruleLabel)}: ${escapeHtml(limit)}</span>
                </div>
            </div>
        `;
    }

    function reportPopupHtml(options) {
        const ruleName = options?.ruleName || options?.limit || 'this rule';
        const location = options?.location || 'matched road segment';
        const reference = options?.reference ? `<span>REFERENCE: ${escapeHtml(options.reference)}</span>` : '';
        const message = options?.submitting
            ? `Preparing to submit a violation for rule "${escapeHtml(ruleName)}"...`
            : options?.duplicate
            ? `A violation for rule "${escapeHtml(ruleName)}" has already been reported.`
            : `A violation for rule "${escapeHtml(ruleName)}" has been reported.`;

        return `
            <div class="home-report-alert">
                <div class="home-report-alert__message">${message}</div>
                <div class="home-report-alert__meta">
                    <span>SEGMENT: ${escapeHtml(location)}</span>
                    ${reference}
                </div>
            </div>
        `;
    }

    function showReportSubmittingPopup(options = {}) {
        stopCountdownTicker();

        if (!window.Swal) return;

        state.ui.countdownPopupOpen = true;
        state.ui.countdownPopupKey = 'submitting-report';

        const html = reportPopupHtml({
            ...options,
            ruleName: options.ruleName || options.limit || 'this rule',
            submitting: true,
        });
        const title = options.title || 'Submitting report';

        if (window.Swal.isVisible()) {
            window.Swal.update({
                title,
                html,
                icon: 'warning',
                showConfirmButton: false,
            });
            return;
        }

        window.Swal.fire({
            title,
            html,
            icon: 'warning',
            showConfirmButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            backdrop: true,
            customClass: {
                popup: 'rsrs-home-swal',
                title: 'rsrs-home-swal__title',
                htmlContainer: 'rsrs-home-swal__body',
            },
            didClose: () => {
                state.ui.countdownPopupOpen = false;
                state.ui.countdownPopupKey = null;
            },
        });
    }

    function showReportSubmittedPopup(options = {}) {
        stopCountdownTicker();

        if (!window.Swal) return;

        state.ui.countdownPopupOpen = true;
        state.ui.countdownPopupKey = 'reported';

        if (window.Swal.isVisible()) {
            window.Swal.update({
                title: options.title || 'Violation reported',
                html: reportPopupHtml(options),
                icon: 'success',
                showConfirmButton: false,
            });
            return;
        }

        window.Swal.fire({
            title: options.title || 'Violation reported',
            html: reportPopupHtml(options),
            icon: 'success',
            showConfirmButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            backdrop: true,
            customClass: {
                popup: 'rsrs-home-swal',
                title: 'rsrs-home-swal__title',
                htmlContainer: 'rsrs-home-swal__body',
            },
            didClose: () => {
                state.ui.countdownPopupOpen = false;
                state.ui.countdownPopupKey = null;
            },
        });
    }

    function syncCountdownPopup(options, seconds) {
        if (!window.Swal) return;

        const popupKey = [
            options?.location || '',
            options?.ruleLabel || 'SPEED RULE',
            options?.limit || '',
        ].join('|');
        const title = options?.popupTitle || (options?.ruleLabel === 'RULE' ? 'Rule warning' : 'Speed warning');

        if (state.ui.countdownPopupKey !== popupKey || !state.ui.countdownPopupOpen) {
            state.ui.countdownPopupKey = popupKey;
            state.ui.countdownPopupOpen = true;

            window.Swal.fire({
                title,
                html: countdownPopupHtml(options, seconds),
                icon: 'warning',
                showConfirmButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false,
                backdrop: true,
                customClass: {
                    popup: 'rsrs-home-swal',
                    title: 'rsrs-home-swal__title',
                    htmlContainer: 'rsrs-home-swal__body',
                },
                didClose: () => {
                    state.ui.countdownPopupOpen = false;
                    state.ui.countdownPopupKey = null;
                },
            });
            return;
        }

        if (window.Swal.isVisible()) {
            window.Swal.update({
                title,
                html: countdownPopupHtml(options, seconds),
            });
        }
    }

    function renderCountdownTick() {
        const options = state.ui.countdownOptions;
        const endsAt = Number(state.ui.countdownEndsAt);

        if (!options || !Number.isFinite(endsAt)) {
            return;
        }

        const remainingSeconds = Math.max(0, (endsAt - Date.now()) / 1000);
        const displaySeconds = Math.max(0, Math.ceil(remainingSeconds));

        if (state.ui.speedAlertCountEl) {
            state.ui.speedAlertCountEl.textContent = `${displaySeconds}s`;
        }

        if (displaySeconds !== state.ui.lastCountdownDisplaySeconds) {
            state.ui.lastCountdownDisplaySeconds = displaySeconds;
            syncCountdownPopup(options, displaySeconds);
        }

        if (remainingSeconds <= 0 && state.ui.countdownTimerId) {
            window.clearInterval(state.ui.countdownTimerId);
            state.ui.countdownTimerId = null;
        }
    }

    function startCountdownTicker(options, seconds) {
        const safeSeconds = Math.max(0, Number(seconds) || 0);

        state.ui.countdownOptions = { ...options };
        state.ui.countdownEndsAt = Date.now() + safeSeconds * 1000;
        state.ui.lastCountdownDisplaySeconds = null;

        renderCountdownTick();

        if (!state.ui.countdownTimerId) {
            state.ui.countdownTimerId = window.setInterval(renderCountdownTick, 250);
        }
    }

    function stopCountdownTicker() {
        if (state.ui.countdownTimerId) {
            window.clearInterval(state.ui.countdownTimerId);
        }

        state.ui.countdownTimerId = null;
        state.ui.countdownOptions = null;
        state.ui.countdownEndsAt = null;
        state.ui.lastCountdownDisplaySeconds = null;
    }

    function closeCountdownPopup() {
        stopCountdownTicker();

        if (!state.ui.countdownPopupOpen || !window.Swal || !window.Swal.isVisible()) {
            state.ui.countdownPopupOpen = false;
            state.ui.countdownPopupKey = null;
            return;
        }

        state.ui.countdownPopupOpen = false;
        state.ui.countdownPopupKey = null;
        window.Swal.close();
    }

    function updateSpeedAlert(options) {
        cacheSpeedWidget();

        if (
            !state.ui.speedAlertEl ||
            !state.ui.speedAlertLocationEl ||
            !state.ui.speedAlertLimitEl
        ) {
            return;
        }

        const alertState = options?.state || 'idle';
        const countdownSeconds = Number(options?.countdownSeconds);
        const isCounting = alertState === 'warning' && Number.isFinite(countdownSeconds) && countdownSeconds > 0;
        const statusSymbols = {
            idle: '-',
            info: 'OK',
            warning: '!',
            danger: '!',
            success: 'OK',
        };

        state.ui.speedAlertEl.classList.remove(
            'home-speed-alert--idle',
            'home-speed-alert--info',
            'home-speed-alert--warning',
            'home-speed-alert--danger',
            'home-speed-alert--success'
        );
        state.ui.speedAlertEl.classList.add(`home-speed-alert--${alertState}`);
        state.ui.speedAlertEl.classList.toggle('is-counting', isCounting);

        const ruleLabel = String(options?.ruleLabel || 'SPEED RULE').trim().toUpperCase() || 'SPEED RULE';

        state.ui.speedAlertLocationEl.textContent = `SEGMENT: ${options?.location || 'waiting...'}`;
        state.ui.speedAlertLimitEl.textContent = `${ruleLabel}: ${options?.limit || 'unknown'}`;

        if (state.ui.speedAlertSymbolEl) {
            state.ui.speedAlertSymbolEl.textContent = statusSymbols[alertState] || '-';
        }

        if (state.ui.speedAlertCountEl) {
            state.ui.speedAlertCountEl.textContent = isCounting ? `${Math.ceil(countdownSeconds)}s` : '';
        }

        if (isCounting) {
            startCountdownTicker(options, countdownSeconds);
        } else if (options?.keepPopup) {
            stopCountdownTicker();
        } else if (alertState !== 'warning') {
            closeCountdownPopup();
        }
    }

    app.ui = {
        cacheSpeedWidget,
        updateSpeedDisplay,
        updateSpeedAlert,
        showReportSubmittingPopup,
        showReportSubmittedPopup,
    };
})(window);
