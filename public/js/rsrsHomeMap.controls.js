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
        }

        if (state.rotateToggleEl) {
            const title = state.autoRotateEnabled ? 'Pause auto rotate' : 'Resume auto rotate';
            state.rotateToggleEl.title = title;
            state.rotateToggleEl.setAttribute('aria-label', title);
            state.rotateToggleEl.setAttribute('aria-pressed', String(state.autoRotateEnabled));
        }

        const map = state.mapInterface?.map;
        if (!map) return;

        if (state.autoRotateEnabled) {
            if (map.compassBearing && typeof map.compassBearing.enable === 'function') {
                map.compassBearing.enable();
            }

            if (Number.isFinite(state.lastAutoRotateHeading)) {
                state.mapInterface.setBearing?.(state.lastAutoRotateHeading);
            }
            return;
        }

        if (map.compassBearing && typeof map.compassBearing.disable === 'function') {
            map.compassBearing.disable();
        }
    }

    function rotateMapToHeading(heading, speedKmh, movedMeters) {
        if (!state.mapInterface?.setBearing || !Number.isFinite(heading)) {
            return;
        }

        state.lastAutoRotateHeading = heading;

        if (!state.autoRotateEnabled) {
            return;
        }

        const isMovingEnough = speedKmh >= constants.AUTO_ROTATE_MIN_SPEED_KMH || movedMeters >= constants.AUTO_ROTATE_MIN_MOVEMENT_METERS;
        if (!isMovingEnough) {
            return;
        }

        state.mapInterface.setBearing(heading);
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
        const rotateControl = mapRoot?.querySelector('.leaflet-control-rotate');
        const rotateToggle = rotateControl?.querySelector('.leaflet-control-rotate-toggle');

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
            setAutoRotateEnabled(!state.autoRotateEnabled);
        }, true);

        const compassNeedle = rotateToggle.querySelector('.home-compass-icon__needle');
        const syncCompassBearing = () => {
            if (!compassNeedle) return;

            const bearing = Number(state.mapInterface?.map?.getBearing?.() || 0);
            compassNeedle.style.transform = `translate(-50%, -50%) rotate(${Number.isFinite(bearing) ? bearing : 0}deg)`;
        };

        state.mapInterface.map.on('rotate', syncCompassBearing);
        syncCompassBearing();
        setAutoRotateEnabled(state.autoRotateEnabled);
    }

    function applyPosition(position) {
        if (!state.mapInterface) return;

        const latitude = Number(position.coords.latitude);
        const longitude = Number(position.coords.longitude);
        const accuracy = Number(position.coords.accuracy);
        const now = Date.now();
        const currentPoint = { lat: latitude, lng: longitude };
        const speedKmh = app.geo.resolveSpeedKmh(position, now, currentPoint);
        const movedMeters = state.lastTrackedPoint ? app.geo.distanceInMeters(state.lastTrackedPoint, currentPoint) : 0;
        const gpsHeading = Number(position.coords.heading);
        const normalizedSpeedKmh = app.geo.normalizeSpeedKmh(speedKmh, movedMeters, accuracy);
        const heading = Number.isFinite(gpsHeading) && gpsHeading >= 0
            ? gpsHeading
            : state.lastTrackedPoint && movedMeters >= 3
                ? app.geo.bearingDegrees(state.lastTrackedPoint, currentPoint)
                : null;

        if (state.lastTrackedPoint && movedMeters < 1.2 && now - state.lastTrackTimestamp < 900) {
            const transientMoving = normalizedSpeedKmh >= 1;

            app.ui.updateSpeedDisplay(normalizedSpeedKmh, transientMoving ? 'Live movement detected' : 'Waiting for movement...', transientMoving);
            return;
        }

        state.lastTrackedPoint = currentPoint;
        state.lastTrackTimestamp = now;

        state.mapInterface.selectPoint(latitude, longitude, { resolveLocation: false });
        rotateMapToHeading(heading, normalizedSpeedKmh, movedMeters);
        state.mapInterface.setUserLocation?.(latitude, longitude, { accuracy, heading });
        setLocatingState(false);

        const isMoving = normalizedSpeedKmh >= 1;
        app.ui.updateSpeedDisplay(normalizedSpeedKmh, isMoving ? 'Live movement detected' : 'You look stationary', isMoving);

        app.geo.publishLocationReady(position, normalizedSpeedKmh);
        app.reporting.evaluateAutoReporting(position, normalizedSpeedKmh, now);

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
