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
        STATIONARY_SPEED_THRESHOLD_KMH: 0.8,
        STATIONARY_MOVEMENT_THRESHOLD_METERS: 2.5,
        LOW_CONFIDENCE_ACCURACY_METERS: 120,
        AUTO_ROTATE_MIN_SPEED_KMH: 1,
        AUTO_ROTATE_MIN_MOVEMENT_METERS: 3,
    };

    app.state = app.state || {
        mapInterface: null,
        watchId: null,
        hasCentered: false,
        userHasAdjustedView: false,
        autoRotateEnabled: true,
        rotateControlEl: null,
        rotateToggleEl: null,
        lastAutoRotateHeading: null,
        lastTrackedPoint: null,
        lastTrackTimestamp: 0,
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
