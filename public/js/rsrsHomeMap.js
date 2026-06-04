// Entry point for the RSRS home map.
(function (window) {
    const app = window.RSRSHomeMap;
    const state = app.state;

    function initWhenMapReady() {
        const mapEl = document.getElementById('mainPublicMap');
        if (!mapEl) return;

        app.controls.requestGpsDetectionEarly();
        app.ui.cacheSpeedWidget();
        app.ui.updateSpeedDisplay(0, 'Waiting for movement...', false);
        app.ui.updateSpeedAlert({
            state: 'idle',
            label: 'Speed info',
            message: 'We are checking your location and the nearest speed rule.',
            location: 'waiting...',
            limit: 'unknown',
        });

        const wireUp = () => {
            if (!mapEl.mapApi) return;

            state.mapInterface = mapEl.mapApi;
            state.mapInterface.ensureSize();
            app.controls.createLocationControl();
            app.controls.customizeRotateControl();

            const markUserAdjusted = () => {
                if (state.hasCentered) {
                    state.userHasAdjustedView = true;
                }
            };

            const clearFocusedMode = () => {
                if (state.userHasAdjustedView) {
                    state.locationViewMode = 'idle';
                    app.controls.setLocationButtonMode(state.locationViewMode);
                }
            };

            state.mapInterface.map.on('zoomstart', markUserAdjusted);
            state.mapInterface.map.on('dragstart', markUserAdjusted);
            state.mapInterface.map.on('zoomend', clearFocusedMode);
            state.mapInterface.map.on('dragend', clearFocusedMode);

            if (state.pendingInitialPosition) {
                app.controls.applyPosition(state.pendingInitialPosition);
                app.controls.startWatch(true);
                state.pendingInitialPosition = null;
                return;
            }

            app.controls.bootstrapGps(false);
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
})(window);
