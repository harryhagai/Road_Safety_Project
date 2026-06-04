// Leaflet controls and GPS lifecycle for the RSRS home map.
(function (window) {
    const app = window.RSRSHomeMap;
    const state = app.state;
    const constants = app.constants;

    function setLocationButtonMode(mode) {
        if (!state.locationButton) return;

        const isDetail = mode === 'detail';
        state.locationButton.classList.toggle('is-detail-view', isDetail);
        const buttonTitle = isDetail ? 'Switch to wider location view' : 'Use my current location';

        state.locationButton.title = buttonTitle;
        state.locationButton.setAttribute('aria-label', buttonTitle);
    }

    function getNextLocationViewMode() {
        if (state.locationViewMode === 'idle') return 'focus';
        if (state.locationViewMode === 'focus') return 'detail';

        return 'focus';
    }

    function getTargetZoom(mode) {
        if (!state.mapInterface?.map) return constants.LOCATION_FOCUS_ZOOM;

        const maxZoom = Number(state.mapInterface.map.getMaxZoom()) || constants.LOCATION_DETAIL_ZOOM;

        return Math.min(
            mode === 'detail' ? constants.LOCATION_DETAIL_ZOOM : constants.LOCATION_FOCUS_ZOOM,
            maxZoom
        );
    }

    function flyToUser(lat, lng, mode) {
        if (!state.mapInterface?.map) return;

        state.mapInterface.map.flyTo([lat, lng], getTargetZoom(mode), constants.FLY_ANIMATION);
    }

    function setAutoRotateEnabled(enabled) {
        state.autoRotateEnabled = Boolean(enabled);

        if (state.rotateControlEl) {
            state.rotateControlEl.classList.toggle('is-auto-rotate-paused', !state.autoRotateEnabled);
            state.rotateControlEl.classList.toggle('is-auto-rotate-active', state.autoRotateEnabled);
        }

        if (state.rotateToggleEl) {
            const title = state.autoRotateEnabled
                ? 'Course-up GPS rotation active. Click to reset map north.'
                : 'Enable course-up GPS rotation';
            state.rotateToggleEl.title = title;
            state.rotateToggleEl.setAttribute('aria-label', title);
            state.rotateToggleEl.setAttribute('aria-pressed', state.autoRotateEnabled ? 'true' : 'false');
        }

        const map = state.mapInterface?.map;
        if (!map) return;

        if (!state.autoRotateEnabled && map.compassBearing && typeof map.compassBearing.disable === 'function') {
            map.compassBearing.disable();
        }
    }

    function resetMapNorth() {
        const map = state.mapInterface?.map;

        stopDeviceCompassTracking();
        state.autoRotateEnabled = false;
        state.lastAutoRotateHeading = null;

        if (map?.compassBearing && typeof map.compassBearing.disable === 'function') {
            map.compassBearing.disable();
        }

        state.mapInterface?.setBearing?.(0);
        setAutoRotateEnabled(false);
        syncCompassNeedle(0);
    }

    function syncCompassNeedle(value = null) {
        const compassNeedle = state.rotateToggleEl?.querySelector('.home-compass-icon__needle');
        if (!compassNeedle) return;

        const fallbackBearing = Number(state.mapInterface?.map?.getBearing?.() || 0);
        const bearing = Number.isFinite(Number(value)) ? Number(value) : fallbackBearing;
        const safeBearing = Number.isFinite(bearing) ? ((bearing % 360) + 360) % 360 : 0;

        compassNeedle.style.transform = `translate(-50%, -50%) rotate(${safeBearing}deg)`;
        state.rotateToggleEl.title = state.autoRotateEnabled
            ? `Course-up GPS rotation active: ${Math.round(safeBearing)} degrees. Click to reset map north.`
            : `Compass: ${Math.round(safeBearing)} degrees. Click to enable course-up rotation.`;
        state.rotateToggleEl.setAttribute('aria-label', state.rotateToggleEl.title);
    }

    function normalizeBearing(value) {
        const bearing = Number(value);

        return Number.isFinite(bearing) ? ((bearing % 360) + 360) % 360 : null;
    }

    function deviceHeadingFromEvent(event) {
        const iosHeading = Number(event.webkitCompassHeading);
        if (Number.isFinite(iosHeading)) {
            return normalizeBearing(iosHeading);
        }

        const alpha = Number(event.alpha);
        if (!Number.isFinite(alpha)) {
            return null;
        }

        const screenAngle = Number(window.screen?.orientation?.angle || window.orientation || 0);

        return normalizeBearing(360 - alpha + screenAngle);
    }

    function applyDeviceHeading(rawHeading) {
        const heading = normalizeBearing(rawHeading);
        if (heading === null) return;

        const now = Date.now();
        if (now - state.lastDeviceCompassUpdateAt < constants.DEVICE_COMPASS_MIN_UPDATE_MS) {
            return;
        }

        const previous = Number.isFinite(state.lastDeviceCompassHeading)
            ? state.lastDeviceCompassHeading
            : null;

        if (previous !== null) {
            const delta = Math.abs(app.geo.angleDeltaDegrees(previous, heading));

            if (delta < constants.DEVICE_COMPASS_HEADING_DEADZONE_DEGREES) {
                return;
            }
        }

        const nextHeading = previous === null
            ? heading
            : app.geo.smoothHeadingDegrees(previous, heading, constants.DEVICE_COMPASS_SMOOTHING);

        state.lastDeviceCompassHeading = nextHeading;
        state.lastDeviceCompassUpdateAt = now;
        state.mapInterface?.setBearing?.(nextHeading);
        syncCompassNeedle(nextHeading);
    }

    function bindDeviceCompassEvents() {
        if (state.deviceCompassHandler) return;

        state.deviceCompassHandler = (event) => {
            const heading = deviceHeadingFromEvent(event);

            if (heading === null) return;

            state.deviceCompassActive = true;
            setAutoRotateEnabled(true);
            applyDeviceHeading(heading);
        };

        window.addEventListener('deviceorientationabsolute', state.deviceCompassHandler, true);
        window.addEventListener('deviceorientation', state.deviceCompassHandler, true);
    }

    function stopDeviceCompassTracking() {
        if (state.deviceCompassHandler) {
            window.removeEventListener('deviceorientationabsolute', state.deviceCompassHandler, true);
            window.removeEventListener('deviceorientation', state.deviceCompassHandler, true);
        }

        state.deviceCompassHandler = null;
        state.deviceCompassActive = false;
        state.lastDeviceCompassHeading = null;
        state.lastDeviceCompassUpdateAt = 0;
    }

    async function startDeviceCompassTracking(requestPermission = false) {
        if (!state.mapInterface?.map) return;

        if (typeof DeviceOrientationEvent === 'undefined') {
            setAutoRotateEnabled(false);
            return;
        }

        if (
            !requestPermission &&
            typeof DeviceOrientationEvent.requestPermission === 'function' &&
            !state.deviceCompassPermissionRequested
        ) {
            setAutoRotateEnabled(false);
            return;
        }

        if (
            requestPermission &&
            typeof DeviceOrientationEvent.requestPermission === 'function' &&
            !state.deviceCompassPermissionRequested
        ) {
            try {
                const permission = await DeviceOrientationEvent.requestPermission();

                if (permission !== 'granted') {
                    state.deviceCompassPermissionRequested = false;
                    setAutoRotateEnabled(false);
                    return;
                }

                state.deviceCompassPermissionRequested = true;
            } catch (error) {
                state.deviceCompassPermissionRequested = false;
                setAutoRotateEnabled(false);
                return;
            }
        }

        state.lastDeviceCompassHeading = null;
        state.lastDeviceCompassUpdateAt = 0;
        setAutoRotateEnabled(true);
        bindDeviceCompassEvents();
    }

    function rotateMapToHeading(heading, speedKmh, movedMeters, accuracyMeters, motionState) {
        if (!state.mapInterface?.setBearing || !Number.isFinite(heading)) {
            return;
        }

        if (!state.autoRotateEnabled) {
            return;
        }

        const accuracy = Number.isFinite(accuracyMeters) ? Math.max(0, accuracyMeters) : Infinity;
        const isMovingEnough =
            speedKmh >= constants.DISPLAY_SPEED_THRESHOLD_KMH &&
            movedMeters >= app.geo.displayMovementThresholdMeters(accuracyMeters) &&
            accuracy <= constants.LOW_CONFIDENCE_ACCURACY_METERS;

        if (!isMovingEnough) {
            return;
        }

        const previousHeading = Number.isFinite(state.lastAutoRotateHeading)
            ? state.lastAutoRotateHeading
            : null;

        if (previousHeading !== null) {
            const delta = Math.abs(app.geo.angleDeltaDegrees(previousHeading, heading));

            if (delta < constants.AUTO_ROTATE_HEADING_DEADZONE_DEGREES) {
                return;
            }
        }

        const nextHeading = previousHeading === null
            ? heading
            : app.geo.smoothHeadingDegrees(previousHeading, heading, constants.AUTO_ROTATE_SMOOTHING);

        state.lastAutoRotateHeading = nextHeading;
        state.mapInterface.setBearing(nextHeading);
    }

    function setLocatingState(isLocating) {
        if (!state.locationButton) return;

        state.locationButton.disabled = isLocating;
        state.locationButton.classList.toggle('is-locating', isLocating);

        if (isLocating) {
            state.locationButton.title = 'Finding your current position...';
            return;
        }

        setLocationButtonMode(state.locationViewMode);
    }

    function createLocationControl() {
        if (!state.mapInterface?.map || state.locationButton) return;

        const LocationControl = L.Control.extend({
            options: { position: 'bottomright' },
            onAdd: function () {
                const container = L.DomUtil.create('div', 'leaflet-bar home-location-control');
                const button = L.DomUtil.create('button', 'home-location-control__button', container);

                button.type = 'button';
                button.title = 'Use my current location';
                button.setAttribute('aria-label', 'Use my current location');
                button.innerHTML = '<i class="bi bi-geo-alt-fill" aria-hidden="true"></i>';
                state.locationButton = button;

                L.DomEvent.disableClickPropagation(container);
                L.DomEvent.disableScrollPropagation(container);

                L.DomEvent.on(button, 'click', function (event) {
                    L.DomEvent.preventDefault(event);
                    L.DomEvent.stopPropagation(event);

                    state.zoomToUserOnNextFix = true;
                    state.locationViewMode = getNextLocationViewMode();
                    setLocationButtonMode(state.locationViewMode);

                    if (state.lastTrackedPoint && state.mapInterface?.map) {
                        flyToUser(state.lastTrackedPoint.lat, state.lastTrackedPoint.lng, state.locationViewMode);
                    }

                    bootstrapGps(true);
                });

                return container;
            },
        });

        const control = new LocationControl();
        control.addTo(state.mapInterface.map);

        setLocationButtonMode(state.locationViewMode);
    }

    function customizeRotateControl() {
        const mapRoot = state.mapInterface?.map?.getContainer?.();
        let rotateControl = mapRoot?.querySelector('.leaflet-control-rotate');
        let rotateToggle = rotateControl?.querySelector('.leaflet-control-rotate-toggle');

        if ((!rotateControl || !rotateToggle) && state.mapInterface?.map && typeof L !== 'undefined') {
            const CompassControl = L.Control.extend({
                options: { position: 'bottomright' },
                onAdd() {
                    const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control-rotate home-compass-control');
                    const button = L.DomUtil.create('button', 'leaflet-control-rotate-toggle', container);

                    button.type = 'button';
                    button.title = 'Compass: 0 degrees. Click to reset map north.';
                    button.setAttribute('aria-label', button.title);

                    L.DomEvent.disableClickPropagation(container);
                    L.DomEvent.disableScrollPropagation(container);

                    return container;
                },
            });

            state.mapInterface.map.addControl(new CompassControl());
            rotateControl = mapRoot?.querySelector('.home-compass-control');
            rotateToggle = rotateControl?.querySelector('.leaflet-control-rotate-toggle');
        }

        if (!rotateControl || !rotateToggle || rotateControl.dataset.homeRotateReady === 'true') {
            return;
        }

        state.rotateControlEl = rotateControl;
        state.rotateToggleEl = rotateToggle;

        const locationControl = state.locationButton?.closest('.home-location-control');
        if (locationControl?.parentElement === rotateControl.parentElement) {
            rotateControl.parentElement.insertBefore(rotateControl, locationControl);
        }

        rotateControl.dataset.homeRotateReady = 'true';
        rotateToggle.innerHTML = `
            <span class="home-compass-icon" aria-hidden="true">
                <span class="home-compass-icon__needle"></span>
                <span class="home-compass-icon__center"></span>
            </span>
        `;

        const stopDefaultRotateControl = (event) => {
            event.preventDefault();
            event.stopImmediatePropagation();
        };

        rotateToggle.addEventListener('mousedown', stopDefaultRotateControl, true);
        rotateToggle.addEventListener('dblclick', stopDefaultRotateControl, true);
        rotateToggle.addEventListener('click', (event) => {
            stopDefaultRotateControl(event);

            if (state.autoRotateEnabled) {
                resetMapNorth();
                return;
            }

            stopDeviceCompassTracking();
            state.lastAutoRotateHeading = null;
            setAutoRotateEnabled(true);
        }, true);

        const syncCompassBearing = () => {
            syncCompassNeedle();
        };

        state.mapInterface.map.on('rotate', syncCompassBearing);
        syncCompassBearing();
        setAutoRotateEnabled(true);
    }

    function applyPosition(position) {
        if (!state.mapInterface) return;

        const latitude = Number(position.coords.latitude);
        const longitude = Number(position.coords.longitude);
        const accuracy = Number(position.coords.accuracy);
        const now = Date.now();
        const currentPoint = { lat: latitude, lng: longitude };
        const motion = app.geo.resolveMotion(position, now, currentPoint);
        const speedKmh = motion.speedKmh;
        const movedMeters = state.lastTrackedPoint ? app.geo.distanceInMeters(state.lastTrackedPoint, currentPoint) : 0;
        const gpsHeading = Number(position.coords.heading);
        const reportSpeedKmh = app.geo.normalizeSpeedKmh(
            speedKmh,
            movedMeters,
            accuracy,
            motion.source,
            motion.elapsedSeconds
        );
        const displaySpeedKmh = app.geo.normalizeDisplaySpeedKmh(
            speedKmh,
            movedMeters,
            accuracy,
            motion.source,
            motion.elapsedSeconds
        );
        const displayMoving = displaySpeedKmh >= constants.DISPLAY_SPEED_THRESHOLD_KMH;
        const motionState = app.geo.updateMotionConfidence(reportSpeedKmh, movedMeters, accuracy, now, motion.source);
        const heading = displayMoving && Number.isFinite(gpsHeading) && gpsHeading >= 0
            ? gpsHeading
            : state.lastTrackedPoint && displayMoving && app.geo.isDisplayMovement(movedMeters, accuracy)
                ? app.geo.bearingDegrees(state.lastTrackedPoint, currentPoint)
                : null;

        if (state.lastTrackedPoint && movedMeters < 1.2 && now - state.lastTrackTimestamp < 900) {
            if (Number.isFinite(heading)) {
                syncCompassNeedle(heading);
            }

            app.ui.updateSpeedDisplay(displayMoving ? displaySpeedKmh : 0, displayMoving ? 'Movement detected' : 'Waiting for movement...', displayMoving);
            return;
        }

        state.lastTrackedPoint = currentPoint;
        state.lastTrackTimestamp = now;

        state.mapInterface.selectPoint(latitude, longitude, { resolveLocation: false });
        rotateMapToHeading(heading, displaySpeedKmh, movedMeters, accuracy, motionState);
        state.mapInterface.setUserLocation?.(latitude, longitude, { accuracy, heading });
        if (Number.isFinite(heading)) {
            syncCompassNeedle(heading);
        }
        setLocatingState(false);

        const isMoving = motionState.confirmedMotion && reportSpeedKmh >= constants.AUTO_REPORT_MIN_CONFIRMED_SPEED_KMH;
        const isMovingCandidate = motionState.isMovingCandidate && reportSpeedKmh >= constants.AUTO_REPORT_MIN_CONFIRMED_SPEED_KMH;
        const movementStatus = isMoving
            ? 'Live movement detected'
            : isMovingCandidate
                ? 'Confirming movement...'
                : displayMoving
                    ? 'Movement detected'
                    : 'You look stationary';

        app.ui.updateSpeedDisplay(displayMoving ? displaySpeedKmh : 0, movementStatus, displayMoving);

        app.geo.publishLocationReady(position, displaySpeedKmh);
        app.reporting.evaluateAutoReporting(position, displaySpeedKmh, now, {
            confirmedMotion: motionState.confirmedMotion,
            observedMovement: displayMoving,
            movedMeters,
            accuracy,
            movementThresholdMeters: motionState.movementThresholdMeters,
        });

        if (state.zoomToUserOnNextFix) {
            flyToUser(latitude, longitude, state.locationViewMode);
            state.zoomToUserOnNextFix = false;
            state.hasCentered = true;
        } else if (!state.hasCentered) {
            state.mapInterface.map.panTo([latitude, longitude], { animate: true, duration: 0.65 });
            state.hasCentered = true;
        } else if (!state.userHasAdjustedView) {
            state.mapInterface.map.panTo([latitude, longitude], { animate: true, duration: 0.55 });
        }
    }

    function startWatch(highAccuracy) {
        if (!navigator.geolocation || !state.mapInterface) return;

        if (state.watchId !== null) {
            navigator.geolocation.clearWatch(state.watchId);
        }

        state.watchId = navigator.geolocation.watchPosition(
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

    function bootstrapGps(force) {
        if (!navigator.geolocation || !state.mapInterface) return;
        if (!force && state.watchId !== null) return;

        setLocatingState(true);
        app.ui.updateSpeedDisplay(0, 'Checking GPS speed...', false);
        app.ui.updateSpeedAlert({
            state: 'idle',
            label: 'Reading location',
            message: 'Allow GPS so the system can match your coordinates to speed limits in the database.',
            location: 'checking GPS...',
            limit: 'unknown',
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
                        app.ui.updateSpeedDisplay(0, 'Speed unavailable right now', false);
                        app.ui.updateSpeedAlert({
                            state: 'warning',
                            label: 'Location unavailable',
                            message: 'GPS could not be detected, so speed rules and automatic reporting cannot work right now.',
                            location: 'unavailable',
                            limit: 'unknown',
                        });
                        startWatch(false);
                    },
                    { enableHighAccuracy: false, timeout: 8000, maximumAge: 15000 }
                );
            },
            { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
        );
    }

    function requestGpsDetectionEarly() {
        if (!navigator.geolocation) return;

        navigator.geolocation.getCurrentPosition(
            (position) => {
                state.pendingInitialPosition = position;

                if (state.mapInterface) {
                    applyPosition(position);
                    startWatch(true);
                }
            },
            () => {
                // Normal bootstrap flow will show UI status once map is ready.
            },
            { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
        );
    }

    app.controls = {
        applyPosition,
        bootstrapGps,
        createLocationControl,
        customizeRotateControl,
        requestGpsDetectionEarly,
        setLocationButtonMode,
        startWatch,
    };
})(window);
