// Home map geolocation, speed, distance, and heading helpers.
(function (window) {
    const app = window.RSRSHomeMap;
    const state = app.state;
    const constants = app.constants;

    function resolveMotion(position, now, currentPoint) {
        const directSpeed = Number(position.coords.speed);
        const hasPreviousPoint = Boolean(state.lastTrackedPoint && state.lastTrackTimestamp);
        const movedMeters = hasPreviousPoint ? distanceInMeters(state.lastTrackedPoint, currentPoint) : 0;
        const elapsedSeconds = hasPreviousPoint ? (now - state.lastTrackTimestamp) / 1000 : 0;
        const computedSpeedKmh = elapsedSeconds > 0 ? (movedMeters / elapsedSeconds) * 3.6 : 0;

        if (Number.isFinite(directSpeed) && directSpeed >= 0) {
            return {
                speedKmh: directSpeed * 3.6,
                source: 'gps',
                computedSpeedKmh,
                elapsedSeconds,
            };
        }

        return {
            speedKmh: computedSpeedKmh,
            source: hasPreviousPoint ? 'computed' : 'none',
            computedSpeedKmh,
            elapsedSeconds,
        };
    }

    function resolveSpeedKmh(position, now, currentPoint) {
        return resolveMotion(position, now, currentPoint).speedKmh;
    }

    function movementThresholdMeters(accuracyMeters) {
        const accuracy = Number.isFinite(accuracyMeters) ? Math.max(0, accuracyMeters) : Infinity;

        if (!Number.isFinite(accuracy)) {
            return constants.STATIONARY_MAX_MOVEMENT_THRESHOLD_METERS;
        }

        return Math.min(
            constants.STATIONARY_MAX_MOVEMENT_THRESHOLD_METERS,
            Math.max(
                constants.STATIONARY_MOVEMENT_THRESHOLD_METERS,
                accuracy * constants.STATIONARY_ACCURACY_MOVEMENT_RATIO
            )
        );
    }

    function displayMovementThresholdMeters(accuracyMeters) {
        const accuracy = Number.isFinite(accuracyMeters) ? Math.max(0, accuracyMeters) : Infinity;

        if (!Number.isFinite(accuracy)) {
            return constants.DISPLAY_MAX_MOVEMENT_THRESHOLD_METERS;
        }

        return Math.min(
            constants.DISPLAY_MAX_MOVEMENT_THRESHOLD_METERS,
            Math.max(
                constants.DISPLAY_MOVEMENT_THRESHOLD_METERS,
                accuracy * constants.DISPLAY_ACCURACY_MOVEMENT_RATIO
            )
        );
    }

    function isDisplayMovement(movedMeters, accuracyMeters) {
        const movement = Number.isFinite(movedMeters) ? Math.max(0, movedMeters) : 0;

        return movement >= displayMovementThresholdMeters(accuracyMeters);
    }

    function isReliableMovement(movedMeters, accuracyMeters) {
        const movement = Number.isFinite(movedMeters) ? Math.max(0, movedMeters) : 0;

        return movement >= movementThresholdMeters(accuracyMeters);
    }

    function normalizeDisplaySpeedKmh(rawSpeedKmh, movedMeters, accuracyMeters, source = 'computed', elapsedSeconds = 0) {
        const speed = Number.isFinite(rawSpeedKmh) ? Math.max(0, rawSpeedKmh) : 0;
        const accuracy = Number.isFinite(accuracyMeters) ? Math.max(0, accuracyMeters) : Infinity;

        if (speed < constants.DISPLAY_SPEED_THRESHOLD_KMH || accuracy > constants.LOW_CONFIDENCE_ACCURACY_METERS) {
            return 0;
        }

        if (source === 'gps') {
            return speed;
        }

        if (elapsedSeconds < constants.DISPLAY_MIN_COMPUTED_SPEED_SAMPLE_SECONDS) {
            return 0;
        }

        return isDisplayMovement(movedMeters, accuracyMeters) ? speed : 0;
    }

    function normalizeSpeedKmh(rawSpeedKmh, movedMeters, accuracyMeters, source = 'computed', elapsedSeconds = 0) {
        const speed = Number.isFinite(rawSpeedKmh) ? Math.max(0, rawSpeedKmh) : 0;
        const movement = Number.isFinite(movedMeters) ? Math.max(0, movedMeters) : 0;
        const accuracy = Number.isFinite(accuracyMeters) ? Math.max(0, accuracyMeters) : Infinity;
        const reliableMovement = isReliableMovement(movement, accuracy);

        if (speed < constants.STATIONARY_SPEED_THRESHOLD_KMH) {
            return 0;
        }

        if (accuracy > constants.LOW_CONFIDENCE_ACCURACY_METERS) {
            return 0;
        }

        if (source === 'computed' && elapsedSeconds < constants.MIN_COMPUTED_SPEED_SAMPLE_SECONDS) {
            return 0;
        }

        if (source === 'computed' && !reliableMovement) {
            return 0;
        }

        if (!reliableMovement && speed < constants.AUTO_REPORT_MIN_CONFIRMED_SPEED_KMH) {
            return 0;
        }

        return speed;
    }

    function updateMotionConfidence(speedKmh, movedMeters, accuracyMeters, now, source = 'computed') {
        const speed = Number.isFinite(speedKmh) ? Math.max(0, speedKmh) : 0;
        const accuracy = Number.isFinite(accuracyMeters) ? Math.max(0, accuracyMeters) : Infinity;
        const reliableMovement = isReliableMovement(movedMeters, accuracyMeters);
        const reliableSpeed = speed >= constants.AUTO_REPORT_MIN_CONFIRMED_SPEED_KMH;
        const reliableGpsSpeed =
            source === 'gps' &&
            reliableSpeed &&
            accuracy <= constants.AUTO_ROTATE_MAX_ACCURACY_METERS;
        const isMoving = (reliableMovement || reliableGpsSpeed) && reliableSpeed;

        if (isMoving) {
            if (!state.confirmedMovingSince) {
                state.confirmedMovingSince = now;
            }
            state.lastReliableMotionAt = now;
        } else if (!state.lastReliableMotionAt || now - state.lastReliableMotionAt > constants.MOTION_GRACE_MS) {
            state.confirmedMovingSince = null;
            state.lastReliableMotionAt = 0;
        }

        return {
            reliableMovement,
            reliableGpsSpeed,
            reliableSpeed,
            isMovingCandidate: isMoving,
            confirmedMotion: Boolean(
                state.confirmedMovingSince &&
                now - state.confirmedMovingSince >= constants.MOTION_CONFIRMATION_MS
            ),
            movementThresholdMeters: movementThresholdMeters(accuracyMeters),
        };
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

    function angleDeltaDegrees(from, to) {
        return ((to - from + 540) % 360) - 180;
    }

    function smoothHeadingDegrees(previous, next, factor) {
        const safeFactor = Math.max(0, Math.min(1, Number(factor) || 0));

        return (previous + angleDeltaDegrees(previous, next) * safeFactor + 360) % 360;
    }

    app.geo = {
        resolveMotion,
        resolveSpeedKmh,
        normalizeDisplaySpeedKmh,
        normalizeSpeedKmh,
        updateMotionConfidence,
        displayMovementThresholdMeters,
        isDisplayMovement,
        movementThresholdMeters,
        isReliableMovement,
        publishLocationReady,
        distanceInMeters,
        bearingDegrees,
        angleDeltaDegrees,
        smoothHeadingDegrees,
    };
})(window);
