(function () {
    const LOCATION_FOCUS_ZOOM = 16;
    const LOCATION_DETAIL_ZOOM = 18;
    const FLY_ANIMATION = {
        animate: true,
        duration: 1.05,
        easeLinearity: 0.25,
    };

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
    let hasPublishedLocationReady = false;

    function cacheSpeedWidget() {
        if (speedWidget) return;

        speedWidget = document.querySelector('[data-home-speed-widget]');
        speedValueEl = document.querySelector('[data-home-speed-value]');
        speedStatusEl = document.querySelector('[data-home-speed-status]');
    }

    function updateSpeedDisplay(speedKmh, statusText, isLive) {
        cacheSpeedWidget();
        if (!speedWidget || !speedValueEl || !speedStatusEl) return;

        const safeSpeed = Number.isFinite(speedKmh) ? Math.max(0, speedKmh) : 0;
        speedValueEl.textContent = String(Math.round(safeSpeed));
        speedStatusEl.textContent = statusText;
        speedWidget.classList.toggle('is-live', Boolean(isLive));
        speedWidget.classList.toggle('is-idle', !isLive);
    }

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

    function setLocationButtonMode(mode) {
        if (!locationButton) return;

        const isDetail = mode === 'detail';
        locationButton.classList.toggle('is-detail-view', isDetail);
        locationButton.title = isDetail ? 'Switch to wider location view' : 'Use my current location';
    }

    function getNextLocationViewMode() {
        if (locationViewMode === 'idle') return 'focus';
        if (locationViewMode === 'focus') return 'detail';
        return 'focus';
    }

    function getTargetZoom(mode) {
        if (!mapInterface?.map) return LOCATION_FOCUS_ZOOM;
        const maxZoom = Number(mapInterface.map.getMaxZoom()) || LOCATION_DETAIL_ZOOM;
        return Math.min(mode === 'detail' ? LOCATION_DETAIL_ZOOM : LOCATION_FOCUS_ZOOM, maxZoom);
    }

    function flyToUser(lat, lng, mode) {
        if (!mapInterface?.map) return;
        mapInterface.map.flyTo([lat, lng], getTargetZoom(mode), FLY_ANIMATION);
    }

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

    function createLocationControl() {
        if (!mapInterface?.map || locationButton) return;

        const LocationControl = L.Control.extend({
            options: { position: 'topright' },
            onAdd: function () {
                const container = L.DomUtil.create('div', 'leaflet-bar home-location-control');
                const button = L.DomUtil.create('button', 'home-location-control__button', container);
                button.type = 'button';
                button.title = 'Use my current location';
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

        mapInterface.map.addControl(new LocationControl());
    }

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

    function applyPosition(position) {
        if (!mapInterface) return;

        const latitude = Number(position.coords.latitude);
        const longitude = Number(position.coords.longitude);
        const accuracy = Number(position.coords.accuracy);
        const now = Date.now();
        const currentPoint = { lat: latitude, lng: longitude };
        const speedKmh = resolveSpeedKmh(position, now, currentPoint);
        const isMoving = speedKmh >= 1;

        if (lastTrackedPoint) {
            const movedMeters = distanceInMeters(lastTrackedPoint, currentPoint);
            if (movedMeters < 5 && now - lastTrackTimestamp < 1200) {
                updateSpeedDisplay(speedKmh, isMoving ? 'Live movement detected' : 'Waiting for movement...', isMoving);
                return;
            }
        }

        lastTrackedPoint = currentPoint;
        lastTrackTimestamp = now;

        mapInterface.selectPoint(latitude, longitude, { resolveLocation: false });
        mapInterface.setUserLocation?.(latitude, longitude, { accuracy });
        setLocatingState(false);
        updateSpeedDisplay(speedKmh, isMoving ? 'Live movement detected' : 'You look stationary', isMoving);
        publishLocationReady(position, speedKmh);

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

    function bootstrapGps(force) {
        if (!navigator.geolocation || !mapInterface) return;
        if (!force && watchId !== null) return;

        setLocatingState(true);
        updateSpeedDisplay(0, 'Checking GPS speed...', false);

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
                        startWatch(false);
                    },
                    { enableHighAccuracy: false, timeout: 8000, maximumAge: 15000 }
                );
            },
            { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
        );
    }

    function initWhenMapReady() {
        const mapEl = document.getElementById('mainPublicMap');
        if (!mapEl) return;
        cacheSpeedWidget();
        updateSpeedDisplay(0, 'Waiting for movement...', false);

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
