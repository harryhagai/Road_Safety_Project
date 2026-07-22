// Automatic speed reporting for the RSRS home map.
(function (window) {
    const app = window.RSRSHomeMap;
    const state = app.state;
    const constants = app.constants;

    function getAutoReportingConfig() {
        const config = window.rsrsAutoSpeedReporting || {};

        if (!config.evaluateUrl || !config.csrfToken) {
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

    function buildAutoReportSample(position, speedKmh, context = {}) {
        const latitude = Number(position.coords.latitude);
        const longitude = Number(position.coords.longitude);
        const accuracy = Number(position.coords.accuracy);
        const heading = Number(position.coords.heading);
        const confirmedMotion = context.confirmedMotion === true;
        const observedMovement = context.observedMovement === true;

        return {
            latitude,
            longitude,
            speed_kmh: (confirmedMotion || observedMovement) && Number.isFinite(speedKmh) ? Math.max(0, speedKmh) : 0,
            accuracy: Number.isFinite(accuracy) ? accuracy : null,
            heading: Number.isFinite(heading) && heading >= 0 ? heading : null,
            confirmed_motion: confirmedMotion,
            observed_movement: observedMovement,
        };
    }

    function resetReportedRuleIfSafe(evaluation) {
        if (!evaluation?.matched || !evaluation?.rule?.id || evaluation.exceeded) return;

        state.reportedRuleIds.delete(Number(evaluation.rule.id));
    }

    function ruleDisplayForEvaluation(evaluation) {
        const limit = Number(evaluation?.speed_limit_kmh);

        return evaluation?.rule?.display ||
            evaluation?.rule?.name ||
            (Number.isFinite(limit) ? `${Math.round(limit)} km/h` : 'this rule');
    }

    function alertOptionsForEvaluation(evaluation) {
        const ruleDisplay = ruleDisplayForEvaluation(evaluation);

        return {
            location: evaluation?.segment?.db_name || evaluation?.segment?.name || 'matched road segment',
            ruleLabel: evaluation?.is_no_parking_rule ? 'RULE' : 'SPEED RULE',
            limit: ruleDisplay,
            ruleName: ruleDisplay,
        };
    }

    async function submitAutoReport(evaluation, sample) {
        const config = getAutoReportingConfig();
        const ruleId = Number(evaluation?.rule?.id);
        const segmentId = Number(evaluation?.segment?.id);
        const runtime = window.rsrsHomeRuntime || {};

        if (
            !config ||
            !config.authenticated ||
            !config.storeUrl ||
            !ruleId ||
            !segmentId ||
            (!sample.confirmed_motion && !sample.observed_movement && evaluation?.requires_stationary !== true) ||
            state.autoReportInFlight ||
            state.reportedRuleIds.has(ruleId)
        ) {
            return;
        }

        state.autoReportInFlight = true;

        try {
            const result = await postAutoJson(config.storeUrl, {
                ...sample,
                rule_id: ruleId,
                segment_id: segmentId,
            }, config.csrfToken);

            state.reportedRuleIds.add(ruleId);
            const reference = result.reference_no || '';
            const referenceStatus = reference ? `: ${reference}` : '';
            const popupOptions = alertOptionsForEvaluation(evaluation);

            app.ui.updateSpeedDisplay(
                sample.speed_kmh,
                result.duplicate ? 'Automatic report already submitted' : `Automatic report submitted${referenceStatus}`,
                true
            );
            app.ui.updateSpeedAlert({
                state: 'danger',
                ...popupOptions,
                keepPopup: true,
            });
            app.ui.showReportSubmittedPopup({
                ...popupOptions,
                title: result.duplicate ? 'Violation already reported' : 'Violation reported',
                reference,
                duplicate: result.duplicate,
            });

            if (runtime.reloadAfterAutoReportSubmission && !state.reloadScheduled) {
                state.reloadScheduled = true;
                const delayMs = Math.max(1800, Number(runtime.reloadDelayMs) || 2400);
                setTimeout(() => window.location.reload(), delayMs);
            }
        } catch (error) {
            const response = error.response || {};
            const popupOptions = alertOptionsForEvaluation(evaluation);
            let statusText = response.message || 'Automatic reporting unavailable right now';

            if (response.reason === 'duration_pending' && Number.isFinite(Number(response.exceeded_seconds))) {
                statusText = `Rule pending for ${Math.round(Number(response.exceeded_seconds))}s`;
            } else if (response.reason === 'speed_within_limit') {
                statusText = 'Speed is back within the saved limit';
            } else if (error.status === 401 || response.reason === 'driver_authentication_required') {
                statusText = 'Driver login is required before reporting';
            } else if (error.status === 422) {
                statusText = 'Automatic report data could not be validated';
            }

            app.ui.updateSpeedDisplay(sample.speed_kmh, statusText, sample.speed_kmh >= 1);
            app.ui.updateSpeedAlert({
                state: 'info',
                ...popupOptions,
            });
        } finally {
            state.autoReportInFlight = false;
        }
    }

    function continuePassengerReport(evaluation, sample, popupOptions) {
        const config = getAutoReportingConfig();
        const passengerUrl = evaluation?.passenger_report_url;

        if (config?.authenticated || !passengerUrl) {
            return false;
        }

        if (state.autoReportInFlight) {
            return true;
        }

        state.autoReportInFlight = true;
        app.ui.updateSpeedDisplay(sample.speed_kmh, 'Opening passenger bus details form...', sample.speed_kmh >= 1);
        app.ui.updateSpeedAlert({
            state: 'danger',
            ...popupOptions,
            keepPopup: true,
        });

        window.setTimeout(() => {
            window.location.assign(passengerUrl);
        }, 500);

        return true;
    }

    function continueRecentlySubmittedPassengerReport(evaluation, sample, popupOptions) {
        if (evaluation?.report_mode !== 'passenger_recently_submitted') {
            return false;
        }

        const ruleId = Number(evaluation?.rule?.id);
        const reference = evaluation?.reference_no || '';
        const referenceStatus = reference ? `: ${reference}` : '';

        if (ruleId) {
            state.reportedRuleIds.add(ruleId);
        }

        app.ui.updateSpeedDisplay(
            sample.speed_kmh,
            `Passenger report already submitted${referenceStatus}`,
            sample.speed_kmh >= 1
        );
        app.ui.updateSpeedAlert({
            state: 'success',
            ...popupOptions,
        });

        if (ruleId && !state.recentPassengerReportPopupRuleIds?.has(ruleId)) {
            state.recentPassengerReportPopupRuleIds = state.recentPassengerReportPopupRuleIds || new Set();
            state.recentPassengerReportPopupRuleIds.add(ruleId);
            app.ui.showReportSubmittedPopup({
                ...popupOptions,
                title: 'Violation already reported',
                reference,
                duplicate: true,
            });
        }

        return true;
    }

    function continueDriverReport(evaluation, sample, popupOptions) {
        const config = getAutoReportingConfig();
        const driverUrl = evaluation?.driver_report_url;

        if (!config?.authenticated || !driverUrl) {
            return false;
        }

        if (state.autoReportInFlight) {
            return true;
        }

        state.autoReportInFlight = true;
        app.ui.updateSpeedDisplay(sample.speed_kmh, 'Opening driver report confirmation...', sample.speed_kmh >= 1);
        app.ui.updateSpeedAlert({
            state: 'danger',
            ...popupOptions,
            keepPopup: true,
        });

        window.setTimeout(() => {
            window.location.assign(driverUrl);
        }, 500);

        return true;
    }

    function handleAutoEvaluation(evaluation, sample) {
        resetReportedRuleIfSafe(evaluation);

        if (!evaluation?.matched) {
            if (sample.speed_kmh >= 1) {
                app.ui.updateSpeedDisplay(sample.speed_kmh, 'No monitored speed rule nearby', true);
            }

            const lowAccuracy = evaluation?.reason === 'low_accuracy';
            const requiredAccuracy = Number(evaluation?.required_accuracy_meters);
            const accuracyNow = Number(evaluation?.accuracy_meters);
            const lowAccuracyMessage = Number.isFinite(accuracyNow) && Number.isFinite(requiredAccuracy)
                ? `GPS accuracy is ${Math.round(accuracyNow)}m. Move to open sky until it improves to ${Math.round(requiredAccuracy)}m or better.`
                : 'GPS accuracy is too low for reliable segment matching.';

            app.ui.updateSpeedAlert({
                state: 'idle',
                location: lowAccuracy ? 'Low GPS accuracy' : 'NO SEGMENT DETECTED',
                limit: lowAccuracy ? lowAccuracyMessage : 'unknown',
            });
            return;
        }

        const limit = Number(evaluation.speed_limit_kmh);
        const limitText = Number.isFinite(limit) ? `${Math.round(limit)} km/h` : 'saved limit';
        const segmentName = evaluation.segment?.db_name || evaluation.segment?.name || 'matched road segment';

        if (evaluation.is_no_parking_rule) {
            const displayRule = evaluation.rule?.display || evaluation.rule?.name || 'NO PARKING';
            const parkedSeconds = Math.max(0, Number(evaluation.exceeded_seconds) || 0);
            const requiredSeconds = Math.max(30, Number(evaluation.required_seconds) || 30);
            const remainingSeconds = Math.max(0, requiredSeconds - parkedSeconds);

            if (!evaluation.exceeded) {
                app.ui.updateSpeedDisplay(sample.speed_kmh, 'No parking rule active', sample.speed_kmh >= 1);
                app.ui.updateSpeedAlert({
                    state: 'info',
                    location: segmentName,
                    ruleLabel: 'RULE',
                    limit: displayRule,
                });
                return;
            }

            if (remainingSeconds > 0) {
                app.ui.updateSpeedDisplay(sample.speed_kmh, `No parking stationary for ${Math.round(parkedSeconds)}s`, false);
                app.ui.updateSpeedAlert({
                    state: 'warning',
                    location: segmentName,
                    ruleLabel: 'RULE',
                    limit: displayRule,
                    countdownSeconds: remainingSeconds,
                    popupTitle: 'No parking warning',
                });
                return;
            }

            const noParkingPopupOptions = {
                location: segmentName,
                ruleLabel: 'RULE',
                limit: displayRule,
            };

            if (continueRecentlySubmittedPassengerReport(evaluation, sample, noParkingPopupOptions)) {
                return;
            }

            if (continueDriverReport(evaluation, sample, noParkingPopupOptions)) {
                return;
            }

            if (continuePassengerReport(evaluation, sample, noParkingPopupOptions)) {
                return;
            }

            app.ui.updateSpeedDisplay(sample.speed_kmh, 'Submitting automatic no parking report...', false);
            app.ui.updateSpeedAlert({
                state: 'danger',
                location: segmentName,
                ruleLabel: 'RULE',
                limit: displayRule,
                keepPopup: true,
            });
            app.ui.showReportSubmittingPopup({
                location: segmentName,
                ruleLabel: 'RULE',
                limit: displayRule,
                ruleName: displayRule,
                title: 'Submitting no parking report',
            });
            submitAutoReport(evaluation, sample);
            return;
        }

        if (evaluation.has_speed_rule === false) {
            const displayRule = evaluation.rule?.display || evaluation.rule?.name || 'NOT CONFIGURED';

            app.ui.updateSpeedDisplay(sample.speed_kmh, 'Segment detected without speed rule', sample.speed_kmh >= 1);
            app.ui.updateSpeedAlert({
                state: 'idle',
                location: segmentName,
                ruleLabel: evaluation.rule ? 'RULE' : 'SPEED RULE',
                limit: displayRule,
            });
            return;
        }

        if (!evaluation.exceeded) {
            app.ui.updateSpeedDisplay(sample.speed_kmh, `Speed limit ${limitText} active`, sample.speed_kmh >= 1);
            app.ui.updateSpeedAlert({
                state: 'info',
                location: segmentName,
                limit: limitText,
            });
            return;
        }

        const exceededSeconds = Math.max(0, Number(evaluation.exceeded_seconds) || 0);
        const requiredSeconds = Math.max(30, Number(evaluation.required_seconds) || 30);
        const remainingSeconds = Math.max(0, requiredSeconds - exceededSeconds);

        if (remainingSeconds > 0) {
            app.ui.updateSpeedDisplay(sample.speed_kmh, `Limit ${limitText} exceeded for ${Math.round(exceededSeconds)}s`, true);
            app.ui.updateSpeedAlert({
                state: 'warning',
                location: segmentName,
                limit: limitText,
                countdownSeconds: remainingSeconds,
                popupTitle: 'Speed warning',
            });
            return;
        }

        const speedPopupOptions = {
            location: segmentName,
            ruleLabel: 'SPEED RULE',
            limit: ruleDisplayForEvaluation(evaluation),
        };

        if (continueRecentlySubmittedPassengerReport(evaluation, sample, speedPopupOptions)) {
            return;
        }

        if (continueDriverReport(evaluation, sample, speedPopupOptions)) {
            return;
        }

        if (continuePassengerReport(evaluation, sample, speedPopupOptions)) {
            return;
        }

        app.ui.updateSpeedDisplay(sample.speed_kmh, 'Submitting automatic speed report...', true);
        app.ui.updateSpeedAlert({
            state: 'danger',
            location: segmentName,
            limit: limitText,
            keepPopup: true,
        });
        app.ui.showReportSubmittingPopup({
            location: segmentName,
            ruleLabel: 'SPEED RULE',
            limit: ruleDisplayForEvaluation(evaluation),
            ruleName: ruleDisplayForEvaluation(evaluation),
            title: 'Submitting speed report',
        });
        submitAutoReport(evaluation, sample);
    }

    function evaluateAutoReporting(position, speedKmh, now, context = {}) {
        const config = getAutoReportingConfig();

        if (
            !config ||
            state.autoEvaluationInFlight ||
            now - state.lastAutoEvaluationAt < constants.AUTO_EVALUATION_INTERVAL_MS
        ) {
            return;
        }

        const sample = buildAutoReportSample(position, speedKmh, context);

        if (!Number.isFinite(sample.latitude) || !Number.isFinite(sample.longitude)) {
            return;
        }

        state.lastAutoReportSample = sample;
        state.lastAutoEvaluationAt = now;
        state.autoEvaluationInFlight = true;

        postAutoJson(config.evaluateUrl, sample, config.csrfToken)
            .then((evaluation) => handleAutoEvaluation(evaluation, state.lastAutoReportSample || sample))
            .catch((error) => {
                if (error.status !== 422 && speedKmh >= 1) {
                    app.ui.updateSpeedDisplay(speedKmh, 'Automatic reporting check failed', true);
                }
            })
            .finally(() => {
                state.autoEvaluationInFlight = false;
            });
    }

    app.reporting = {
        evaluateAutoReporting,
    };
})(window);
