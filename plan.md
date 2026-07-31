# RSRS Reporting Duplicate Prevention Business Logic

## Purpose

This document explains the current business logic that prevents duplicate violation reports after a passenger or driver has already submitted a report for the same monitored rule/location.

The goal of the current logic is to reduce accidental duplicate submissions and report spam when the device stays in the same monitored area after a violation has already been reported.

## Current Duplicate Blocking Rule

After a report is submitted, the system stores a temporary session marker for the detected rule and road segment.

The duplicate marker is based on:

- `segment_id`
- `rule_id`
- authenticated driver id, or `0` for passenger/guest flow

For passengers, the session key format is:

```text
auto_speed.reported.0.{segment_id}.{rule_id}
```

For drivers, the session key format is:

```text
auto_speed.reported.{driver_id}.{segment_id}.{rule_id}
```

This means the system does not block based on exact latitude/longitude coordinates. Instead, it blocks based on the road segment and rule matched from the coordinates.

## Duplicate Window

The current duplicate blocking window is:

```text
600 seconds = 10 minutes
```

During this 10-minute window, if the system detects the same `segment_id + rule_id` again, it treats the event as already reported recently.

After the 10-minute window expires, the same location/rule can trigger a new report flow again if the violation condition is still met.

## Passenger Flow

1. The device sends location, speed, and GPS accuracy to the evaluation endpoint.
2. The backend matches the coordinates to a monitored road segment.
3. The backend resolves the active rule for that segment.
4. If the violation condition is met for the required duration, the backend prepares a pending passenger violation session.
5. The passenger is sent to the report page to add bus identity details.
6. After the passenger submits the report, the system creates:
   - `reports` record
   - `rule_violations` record
   - duplicate session marker for the matched `segment_id + rule_id`
7. If the device detects the same segment/rule again within 10 minutes, the backend returns a recent duplicate state.
8. The frontend shows a "Violation already reported" popup with an `OK` button.

## Driver Flow

1. The authenticated driver device sends location, speed, and GPS accuracy to the evaluation endpoint.
2. The backend matches the same `segment_id + rule_id` pattern.
3. If the violation condition is met for the required duration, a driver pending violation is created.
4. After confirmation/submission, the system creates the report and stores a duplicate session marker using the driver id.
5. If the same driver triggers the same segment/rule again within 10 minutes, the system treats it as a duplicate and returns the existing reference number.

## Why It Happens When Still In The Same Location

If the device remains in the same monitored area, GPS coordinates may change slightly, but the matched road segment can remain the same.

Because the duplicate key is based on `segment_id + rule_id`, the system will still treat it as the same reporting context.

Example:

```text
Latitude/longitude changes slightly
but
matched segment_id = 12
matched rule_id = 5
```

Result:

```text
The system blocks another report for 10 minutes.
```

## Architecture Assessment

This approach is acceptable as a simple temporary duplicate guard, especially for preventing accidental repeated submissions from the same browser session.

However, it is not ideal as the main architecture for a monitoring system.

Limitations:

- It depends on browser/session state, not database-level truth.
- Another browser/device can still submit the same event.
- Passenger reports use `0` as the actor id, so the block is broad within that session.
- It does not consider bus plate number or operator.
- It can suppress valid repeated incidents in the same monitored location.
- It hides monitoring patterns that officers may need to see.

## Monitoring-Oriented Recommendation

Because RSRS is a monitoring system, the system should avoid hard-blocking useful incident data.

Recommended future direction:

1. Allow reports to be submitted even when a similar recent report exists.
2. Store every valid report in the database.
3. Add a database-level similarity check to flag reports as related or repeated.
4. Group similar reports in the officer dashboard by:
   - segment
   - rule
   - time window
   - bus plate number, when available
   - reporter type
5. Show officers clear context such as:

```text
Similar report submitted 4 minutes ago: RPT-20260730-ABC123
```

This keeps monitoring data complete while still helping officers identify repeated or duplicate-looking reports.

## Best Business Rule For RSRS

For monitoring, the preferred rule is:

```text
Do not permanently prevent repeated reports.
Allow valid reports, then flag or group similar recent reports.
```

If hard blocking must remain, it should be narrowed:

- Use a shorter cooldown, such as 30-60 seconds.
- Apply stronger blocking only to automatic driver submissions.
- Allow passenger reports to continue because passengers provide bus identity details.
- Consider `bus_plate_number` before deciding whether a passenger report is a duplicate.

## Current Decision

The current system uses a 10-minute session-based duplicate block.

This is useful for spam prevention but may not be the best long-term architecture for monitoring. For future improvement, duplicate prevention should move from hard blocking to database-backed similarity detection and officer-facing grouping.
