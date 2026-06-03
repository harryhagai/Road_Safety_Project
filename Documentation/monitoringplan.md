# RSRS Passenger Monitoring And Android Tracking Plan

This document explains the proposed plan for adding reliable passenger trip monitoring to RSRS without breaking or replacing the existing Laravel web home page, Blade UI, map logic, or officer dashboard.

## 1. Main Decision

RSRS should keep the current Laravel web application as it is, then add a separate Android passenger app for trip tracking.

The current web UI is already good and should not be disturbed. The Android app will focus only on passenger trip monitoring, background location tracking, violation reporting, and links to selected public web pages.

Recommended architecture:

```text
Laravel Web Application
- Existing home page remains unchanged
- Existing about/help/public pages remain web pages
- Officer dashboard remains web based
- Reporting and map logic remain inside Laravel

Laravel API
- Receives trip start/stop requests
- Receives telemetry/location updates
- Receives passenger violation reports
- Provides trip status and public content links

Flutter Android App
- Passenger tracking home screen
- Start Trip / End Trip
- Active trip tracking screen
- Report violation screen
- Background GPS tracking through Android foreground service
- Links to Laravel web pages such as About, Privacy, Help
```

## 2. Why Not Change The Existing Home Blade?

The existing `home.blade.php` and public web UI should stay untouched because:

- It already serves the public web reporting experience.
- It already contains useful map/reporting logic.
- Changing it to support Android background tracking may create unnecessary risk.
- Browser/PWA tracking cannot reliably continue for long trips when the app is closed, minimized, or suspended by the phone.
- Android background tracking needs native Android capability, not only browser JavaScript.

Therefore, the clean plan is:

```text
Keep Laravel web UI stable.
Add Android app for the passenger tracking workflow.
Use Laravel API as the connection between them.
```

## 3. Android App Scope

The Android app should be small and focused. It should not try to replace the whole website.

Native Flutter screens:

- Passenger home screen
- Start Trip screen
- Active Tracking screen
- Report Violation screen
- Trip Summary / End Trip screen
- Permission and consent screen

Web links opened from the app:

- About
- Privacy Policy
- Terms
- Help
- Full RSRS website

Example links:

```text
https://rsrs-domain.com/about
https://rsrs-domain.com/privacy
https://rsrs-domain.com/help
```

These pages do not need native Android background features, so they can remain Laravel web pages.

## 4. Trip Tracking Flow

Example use case:

```text
Passenger enters a bus from Arusha to Dar es Salaam.
Passenger opens the Android app.
Passenger taps Start Trip.
App asks for location and tracking permission.
App starts a visible Android foreground notification.
App sends location/telemetry updates during the trip.
App continues tracking even when minimized, as long as permission is granted.
Trip automatically stops after 8 hours or when passenger taps End Trip.
```

Recommended tracking rules:

- Maximum trip duration: 8 hours.
- Send telemetry every 30 to 60 seconds.
- Store telemetry locally if internet is unavailable.
- Retry sending pending telemetry when internet returns.
- Stop tracking if the trip is ended manually.
- Stop tracking automatically after 8 hours.
- Show a persistent notification while tracking is active.

Notification example:

```text
RSRS trip tracking active
Your road safety trip is being monitored.
```

## 5. Required Android Capabilities

The Flutter Android app will need native Android permissions and background service support.

Expected Android permissions:

```text
ACCESS_FINE_LOCATION
ACCESS_COARSE_LOCATION
ACCESS_BACKGROUND_LOCATION
FOREGROUND_SERVICE
FOREGROUND_SERVICE_LOCATION
POST_NOTIFICATIONS
INTERNET
ACCESS_NETWORK_STATE
```

Important Android behavior:

- The app must request location permission clearly.
- Background location must be explained to the user.
- Android foreground service must show a visible notification.
- Tracking cannot be hidden from the user.
- If the user force-stops the app, disables GPS, removes permission, or enables strong battery restrictions, tracking may stop.

## 6. Laravel API Plan

The Android app should communicate with Laravel through API routes.

Proposed endpoints:

```text
POST /api/trips/start
POST /api/trips/{trip}/telemetry
POST /api/trips/{trip}/violations
POST /api/trips/{trip}/stop
GET  /api/trips/{trip}/status
```

### Start Trip

Purpose:

- Create a new passenger trip session.
- Return a `trip_id` to the Android app.
- Store device/session metadata.

Possible payload:

```json
{
  "device_id": "anonymous-device-id",
  "route_name": "Arusha to Dar es Salaam",
  "started_at": "2026-06-03T10:00:00Z",
  "start_latitude": -3.3869,
  "start_longitude": 36.6829
}
```

### Telemetry

Purpose:

