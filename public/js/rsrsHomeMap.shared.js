// Shared state and constants for the RSRS home map scripts.
(function (window) {
    const app = window.RSRSHomeMap = window.RSRSHomeMap || {};

    app.constants = {
        LOCATION_FOCUS_ZOOM: 16,
        LOCATION_DETAIL_ZOOM: 18,
        FLY_ANIMATION: {
            animate: true,
            duration: 1.05,
            easeLinearity: 0.25,
        },
        AUTO_EVALUATION_INTERVAL_MS: 3000,
        STATIONARY_SPEED_THRESHOLD_KMH: 4,
        STATIONARY_MOVEMENT_THRESHOLD_METERS: 12,
        STATIONARY_ACCURACY_MOVEMENT_RATIO: 0.45,
        STATIONARY_MAX_MOVEMENT_THRESHOLD_METERS: 35,
        MIN_COMPUTED_SPEED_SAMPLE_SECONDS: 2,
        LOW_CONFIDENCE_ACCURACY_METERS: 120,
        MOTION_CONFIRMATION_MS: 9000,
        MOTION_GRACE_MS: 5500,
        AUTO_REPORT_MIN_CONFIRMED_SPEED_KMH: 8,
        AUTO_ROTATE_MIN_SPEED_KMH: 12,
        AUTO_ROTATE_MIN_MOVEMENT_METERS: 14,
        AUTO_ROTATE_MAX_ACCURACY_METERS: 80,
        AUTO_ROTATE_HEADING_DEADZONE_DEGREES: 12,
        AUTO_ROTATE_SMOOTHING: 0.18,
    };

    app.state = app.state || {
        mapInterface: null,
        watchId: null,
        hasCentered: false,
        userHasAdjustedView: false,
        autoRotateEnabled: false,
        rotateControlEl: null,
        rotateToggleEl: null,
        lastAutoRotateHeading: null,
        lastTrackedPoint: null,
        lastTrackTimestamp: 0,
        confirmedMovingSince: null,
        lastReliableMotionAt: 0,
        locationButton: null,
        zoomToUserOnNextFix: false,
        locationViewMode: 'idle',
        hasPublishedLocationReady: false,
        lastAutoEvaluationAt: 0,
        autoEvaluationInFlight: false,
        autoReportInFlight: false,
        lastAutoReportSample: null,
        pendingInitialPosition: null,
        reloadScheduled: false,
        reportedRuleIds: new Set(),
        ui: {},
    };
})(window);
