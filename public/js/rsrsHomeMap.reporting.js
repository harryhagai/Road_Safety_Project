// Automatic speed reporting and telemetry feedback handling.
(function (window) {
    const app = window.RSRSHomeMap;
    const state = app.state;
    const constants = app.constants;

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

    function resetReportedRuleIfSafe(evaluation) {
        if (!evaluation?.matched || !evaluation?.rule?.id || evaluation.exceeded) return;

        state.reportedRuleIds.delete(Number(evaluation.rule.id));
    }

    async function submitAutoReport(evaluation, telemetry) {
        const config = getAutoReportingConfig();
        const ruleId = Number(evaluation?.rule?.id);
        const segmentId = Number(evaluation?.segment?.id);
        const runtime = window.rsrsHomeRuntime || {};

        if (!config || !ruleId || !segmentId || state.autoReportInFlight || state.reportedRuleIds.has(ruleId)) {
            return;
        }

        state.autoReportInFlight = true;

        try {
            const result = await postAutoJson(config.storeUrl, {
                ...telemetry,
                rule_id: ruleId,
                segment_id: segmentId,
            }, config.csrfToken);

            state.reportedRuleIds.add(ruleId);
            const reference = result.reference_no ? `: ${result.reference_no}` : '';

            app.ui.updateSpeedDisplay(
                telemetry.speed_kmh,
                result.duplicate ? 'Automatic report already submitted' : `Automatic report submitted${reference}`,
                true
            );
            app.ui.updateSpeedAlert({
                state: 'danger',
                label: result.duplicate ? 'Report already submitted' : 'Speed violation reported',
                message: result.duplicate
                    ? 'Your speed is still above the limit, and a report for this area is already in the system.'
                    : `An automatic report was submitted because the speed did not go down within 30 seconds${reference}.`,
                location: evaluation?.segment?.name || 'matched road segment',
                limit: `${Math.round(Number(evaluation.speed_limit_kmh))} km/h`,
            });

            if (!result.duplicate && runtime.reloadAfterAutoReportSubmission && !state.reloadScheduled) {
                state.reloadScheduled = true;
                const delayMs = Math.max(300, Number(runtime.reloadDelayMs) || 1400);
                setTimeout(() => window.location.reload(), delayMs);
            }
        } catch (error) {
            const response = error.response || {};

            if (response.reason === 'duration_pending' && Number.isFinite(Number(response.exceeded_seconds))) {
                app.ui.updateSpeedDisplay(telemetry.speed_kmh, `Speed limit exceeded for ${Math.round(Number(response.exceeded_seconds))}s`, true);
            } else if (response.reason === 'speed_within_limit') {
                app.ui.updateSpeedDisplay(telemetry.speed_kmh, 'Speed is back within the saved limit', telemetry.speed_kmh >= 1);
            } else if (error.status !== 422) {
                app.ui.updateSpeedDisplay(telemetry.speed_kmh, 'Automatic reporting unavailable right now', telemetry.speed_kmh >= 1);
            }
        } finally {
            state.autoReportInFlight = false;
        }
    }

    function handleAutoEvaluation(evaluation, telemetry) {
        if (app.isTelemetryAlertPinned()) {
            return;
        }

        resetReportedRuleIfSafe(evaluation);

        if (!evaluation?.matched) {
            if (telemetry.speed_kmh >= 1) {
                app.ui.updateSpeedDisplay(telemetry.speed_kmh, 'No monitored speed rule nearby', true);
            }

            const lowAccuracy = evaluation?.reason === 'low_accuracy';
            const requiredAccuracy = Number(evaluation?.required_accuracy_meters);
            const accuracyNow = Number(evaluation?.accuracy_meters);
            const lowAccuracyMessage = Number.isFinite(accuracyNow) && Number.isFinite(requiredAccuracy)
                ? `GPS accuracy is ${Math.round(accuracyNow)}m. Move to open sky until it improves to ${Math.round(requiredAccuracy)}m or better.`
                : 'GPS accuracy is too low for reliable segment matching.';

            app.ui.updateSpeedAlert({
                state: 'idle',
                label: lowAccuracy ? 'Low GPS accuracy' : 'No nearby speed rule',
                message: lowAccuracy
                    ? lowAccuracyMessage
                    : (evaluation?.message || 'Your location did not match any road segment with a speed limit in the database.'),
                location: 'not matched',
                limit: 'unknown',
            });
            return;
        }

        const limit = Number(evaluation.speed_limit_kmh);
        const limitText = Number.isFinite(limit) ? `${Math.round(limit)} km/h` : 'saved limit';
        const segmentName = evaluation.segment?.db_name || evaluation.segment?.name || 'matched road segment';

        if (!evaluation.exceeded) {
            app.ui.updateSpeedDisplay(telemetry.speed_kmh, `Speed limit ${limitText} active`, telemetry.speed_kmh >= 1);
            app.ui.updateSpeedAlert({
                state: 'info',
                label: 'Speed is within limit',
                message: `Your speed is within the rule for this area. Please stay below ${limitText}.`,
                location: segmentName,
                limit: limitText,
            });
            return;
        }

        const exceededSeconds = Math.max(0, Number(evaluation.exceeded_seconds) || 0);
        const requiredSeconds = Math.max(30, Number(evaluation.required_seconds) || 30);
        const remainingSeconds = Math.max(0, requiredSeconds - exceededSeconds);

        if (remainingSeconds > 0) {
            app.ui.updateSpeedDisplay(telemetry.speed_kmh, `Limit ${limitText} exceeded for ${Math.round(exceededSeconds)}s`, true);
            app.ui.updateSpeedAlert({
                state: 'warning',
                label: 'Warning: speed limit exceeded',
                message: `Reduce speed to ${limitText}. Auto report will be submitted in ${remainingSeconds}s if speed stays above limit.`,
                location: segmentName,
                limit: limitText,
            });
            return;
        }

        app.ui.updateSpeedDisplay(telemetry.speed_kmh, 'Submitting automatic speed report...', true);
        app.ui.updateSpeedAlert({
            state: 'danger',
            label: 'Danger: auto report starting',
            message: `Your speed stayed above ${limitText} for 30 seconds. The system is submitting a speed violation report.`,
            location: segmentName,
            limit: limitText,
        });
        submitAutoReport(evaluation, telemetry);
    }

    function evaluateAutoReporting(position, speedKmh, now) {
        const config = getAutoReportingConfig();

        if (
            !config ||
            app.isTelemetryAlertPinned() ||
            state.autoEvaluationInFlight ||
            now - state.lastAutoEvaluationAt < constants.AUTO_EVALUATION_INTERVAL_MS
        ) {
            return;
        }

        const telemetry = buildAutoTelemetry(position, speedKmh);

        if (!Number.isFinite(telemetry.latitude) || !Number.isFinite(telemetry.longitude)) {
            return;
        }

        state.lastAutoTelemetry = telemetry;
        state.lastAutoEvaluationAt = now;
        state.autoEvaluationInFlight = true;

        postAutoJson(config.evaluateUrl, telemetry, config.csrfToken)
            .then((evaluation) => handleAutoEvaluation(evaluation, state.lastAutoTelemetry || telemetry))
            .catch((error) => {
                if (!app.isTelemetryAlertPinned() && error.status !== 422 && speedKmh >= 1) {
                    app.ui.updateSpeedDisplay(speedKmh, 'Automatic reporting check failed', true);
                }
            })
            .finally(() => {
                state.autoEvaluationInFlight = false;
            });
    }

    function handleVehicleTelemetryFeedback(event) {
        const payload = event?.detail || {};
        const speedKmh = Number(payload.current_speed ?? payload.speed_kmh ?? 0);
        const segmentName = payload.segment || payload.segment_name || 'matched road segment';
        const reference = payload.report_reference_no ? ` Reference: ${payload.report_reference_no}.` : '';

        if (payload.rule_alert === 'no_parking_pending') {
            const pending = payload.no_parking || {};
            const elapsed = Math.max(0, Number(pending.elapsed_seconds) || 0);
            const remaining = Math.max(0, Number(pending.remaining_seconds) || 0);

            app.pinTelemetryAlert(12000);
            app.ui.updateSpeedDisplay(Number.isFinite(speedKmh) ? speedKmh : 0, `No parking timer ${Math.round(elapsed)}s`, false);
            app.ui.updateSpeedAlert({
                state: 'warning',
                label: 'No parking zone',
                message: `Vehicle is stationary in a no-parking segment. Report will be submitted in ${Math.round(remaining)}s if it stays parked.`,
                location: segmentName,
                limit: 'No parking',
            });
            return;
        }

        if (payload.rule_alert === 'no_parking') {
            const duplicateText = payload.report_duplicate ? 'No parking report already submitted' : 'No parking report submitted';

            app.pinTelemetryAlert(35000);
            app.ui.updateSpeedDisplay(Number.isFinite(speedKmh) ? speedKmh : 0, duplicateText, false);
            app.ui.updateSpeedAlert({
                state: 'danger',
                label: payload.report_duplicate ? 'Report already submitted' : 'No parking detected',
                message: payload.report_duplicate
                    ? `A no-parking report for this segment is already in the system.${reference}`
                    : `The vehicle stayed stationary inside a no-parking segment for 30 seconds.${reference}`,
                location: segmentName,
                limit: 'No parking',
            });

            const runtime = window.rsrsHomeRuntime || {};
            if (payload.report_created && runtime.reloadAfterAutoReportSubmission && !state.reloadScheduled) {
                state.reloadScheduled = true;
                const delayMs = Math.max(300, Number(runtime.reloadDelayMs) || 1400);
                setTimeout(() => window.location.reload(), delayMs);
            }
            return;
        }

        if (payload.rule_alert === 'speed_limit' && payload.report_reference_no) {
            app.pinTelemetryAlert(10000);
            app.ui.updateSpeedDisplay(Number.isFinite(speedKmh) ? speedKmh : 0, `Telemetry report submitted: ${payload.report_reference_no}`, true);
            app.ui.updateSpeedAlert({
                state: 'danger',
                label: 'Speed violation reported',
                message: `A telemetry report has been submitted for this segment. Reference: ${payload.report_reference_no}.`,
                location: segmentName,
                limit: payload.speed_limit ? `${Math.round(Number(payload.speed_limit))} km/h` : 'saved limit',
            });
        }
    }

    app.reporting = {
        evaluateAutoReporting,
        handleVehicleTelemetryFeedback,
    };
})(window);