- Receive location and movement updates from the Android app.

Possible payload:

```json
{
  "recorded_at": "2026-06-03T10:01:00Z",
  "latitude": -3.3874,
  "longitude": 36.6832,
  "speed_kmh": 62.5,
  "accuracy_meters": 12.4,
  "battery_level": 81,
  "network_type": "mobile"
}
```

### Violation Report

Purpose:

- Allow passenger to report overspeeding, reckless driving, unsafe overtaking, or other issues during the trip.

Possible payload:

```json
{
  "type": "overspeeding",
  "description": "Bus is moving too fast near a populated area.",
  "latitude": -5.0342,
  "longitude": 37.1221,
  "recorded_at": "2026-06-03T13:20:00Z"
}
```

### Stop Trip

Purpose:

- End the active trip.
- Stop receiving telemetry.
- Mark trip as completed, expired, or cancelled.

Possible payload:

```json
{
  "ended_at": "2026-06-03T18:00:00Z",
  "end_reason": "completed"
}
```

## 7. Suggested Database Tables

The final table names can follow the existing project style, but the main data can be organized like this.

### passenger_trips

Stores one monitoring session.

Important columns:

```text
id
public_reference
device_id
route_name
status
started_at
ended_at
expires_at
start_latitude
start_longitude
end_latitude
end_longitude
end_reason
created_at
updated_at
```

Possible statuses:

```text
active
completed
expired
cancelled
failed
```

### trip_telemetry

Stores GPS and movement records.

Important columns:

```text
id
trip_id
recorded_at
latitude
longitude
speed_kmh
accuracy_meters
battery_level
network_type
created_at
```

### trip_violations

Stores reports submitted during a trip.

Important columns:

```text
id
trip_id
type
description
latitude
longitude
recorded_at
status
created_at
updated_at
```

## 8. Offline And Retry Behavior

The Android app should not lose data when the network is poor.

Recommended behavior:

- Save telemetry locally before sending.
- Mark each record as pending, sent, or failed.
- Retry pending records when internet returns.
- Send telemetry in small batches.
- Avoid sending too frequently to reduce battery and server load.
- Keep failed records until confirmed by the server.

Example:

```text
GPS record created
Save to local queue
Try sending to Laravel API
If success: mark as sent
If fail: keep pending
Retry later
```

## 9. Privacy And Consent

Because this feature uses background location, the app must be clear and honest.

Before starting a trip, the user should see a short consent message:

```text
RSRS will collect your location during this trip to support road safety monitoring.
Tracking runs only after you start a trip and stops when the trip ends or after 8 hours.
```

The app should provide:

- Start tracking button.
- Stop tracking button.
- Visible notification while tracking is active.
- Link to privacy policy.
- Clear explanation of why location is needed.

Important privacy rule:

```text
Do not track users silently.
Do not start tracking without user action.
Do not keep tracking after trip end or after 8 hours.
```

## 10. Implementation Phases

### Phase 1: Prepare Laravel API

- Add trip start endpoint.
- Add telemetry endpoint.
- Add violation endpoint.
- Add trip stop endpoint.
- Add validation and rate limiting.
- Add database migrations.
- Add tests for API requests.

### Phase 2: Build Flutter Android App

- Create passenger home screen.
- Add Start Trip and End Trip actions.
- Add permission request flow.
- Add active trip screen.
- Add violation report screen.
- Add web links for About, Privacy, Help.

### Phase 3: Add Background Tracking

- Add Android foreground service.
- Add persistent notification.
- Capture GPS every 30 to 60 seconds.
- Stop automatically after 8 hours.
- Send telemetry to Laravel API.
- Queue data locally when offline.

### Phase 4: Monitoring And Officer Visibility

- Show trip telemetry on officer/admin side if required.
- Connect trip violations with the existing report review workflow.
- Add filters for active/completed trips.
- Add map visualization for trip paths if needed.

### Phase 5: Testing

- Test active trip for 8 hours.
- Test app minimized.
- Test screen locked.
- Test internet off/on.
- Test GPS disabled.
- Test permission denied.
- Test battery saver mode.
- Test auto-stop after 8 hours.
- Test manual End Trip.

## 11. What Must Not Be Changed

The following areas should remain stable unless there is a separate approved task:

- Existing Laravel home page UI.
- Existing public report form behavior.
- Existing officer dashboard UI.
- Existing map integration logic.
- Existing about/public pages.

This feature should be added beside the current system, not forced into the current home page.

## 12. Final Recommendation

Use this approach:

```text
Laravel web remains the main public and officer system.
Flutter Android app becomes the passenger trip monitoring tool.
Laravel API connects both sides.
```

This protects the current UI while giving RSRS the native Android capability required for reliable long-trip background tracking.
