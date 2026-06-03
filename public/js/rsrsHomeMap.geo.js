// Home map geolocation, speed, distance, and heading helpers.
(function (window) {
    const app = window.RSRSHomeMap;
    const state = app.state;
    const constants = app.constants;

    function resolveSpeedKmh(position, now, currentPoint) {
        const directSpeed = Number(position.coords.speed);

        if (Number.isFinite(directSpeed) && directSpeed >= 0) {
            return directSpeed * 3.6;
        }

        if (!state.lastTrackedPoint || !state.lastTrackTimestamp) {
            return 0;
        }

        const movedMeters = distanceInMeters(state.lastTrackedPoint, currentPoint);
        const elapsedSeconds = (now - state.lastTrackTimestamp) / 1000;

        if (elapsedSeconds <= 0) {
            return 0;
        }

        return (movedMeters / elapsedSeconds) * 3.6;
    }

    function normalizeSpeedKmh(rawSpeedKmh, movedMeters, accuracyMeters) {
        const speed = Number.isFinite(rawSpeedKmh) ? Math.max(0, rawSpeedKmh) : 0;
        const movement = Number.isFinite(movedMeters) ? Math.max(0, movedMeters) : 0;
        const accuracy = Number.isFinite(accuracyMeters) ? Math.max(0, accuracyMeters) : Infinity;

        if (speed < constants.STATIONARY_SPEED_THRESHOLD_KMH) {
            return 0;
        }

        if (speed < 1.6 && movement < constants.STATIONARY_MOVEMENT_THRESHOLD_METERS) {
            return 0;
        }

        if (accuracy > constants.LOW_CONFIDENCE_ACCURACY_METERS && speed < 2.5) {
            return 0;
        }

        return speed;
    }

    function publishLocationReady(position, speedKmh) {
        if (state.hasPublishedLocationReady) return;

        const accuracy = Number(position.coords.accuracy);
        const hasUsableAccuracy = Number.isFinite(accuracy) && accuracy > 0 && accuracy <= 120;
        const hasUsableSpeed = Number.isFinite(speedKmh) && speedKmh >= 0;

        if (!hasUsableAccuracy || !hasUsableSpeed) {
            return;
        }

        state.hasPublishedLocationReady = true;
        document.dispatchEvent(new CustomEvent('rsrs:home-location-ready', {
            detail: {
                accuracy,
                speedKmh,
            },
        }));
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

    app.geo = {
        resolveSpeedKmh,
        normalizeSpeedKmh,
        publishLocationReady,
        distanceInMeters,
        bearingDegrees,
    };
})(window);
