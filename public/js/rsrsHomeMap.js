// Frontend helper for rsrsHomeMap interactions in the RSRS interface.

(function () {
    const LOCATION_FOCUS_ZOOM = 16;
    const LOCATION_DETAIL_ZOOM = 18;
    const FLY_ANIMATION = {
        animate: true,
        duration: 1.05,
        easeLinearity: 0.25,
    };
    const AUTO_EVALUATION_INTERVAL_MS = 4000;

    let mapInterface = null;
    let watchId = null;
    let hasCentered = false;
    let userHasAdjustedView = false;
    let lastTrackedPoint = null;
    let lastTrackTimestamp = 0;
    let locationButton = null;
    let zoomToUserOnNextFix = false;
    let locationViewMode = 'idle';
    let speedWidget = null;
    let speedValueEl = null;
    let speedStatusEl = null;
    let speedAlertEl = null;
    let speedAlertIconEl = null;
    let speedAlertLabelEl = null;
    let speedAlertMessageEl = null;
    let speedAlertLocationEl = null;
    let speedAlertLimitEl = null;
    let speedAlertCountdownEl = null;
    let hasPublishedLocationReady = false;
    let lastAutoEvaluationAt = 0;
    let autoEvaluationInFlight = false;
    let autoReportInFlight = false;
    let lastAutoTelemetry = null;
    const reportedRuleIds = new Set();

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function cacheSpeedWidget() {
        if (speedWidget) return;

        speedWidget = document.querySelector('[data-home-speed-widget]');
        speedValueEl = document.querySelector('[data-home-speed-value]');
        speedStatusEl = document.querySelector('[data-home-speed-status]');
        speedAlertEl = document.querySelector('[data-home-speed-alert]');
        speedAlertIconEl = document.querySelector('[data-home-speed-alert-icon]');
        speedAlertLabelEl = document.querySelector('[data-home-speed-alert-label]');
        speedAlertMessageEl = document.querySelector('[data-home-speed-alert-message]');
        speedAlertLocationEl = document.querySelector('[data-home-speed-alert-location]');
        speedAlertLimitEl = document.querySelector('[data-home-speed-alert-limit]');
        speedAlertCountdownEl = document.querySelector('[data-home-speed-alert-countdown]');
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function updateSpeedDisplay(speedKmh, statusText, isLive) {
        cacheSpeedWidget();
        if (!speedWidget || !speedValueEl || !speedStatusEl) return;

        const safeSpeed = Number.isFinite(speedKmh) ? Math.max(0, speedKmh) : 0;
        const ringDuration = Math.max(0.45, 3.2 - Math.min(safeSpeed, 120) / 42);
        speedValueEl.textContent = String(Math.round(safeSpeed));
        speedStatusEl.textContent = statusText;
        speedWidget.style.setProperty('--home-speed-ring-duration', `${ringDuration.toFixed(2)}s`);
        speedWidget.classList.toggle('is-live', Boolean(isLive));
        speedWidget.classList.toggle('is-idle', !isLive);
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function updateSpeedAlert(options) {
        cacheSpeedWidget();
        if (!speedAlertEl || !speedAlertIconEl || !speedAlertLabelEl || !speedAlertMessageEl) return;

        const state = options?.state || 'idle';
        const icons = {
            idle: 'bi-info-circle-fill',
            info: 'bi-info-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            danger: 'bi-shield-exclamation',
            success: 'bi-check-circle-fill',
        };

        speedAlertEl.classList.remove(
            'home-speed-alert--idle',
            'home-speed-alert--info',
            'home-speed-alert--warning',
            'home-speed-alert--danger',
            'home-speed-alert--success'
        );
        speedAlertEl.classList.add(`home-speed-alert--${state}`);
        speedAlertIconEl.innerHTML = `<i class="bi ${icons[state] || icons.info}" aria-hidden="true"></i>`;
        speedAlertLabelEl.textContent = options?.label || 'Speed info';
        speedAlertMessageEl.textContent = options?.message || 'Tunatafuta location yako na speed rule iliyo karibu.';

        if (speedAlertLocationEl) {
            speedAlertLocationEl.textContent = `Location: ${options?.location || 'waiting...'}`;
        }

        if (speedAlertLimitEl) {
            speedAlertLimitEl.textContent = `Limit: ${options?.limit || 'unknown'}`;
        }

        if (speedAlertCountdownEl) {
            speedAlertCountdownEl.textContent = `Report: ${options?.countdown || 'inactive'}`;
        }
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function resolveSpeedKmh(position, now, currentPoint) {
        const directSpeed = Number(position.coords.speed);
        if (Number.isFinite(directSpeed) && directSpeed >= 0) {
            return directSpeed * 3.6;
        }

        if (!lastTrackedPoint || !lastTrackTimestamp) {
            return 0;
        }

        const movedMeters = distanceInMeters(lastTrackedPoint, currentPoint);
        const elapsedSeconds = (now - lastTrackTimestamp) / 1000;
        if (elapsedSeconds <= 0) {
            return 0;
        }

        return (movedMeters / elapsedSeconds) * 3.6;
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function publishLocationReady(position, speedKmh) {
        if (hasPublishedLocationReady) return;

        const accuracy = Number(position.coords.accuracy);
        const hasUsableAccuracy = Number.isFinite(accuracy) && accuracy > 0 && accuracy <= 120;
        const hasUsableSpeed = Number.isFinite(speedKmh) && speedKmh >= 0;

        if (!hasUsableAccuracy || !hasUsableSpeed) {
            return;
        }

        hasPublishedLocationReady = true;
        document.dispatchEvent(new CustomEvent('rsrs:home-location-ready', {
            detail: {
                accuracy,
                speedKmh,
            },
        }));
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function getAutoReportingConfig() {
        const config = window.rsrsAutoSpeedReporting || {};

        if (!config.evaluateUrl || !config.storeUrl || !config.csrfToken) {
            return null;
        }

        return config;
    }

    async function postAutoJson(url, payload, csrfToken) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const error = new Error(data.message || 'Automatic reporting request failed.');
            error.response = data;
            error.status = response.status;
            throw error;
        }

        return data;
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function buildAutoTelemetry(position, speedKmh) {
        const latitude = Number(position.coords.latitude);
        const longitude = Number(position.coords.longitude);
        const accuracy = Number(position.coords.accuracy);
        const heading = Number(position.coords.heading);

        return {
            latitude,
            longitude,
            speed_kmh: Number.isFinite(speedKmh) ? Math.max(0, speedKmh) : 0,
            accuracy: Number.isFinite(accuracy) ? accuracy : null,
            heading: Number.isFinite(heading) && heading >= 0 ? heading : null,
        };
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function resetReportedRuleIfSafe(evaluation) {
        if (!evaluation?.matched || !evaluation?.rule?.id || evaluation.exceeded) return;
        reportedRuleIds.delete(Number(evaluation.rule.id));
    }

    async function submitAutoReport(evaluation, telemetry) {
        const config = getAutoReportingConfig();
        const ruleId = Number(evaluation?.rule?.id);
        const segmentId = Number(evaluation?.segment?.id);

        if (!config || !ruleId || !segmentId || autoReportInFlight || reportedRuleIds.has(ruleId)) {
            return;
        }

        autoReportInFlight = true;

        try {
            const result = await postAutoJson(config.storeUrl, {
                ...telemetry,
                rule_id: ruleId,
                segment_id: segmentId,
            }, config.csrfToken);

            reportedRuleIds.add(ruleId);
            const reference = result.reference_no ? `: ${result.reference_no}` : '';
            updateSpeedDisplay(telemetry.speed_kmh, result.duplicate ? 'Automatic report already submitted' : `Automatic report submitted${reference}`, true);
            updateSpeedAlert({
                state: 'danger',
                label: result.duplicate ? 'Report tayari imetumwa' : 'Speed violation imeripotiwa',
                message: result.duplicate
                    ? 'Speed yako bado ipo juu ya limit na report ya eneo hili tayari ipo kwenye mfumo.'
                    : `Auto report imetumwa kwa sababu speed haijashuka ndani ya sekunde 30${reference}.`,
                location: evaluation?.segment?.name || 'matched road segment',
                limit: `${Math.round(Number(evaluation.speed_limit_kmh))} km/h`,
                countdown: 'submitted',
            });
        } catch (error) {
            const response = error.response || {};

            if (response.reason === 'duration_pending' && Number.isFinite(Number(response.exceeded_seconds))) {
                updateSpeedDisplay(telemetry.speed_kmh, `Speed limit exceeded for ${Math.round(Number(response.exceeded_seconds))}s`, true);
            } else if (response.reason === 'speed_within_limit') {
                updateSpeedDisplay(telemetry.speed_kmh, 'Speed is back within the saved limit', telemetry.speed_kmh >= 1);
            } else if (error.status !== 422) {
                updateSpeedDisplay(telemetry.speed_kmh, 'Automatic reporting unavailable right now', telemetry.speed_kmh >= 1);
            }
        } finally {
            autoReportInFlight = false;
        }
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function handleAutoEvaluation(evaluation, telemetry) {
        resetReportedRuleIfSafe(evaluation);

        if (!evaluation?.matched) {
            if (telemetry.speed_kmh >= 1) {
                updateSpeedDisplay(telemetry.speed_kmh, 'No monitored speed rule nearby', true);
            }
            updateSpeedAlert({
                state: 'idle',
                label: 'Hakuna speed rule karibu',
                message: 'Location yako haijalingana na road segment yenye speed limit kwenye database.',
                location: 'not matched',
                limit: 'unknown',
                countdown: 'inactive',
            });
            return;
        }

        const limit = Number(evaluation.speed_limit_kmh);
        const limitText = Number.isFinite(limit) ? `${Math.round(limit)} km/h` : 'saved limit';
        const segmentName = evaluation.segment?.name || 'matched road segment';

        if (!evaluation.exceeded) {
            updateSpeedDisplay(telemetry.speed_kmh, `Speed limit ${limitText} active`, telemetry.speed_kmh >= 1);
            updateSpeedAlert({
                state: 'info',
                label: 'Speed iko sawa',
                message: `Speed yako haivunji sheria za eneo hili. Inatakiwa usizidi ${limitText}.`,
                location: segmentName,
                limit: limitText,
                countdown: 'inactive',
            });
            return;
        }

        const exceededSeconds = Math.max(0, Number(evaluation.exceeded_seconds) || 0);
        const requiredSeconds = Math.max(30, Number(evaluation.required_seconds) || 30);
        const remainingSeconds = Math.max(0, requiredSeconds - exceededSeconds);

        if (remainingSeconds > 0) {
            updateSpeedDisplay(telemetry.speed_kmh, `Limit ${limitText} exceeded for ${Math.round(exceededSeconds)}s`, true);
            updateSpeedAlert({
                state: 'warning',
                label: 'Warning: umepitiliza speed',
                message: `Punguza speed mpaka ${limitText}. Ukibaki juu ya limit, mfumo utatuma auto report.`,
                location: segmentName,
                limit: limitText,
                countdown: `${Math.ceil(remainingSeconds)}s remaining`,
            });
            return;
        }

        updateSpeedDisplay(telemetry.speed_kmh, 'Submitting automatic speed report...', true);
        updateSpeedAlert({
            state: 'danger',
            label: 'Danger: auto report inaanza',
            message: `Speed yako imekaa juu ya ${limitText} kwa sekunde 30. Mfumo unatuma speed violation report.`,
            location: segmentName,
            limit: limitText,
            countdown: 'submitting',
        });
        submitAutoReport(evaluation, telemetry);
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function evaluateAutoReporting(position, speedKmh, now) {
        const config = getAutoReportingConfig();

        if (!config || autoEvaluationInFlight || now - lastAutoEvaluationAt < AUTO_EVALUATION_INTERVAL_MS) {
            return;
        }

        const telemetry = buildAutoTelemetry(position, speedKmh);
        if (!Number.isFinite(telemetry.latitude) || !Number.isFinite(telemetry.longitude)) {
            return;
        }

        lastAutoTelemetry = telemetry;
        lastAutoEvaluationAt = now;
        autoEvaluationInFlight = true;

        postAutoJson(config.evaluateUrl, telemetry, config.csrfToken)
            .then((evaluation) => handleAutoEvaluation(evaluation, lastAutoTelemetry || telemetry))
            .catch((error) => {
                if (error.status !== 422 && speedKmh >= 1) {
                    updateSpeedDisplay(speedKmh, 'Automatic reporting check failed', true);
                }
            })
            .finally(() => {
                autoEvaluationInFlight = false;
            });
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function setLocationButtonMode(mode) {
        if (!locationButton) return;

        const isDetail = mode === 'detail';
        locationButton.classList.toggle('is-detail-view', isDetail);
        const buttonTitle = isDetail ? 'Switch to wider location view' : 'Use my current location';
        locationButton.title = buttonTitle;
        locationButton.setAttribute('aria-label', buttonTitle);
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function getNextLocationViewMode() {
        if (locationViewMode === 'idle') return 'focus';
        if (locationViewMode === 'focus') return 'detail';
        return 'focus';
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function getTargetZoom(mode) {
        if (!mapInterface?.map) return LOCATION_FOCUS_ZOOM;
        const maxZoom = Number(mapInterface.map.getMaxZoom()) || LOCATION_DETAIL_ZOOM;
        return Math.min(mode === 'detail' ? LOCATION_DETAIL_ZOOM : LOCATION_FOCUS_ZOOM, maxZoom);
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function flyToUser(lat, lng, mode) {
        if (!mapInterface?.map) return;
        mapInterface.map.flyTo([lat, lng], getTargetZoom(mode), FLY_ANIMATION);
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function setLocatingState(isLocating) {
        if (!locationButton) return;

        locationButton.disabled = isLocating;
        locationButton.classList.toggle('is-locating', isLocating);
        if (isLocating) {
            locationButton.title = 'Finding your current position...';
            return;
        }

        setLocationButtonMode(locationViewMode);
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function createLocationControl() {
        if (!mapInterface?.map || locationButton) return;

        const LocationControl = L.Control.extend({
            options: { position: 'bottomright' },
            onAdd: function () {
                const container = L.DomUtil.create('div', 'leaflet-bar home-location-control');
                const button = L.DomUtil.create('button', 'home-location-control__button', container);
                button.type = 'button';
                button.title = 'Use my current location';
                button.setAttribute('aria-label', 'Use my current location');
                button.innerHTML = '<i class="bi bi-geo-alt-fill" aria-hidden="true"></i>';
                locationButton = button;

                L.DomEvent.disableClickPropagation(container);
                L.DomEvent.disableScrollPropagation(container);

                L.DomEvent.on(button, 'click', function (event) {
                    L.DomEvent.preventDefault(event);
                    L.DomEvent.stopPropagation(event);
                    zoomToUserOnNextFix = true;
                    locationViewMode = getNextLocationViewMode();
                    setLocationButtonMode(locationViewMode);

                    if (lastTrackedPoint && mapInterface?.map) {
                        flyToUser(lastTrackedPoint.lat, lastTrackedPoint.lng, locationViewMode);
                    }

                    bootstrapGps(true);
                });

                return container;
            },
        });

        const control = new LocationControl();
        control.addTo(mapInterface.map);

        const container = control.getContainer();
        const corner = container?.parentElement;
        const zoomControl = corner?.querySelector('.leaflet-control-zoom');
        if (corner && zoomControl) {
            corner.insertBefore(container, zoomControl);
        }

        setLocationButtonMode(locationViewMode);
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function distanceInMeters(a, b) {
        const toRad = (value) => (value * Math.PI) / 180;
        const earthRadius = 6371000;
        const dLat = toRad(b.lat - a.lat);
        const dLng = toRad(b.lng - a.lng);
        const lat1 = toRad(a.lat);
        const lat2 = toRad(b.lat);
        const sinLat = Math.sin(dLat / 2);
        const sinLng = Math.sin(dLng / 2);
        const h = sinLat * sinLat + Math.cos(lat1) * Math.cos(lat2) * sinLng * sinLng;

        return 2 * earthRadius * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function bearingDegrees(a, b) {
        const toRad = (value) => (value * Math.PI) / 180;
        const toDeg = (value) => (value * 180) / Math.PI;
        const lat1 = toRad(a.lat);
        const lat2 = toRad(b.lat);
        const dLng = toRad(b.lng - a.lng);
        const y = Math.sin(dLng) * Math.cos(lat2);
        const x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLng);

        return (toDeg(Math.atan2(y, x)) + 360) % 360;
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function applyPosition(position) {
        if (!mapInterface) return;

        const latitude = Number(position.coords.latitude);
        const longitude = Number(position.coords.longitude);
        const accuracy = Number(position.coords.accuracy);
        const now = Date.now();
        const currentPoint = { lat: latitude, lng: longitude };
        const speedKmh = resolveSpeedKmh(position, now, currentPoint);
        const isMoving = speedKmh >= 1;
        const movedMeters = lastTrackedPoint ? distanceInMeters(lastTrackedPoint, currentPoint) : 0;
        const gpsHeading = Number(position.coords.heading);
        const heading = Number.isFinite(gpsHeading) && gpsHeading >= 0
            ? gpsHeading
            : lastTrackedPoint && movedMeters >= 3
                ? bearingDegrees(lastTrackedPoint, currentPoint)
                : null;

        if (lastTrackedPoint) {
            if (movedMeters < 5 && now - lastTrackTimestamp < 1200) {
                updateSpeedDisplay(speedKmh, isMoving ? 'Live movement detected' : 'Waiting for movement...', isMoving);
                return;
            }
        }

        lastTrackedPoint = currentPoint;
        lastTrackTimestamp = now;

        mapInterface.selectPoint(latitude, longitude, { resolveLocation: false });
        mapInterface.setUserLocation?.(latitude, longitude, { accuracy, heading });
        setLocatingState(false);
        updateSpeedDisplay(speedKmh, isMoving ? 'Live movement detected' : 'You look stationary', isMoving);
        publishLocationReady(position, speedKmh);
        evaluateAutoReporting(position, speedKmh, now);

        if (zoomToUserOnNextFix) {
            flyToUser(latitude, longitude, locationViewMode);
            zoomToUserOnNextFix = false;
            hasCentered = true;
        } else if (!hasCentered) {
            mapInterface.map.panTo([latitude, longitude], { animate: true, duration: 0.65 });
            hasCentered = true;
        } else if (!userHasAdjustedView) {
            mapInterface.map.panTo([latitude, longitude], { animate: true, duration: 0.55 });
        }
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function startWatch(highAccuracy) {
        if (!navigator.geolocation || !mapInterface) return;

        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
        }

        watchId = navigator.geolocation.watchPosition(
            applyPosition,
            (error) => {
                if (highAccuracy && (error.code === 2 || error.code === 3)) {
                    startWatch(false);
                    return;
                }

                setLocatingState(false);
            },
            {
                enableHighAccuracy: highAccuracy,
                timeout: highAccuracy ? 9000 : 12000,
                maximumAge: highAccuracy ? 0 : 5000,
            }
        );
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function bootstrapGps(force) {
        if (!navigator.geolocation || !mapInterface) return;
        if (!force && watchId !== null) return;

        setLocatingState(true);
        updateSpeedDisplay(0, 'Checking GPS speed...', false);
        updateSpeedAlert({
            state: 'idle',
            label: 'Inasoma location',
            message: 'Ruhusu GPS ili mfumo ulinganishe coordinates zako na speed limit ya database.',
            location: 'checking GPS...',
            limit: 'unknown',
            countdown: 'inactive',
        });

        navigator.geolocation.getCurrentPosition(
            (position) => {
                applyPosition(position);
                startWatch(true);
            },
            () => {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        applyPosition(position);
                        startWatch(false);
                    },
                    () => {
                        setLocatingState(false);
                        updateSpeedDisplay(0, 'Speed unavailable right now', false);
                        updateSpeedAlert({
                            state: 'warning',
                            label: 'Location haipatikani',
                            message: 'GPS haijapatikana, hivyo speed rule na auto reporting haziwezi kufanya kazi sasa.',
                            location: 'unavailable',
                            limit: 'unknown',
                            countdown: 'inactive',
                        });
                        startWatch(false);
                    },
                    { enableHighAccuracy: false, timeout: 8000, maximumAge: 15000 }
                );
            },
            { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
        );
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function initWhenMapReady() {
        const mapEl = document.getElementById('mainPublicMap');
        if (!mapEl) return;
        cacheSpeedWidget();
        updateSpeedDisplay(0, 'Waiting for movement...', false);
        updateSpeedAlert({
            state: 'idle',
            label: 'Speed info',
            message: 'Tunatafuta location yako na speed rule iliyo karibu.',
            location: 'waiting...',
            limit: 'unknown',
            countdown: 'inactive',
        });

        const wireUp = () => {
            if (!mapEl.mapApi) return;

            mapInterface = mapEl.mapApi;
            mapInterface.ensureSize();
            createLocationControl();

            const markUserAdjusted = () => {
                if (hasCentered) {
                    userHasAdjustedView = true;
                }
            };

            const clearFocusedMode = () => {
                if (userHasAdjustedView) {
                    locationViewMode = 'idle';
                    setLocationButtonMode(locationViewMode);
                }
            };

            mapInterface.map.on('zoomstart', markUserAdjusted);
            mapInterface.map.on('dragstart', markUserAdjusted);
            mapInterface.map.on('zoomend', clearFocusedMode);
            mapInterface.map.on('dragend', clearFocusedMode);

            bootstrapGps(false);
        };

        if (mapEl.mapApi) {
            wireUp();
            return;
        }

        mapEl.addEventListener('rsrs:map-ready', wireUp, { once: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWhenMapReady);
    } else {
        initWhenMapReady();
    }
})();
