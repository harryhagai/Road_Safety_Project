# RSRS Map Integration Documentation

This document explains how map integration works in the Road Safety Reporting System (RSRS), including the frontend Leaflet maps, backend Laravel endpoints, geocoding APIs, hotspot maps, road segment geometry, and automatic speed reporting.

## 1. Overview

RSRS uses Leaflet.js as the main map library. Map configuration is generated from Laravel config and passed to Blade views through `MapConfigService`.

Main responsibilities:

- Display public and officer maps using OpenStreetMap tiles.
- Allow officers to draw/select road segment coordinates.
- Reverse geocode selected coordinates into readable place names.
- Search locations from a geocoder provider.
- Display hotspot markers and radius circles.
- Track user GPS and speed on the public home map.
- Match user location against active speed rules for automatic overspeed reporting.

Main files:

- `config/map.php`
- `app/Services/MapConfigService.php`
- `app/Http/Controllers/MapController.php`
- `app/Http/Controllers/AutoSpeedReportController.php`
- `app/Http/Controllers/PublicHotspotController.php`
- `app/Http/Controllers/officer/OfficerDashboardController.php`
- `app/Http/Controllers/officer/RoadSegmentController.php`
- `app/Http/Controllers/officer/RoadRuleController.php`
- `resources/views/components/map/canvas.blade.php`
- `resources/views/home.blade.php`
- `resources/views/hotspots/index.blade.php`
- `resources/views/officer/dashboard.blade.php`
- `public/js/rsrsMapPicker.js`
- `public/js/rsrsHomeMap.js`
- `public/js/rsrsOfficerDashboard.js`

## 2. Map Configuration

Map settings live in `config/map.php`.

Important config values:

| Config key | Purpose |
| --- | --- |
| `map.default_center.lat` | Default map latitude. |
| `map.default_center.lng` | Default map longitude. |
| `map.default_zoom` | Initial zoom level. |
| `map.min_zoom` | Minimum allowed zoom. |
| `map.max_zoom` | Maximum allowed zoom. |
| `map.tiles.url` | Tile provider URL, currently OpenStreetMap by default. |
| `map.tiles.attribution` | Tile provider attribution. |
| `map.geocoder.provider` | Main geocoder provider label. |
| `map.geocoder.base_url` | Nominatim base URL for reverse geocode/search fallback. |
| `map.geocoder.autocomplete.provider` | Primary search/autocomplete provider. |
| `map.geocoder.autocomplete.api_key` | LocationIQ API key when using LocationIQ. |
| `map.geocoder.autocomplete.countrycodes` | Country filter, default `tz`. |

Example `.env` values:

```env
MAP_DEFAULT_LAT=-6.7924
MAP_DEFAULT_LNG=39.2083
MAP_DEFAULT_ZOOM=12
MAP_TILE_URL=https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png
MAP_GEOCODER_PROVIDER=locationiq
MAP_GEOCODER_BASE_URL=https://nominatim.openstreetmap.org
MAP_AUTOCOMPLETE_PROVIDER=locationiq
MAP_AUTOCOMPLETE_BASE_URL=https://api.locationiq.com/v1
MAP_AUTOCOMPLETE_API_KEY=your_locationiq_key
MAP_AUTOCOMPLETE_COUNTRYCODES=tz
```

## 3. MapConfigService

File: `app/Services/MapConfigService.php`

`MapConfigService::forFrontend()` converts Laravel config into a frontend-safe payload.

Payload shape:

```json
{
  "defaultCenter": {
    "lat": -6.7924,
    "lng": 39.2083
  },
  "defaultZoom": 12,
  "minZoom": 5,
  "maxZoom": 19,
  "tiles": {
    "url": "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
    "attribution": "OpenStreetMap attribution"
  },
  "reverseGeocodeUrl": "/maps/reverse-geocode",
  "searchUrl": "/maps/search",
  "provider": "locationiq"
}
```

This payload is passed to Blade views as `$mapConfig` and then encoded into HTML/JavaScript using `@json`.

## 4. Reusable Map Component

File: `resources/views/components/map/canvas.blade.php`

This Blade component renders the reusable map container.

Usage example:

```blade
<x-map.canvas
    id="mainPublicMap"
    :config="$mapConfig"
    height="100%"
    :show-toolbar="false"
    mode="viewer"
/>
```

Important attributes:

| Attribute | Purpose |
| --- | --- |
| `id` | DOM ID for the Leaflet map container. |
| `config` | Map config payload from `MapConfigService`. |
| `height` | CSS height for the map. |
| `mode` | `picker` or `viewer`. |
| `showToolbar` | Shows/hides coordinate toolbar. |

The component writes:

```html
data-map-root
data-map-mode="picker|viewer"
data-map-config="{...}"
```

`public/js/rsrsMapPicker.js` reads those attributes and initializes Leaflet.

## 5. Core Frontend Map Logic

File: `public/js/rsrsMapPicker.js`

This script initializes all elements with `data-map-root`.

Main responsibilities:

- Creates a Leaflet map using `L.map(...)`.
- Adds tile layer from `config.tiles.url`.
- Adds zoom and scale controls.
- Handles click selection on map.
- Shows selected coordinates.
- Calls reverse geocoding endpoint after coordinate selection.
- Exposes `root.mapApi` for other scripts.
- Dispatches `rsrs:map-ready` event when map is ready.

Available `root.mapApi` methods:

| Method | Purpose |
| --- | --- |
| `map` | Raw Leaflet map instance. |
| `config` | Map config payload. |
| `ensureSize()` | Calls `map.invalidateSize()` safely. |
| `selectPoint(lat, lng, options)` | Selects or updates a coordinate point. |
| `setUserLocation(lat, lng, options)` | Shows current user location and accuracy circle. |
| `previewLocation(lat, lng, options)` | Shows a preview/search marker and flies to it. |
| `clearPreviewLocation()` | Removes preview marker. |
| `centerOn(lat, lng, zoom, animate)` | Moves map to a coordinate. |

Important frontend events:

| Event | Fired by | Purpose |
| --- | --- | --- |
| `rsrs:map-ready` | `rsrsMapPicker.js` | Tells the page that Leaflet map is initialized. |
| `rsrs:point-selected` | Leaflet map click | Sends selected `lat` and `lng`. |
| `rsrs:location-resolved` | Reverse geocode result | Sends coordinate plus readable address if available. |

## 6. Backend Map APIs

Map API routes are defined in `routes/web.php`.

### 6.1 Reverse Geocode API

Route:

```php
GET /maps/reverse-geocode
name: maps.reverse-geocode
middleware: throttle:30,1
controller: MapController@reverseGeocode
```

Purpose:

Converts latitude and longitude into a readable location name using the configured geocoder.

Request query:

| Field | Type | Validation | Required |
| --- | --- | --- | --- |
| `lat` | number | `between:-90,90` | yes |
| `lng` | number | `between:-180,180` | yes |

Example request:

```http
GET /maps/reverse-geocode?lat=-6.7924&lng=39.2083
Accept: application/json
```

Success response:

```json
{
  "display_name": "Dar es Salaam, Tanzania",
  "address": {},
  "lat": "-6.7924",
  "lng": "39.2083",
  "provider": "locationiq"
}
```

Fallback response when geocoder cannot be reached:

```json
{
  "display_name": null,
  "address": [],
  "lat": -6.7924,
  "lng": 39.2083,
  "provider": "locationiq",
  "message": "Reverse geocoding service could not be reached from this environment."
}
```

Internal flow:

1. Validate `lat` and `lng`.
2. Build geocoder URL from `config('map.geocoder.base_url')`.
3. Send HTTP GET to `/reverse`.
4. Return normalized JSON to frontend.
5. If remote service fails, return a graceful JSON response instead of crashing the page.

### 6.2 Location Search API

Route:

```php
GET /maps/search
name: maps.search
middleware: throttle:60,1
controller: MapController@search
```

Purpose:

Searches places by text and returns coordinates. Used for map search/autocomplete flows.

Request query:

| Field | Type | Validation | Required |
| --- | --- | --- | --- |
| `query` | string | `min:3`, `max:255` | yes |

Example request:

```http
GET /maps/search?query=Kariakoo
Accept: application/json
```

Response:

```json
{
  "query": "Kariakoo",
  "results": [
    {
      "label": "Kariakoo",
      "subtitle": "Dar es Salaam, Tanzania",
      "lat": -6.823,
      "lng": 39.275,
      "type": "suburb",
      "provider": "locationiq"
    }
  ],
  "provider": "locationiq",
  "message": null
}
```

Search provider flow:

1. Primary provider comes from `MAP_AUTOCOMPLETE_PROVIDER`.
2. If provider is `locationiq`, the system calls LocationIQ `/autocomplete`.
3. If primary provider fails or has no usable results, it falls back to Nominatim `/search`.
4. Results are normalized into `label`, `subtitle`, `lat`, `lng`, `type`, and `provider`.
5. Results are cached using key `map-search:{provider}:{query_hash}`.

Cache behavior:

- Successful results use `MAP_GEOCODER_CACHE_TTL_MINUTES`, default `15`.
- Empty/unavailable results are cached for `5` minutes.

## 7. Road Segment Map Integration

Files:

- `app/Http/Controllers/officer/RoadSegmentController.php`
- `resources/views/officer/road-segments/index.blade.php`
- `public/js/rsrsRoadSegments.js`

Purpose:

Officers define road segments using map geometry. Each segment stores its boundary as JSON.

Backend flow:

1. `RoadSegmentController@index()` sends `$mapConfig`, existing segments, and active segment types to the view.
2. Frontend allows the officer to draw/select segment geometry.
3. Form submits `boundary_coordinates` as JSON.
4. `RoadSegmentController@store()` validates:
   - `segment_name`
   - optional `segment_type_id`
   - optional `description`
   - optional `length_km`
   - required `boundary_coordinates`
5. Controller decodes JSON and checks that the segment has at least two points.
6. Segment is saved in `road_segments.boundary_coordinates`.

Expected geometry style:

```json
{
  "type": "Feature",
  "geometry": {
    "type": "LineString",
    "coordinates": [
      [39.2083, -6.7924],
      [39.2183, -6.8024]
    ]
  }
}
```

Important note:

GeoJSON stores coordinates as `[lng, lat]`, not `[lat, lng]`.

## 8. Road Rule Map Integration

Files:

- `app/Http/Controllers/officer/RoadRuleController.php`
- `resources/views/officer/road-rules/index.blade.php`
- `public/js/rsrsRoadRules.js`

Purpose:

Road rules are attached to road segments. Speed limit rules are later used by automatic speed reporting.

Important endpoints:

### Road Rule Page

```php
GET /road-officer/road-rules
name: officer.road-rules.index
middleware: auth
```

Loads first page of road segments and their rules.

### Road Rule Data API

```php
GET /road-officer/road-rules/data
name: officer.road-rules.data
middleware: auth
```

Query params:

| Field | Purpose |
| --- | --- |
| `search` | Filters segments/rules by name/type/value/location. |
| `page` | Pagination page. |

Response shape:

```json
{
  "items": [
    {
      "id": 1,
      "segment_name": "Morogoro Road",
      "segment_type": "Highway",
      "description": null,
      "length_km": "2.50",
      "boundary_coordinates": {},
      "road_rules_count": 1,
      "rules": []
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 10,
    "total": 12,
    "has_more": true,
    "search": ""
  }
}
```

When storing a road rule:

- The selected `segment_id` is required.
- Controller reads the segment geometry.
- It extracts first and last coordinates.
- It stores them as `latitude_start`, `longitude_start`, `latitude_end`, `longitude_end`.
- The full segment geometry remains in `road_segments.boundary_coordinates`.

## 9. Public Home Map and GPS Speed Integration

Files:

- `resources/views/home.blade.php`
- `public/js/rsrsMapPicker.js`
- `public/js/rsrsHomeMap.js`
- `app/Http/Controllers/AutoSpeedReportController.php`

Purpose:

The home page shows a live map, tracks user GPS location, calculates speed, evaluates active speed limit rules, and can submit an automatic overspeeding report.

Blade setup:

```blade
<x-map.canvas
    id="mainPublicMap"
    :config="$mapConfig"
    height="100%"
    :show-toolbar="false"
    mode="viewer"
/>
```

JavaScript config:

```js
window.rsrsAutoSpeedReporting = {
    evaluateUrl: "/auto-speed-reports/evaluate",
    storeUrl: "/auto-speed-reports",
    csrfToken: "csrf-token"
};
```

Frontend flow:

1. `rsrsMapPicker.js` initializes `mainPublicMap`.
2. It dispatches `rsrs:map-ready`.
3. `rsrsHomeMap.js` waits for map readiness.
4. Browser GPS starts through `navigator.geolocation`.
5. Current location marker and accuracy circle are displayed.
6. Speed is calculated from browser GPS speed or distance/time between points.
7. Every few seconds, telemetry is sent to `/auto-speed-reports/evaluate`.
8. If speed stays above the matched limit for 30 seconds, `/auto-speed-reports` is called.

## 10. Automatic Speed Reporting APIs

### 10.1 Evaluate Speed Rule API

Route:

```php
POST /auto-speed-reports/evaluate
name: auto-speed-reports.evaluate
middleware: throttle:180,1
controller: AutoSpeedReportController@evaluate
```

Purpose:

Checks whether the user's GPS location matches an active speed limit rule and whether current speed exceeds the limit.

Request body:

```json
{
  "latitude": -6.7924,
  "longitude": 39.2083,
  "speed_kmh": 72.5,
  "accuracy": 20,
  "heading": 90
}
```

Validation:

| Field | Validation |
| --- | --- |
| `latitude` | required, numeric, between `-90` and `90` |
| `longitude` | required, numeric, between `-180` and `180` |
| `speed_kmh` | required, numeric, min `0`, max `320` |
| `accuracy` | nullable, numeric, min `0`, max `1000` |
| `heading` | nullable, numeric, min `0`, max `360` |

Matched response:

```json
{
  "matched": true,
  "exceeded": true,
  "can_submit": false,
  "exceeded_seconds": 12,
  "required_seconds": 30,
  "distance_meters": 18.4,
  "speed_kmh": 72.5,
  "speed_limit_kmh": 50,
  "segment": {
    "id": 1,
    "name": "Morogoro Road"
  },
  "rule": {
    "id": 3,
    "name": "Morogoro Road - Speed Limit - 50",
    "value": "50"
  }
}
```

No match response:

```json
{
  "matched": false,
  "message": "No monitored speed segment nearby."
}
```

Matching logic:

- Only active rules where `rule_type = speed_limit` are considered.
- Rule must be inside effective date range if dates exist.
- User coordinate is compared against the road segment polyline.
- Base tolerance is `80m`.
- Maximum tolerance is `350m`.
- GPS accuracy increases tolerance up to max.
- If speed is above rule limit, session stores when violation started.
- User must remain above limit for `30` seconds before report can be submitted.

### 10.2 Store Automatic Speed Report API

Route:

```php
POST /auto-speed-reports
name: auto-speed-reports.store
middleware: throttle:12,1
controller: AutoSpeedReportController@store
```

Purpose:

Creates a report after speed has exceeded the limit for the required duration.

Request body:

```json
{
  "latitude": -6.7924,
  "longitude": 39.2083,
  "speed_kmh": 82.1,
  "accuracy": 18,
  "heading": 90,
  "rule_id": 3,
  "segment_id": 1
}
```

Success response:

```json
{
  "reported": true,
  "duplicate": false,
  "reference_no": "RPT-20260520-ABC123"
}
```

Duplicate response:

```json
{
  "reported": true,
  "duplicate": true,
  "reference_no": "RPT-20260520-ABC123"
}
```

Possible conflict responses:

| Reason | Meaning |
| --- | --- |
| `rule_mismatch` | Current location no longer matches submitted rule/segment. |
| `speed_within_limit` | Speed dropped below limit before report submission. |
| `duration_pending` | Speed has not exceeded limit for the required 30 seconds. |

Store logic:

1. Validate telemetry plus `rule_id` and `segment_id`.
2. Re-match current location to avoid stale frontend data.
3. Confirm current speed is still above limit.
4. Confirm exceeded duration is at least 30 seconds.
5. Prevent duplicate report for same rule within 600 seconds.
6. Create or reuse violation type `Overspeeding`.
7. Create `reports` row.
8. Create `rule_violations` row with automatic match.
9. Return report reference number.

## 11. Public Hotspot Map

Files:

- `app/Http/Controllers/PublicHotspotController.php`
- `resources/views/hotspots/index.blade.php`

Route:

```php
GET /hotspots
name: hotspots.index
```

Purpose:

Displays public hotspot locations using Leaflet markers and radius circles.

Controller payload:

```json
{
  "id": 1,
  "name": "Kariakoo Junction",
  "lat": -6.823,
  "lng": 39.275,
  "radius": 100,
  "frequency": 5,
  "severity": "high",
  "rule": "Speed Limit 50",
  "updated": "20 May 2026, 14:30"
}
```

Map behavior:

- Uses `$mapConfig` for default center, zoom, and tile layer.
- Adds `L.circleMarker` for hotspot center.
- Adds `L.circle` to show affected radius.
- Fits map bounds around all hotspots.
- Clicking "View on map" flies to the selected hotspot and opens popup.

## 12. Officer Dashboard Hotspot Map

Files:

- `app/Http/Controllers/officer/OfficerDashboardController.php`
- `resources/views/officer/dashboard.blade.php`
- `public/js/rsrsOfficerDashboard.js`

Purpose:

Shows latest hotspot locations inside the officer dashboard.

Blade passes data to JS:

```js
window.rsrsOfficerDashboardMap = {
    mapConfig: {...},
    hotspots: [...]
};
```

Frontend behavior:

- `rsrsOfficerDashboard.js` reads `window.rsrsOfficerDashboardMap`.
- Initializes Leaflet on `#officerHotspotsMap`.
- Adds tile layer.
- Adds colored markers by severity.
- Adds radius circles.
- Fits bounds to available hotspot markers.
- `data-hotspot-focus="{id}"` buttons fly to marker and open popup.

Severity colors:

| Severity | Color |
| --- | --- |
| `critical` | dark red |
| `high` | red |
| `medium` | orange |
| `low` | green |

## 13. Data Model Relationships Used by Maps

### RoadSegment

Important fields:

| Field | Purpose |
| --- | --- |
| `segment_name` | Human-readable road segment name. |
| `segment_type_id` | Linked segment category. |
| `length_km` | Optional segment length. |
| `boundary_coordinates` | GeoJSON-like geometry used by maps and speed matching. |

### RoadRule

Important fields:

| Field | Purpose |
| --- | --- |
| `rule_type` | Example: `speed_limit`. |
| `rule_value` | Example: `50`. |
| `segment_id` | Links rule to road segment. |
| `latitude_start` / `longitude_start` | First coordinate of segment. |
| `latitude_end` / `longitude_end` | Last coordinate of segment. |
| `is_active` | Only active rules are evaluated. |

### Hotspot

Important fields:

| Field | Purpose |
| --- | --- |
| `name` | Hotspot display name. |
| `latitude` | Marker latitude. |
| `longitude` | Marker longitude. |
| `radius_meters` | Circle radius shown on map. |
| `frequency` | Incident frequency/count indicator. |
| `severity` | `critical`, `high`, `medium`, or `low`. |
| `rule_id` | Optional linked rule. |

### Report

Important fields for maps:

| Field | Purpose |
| --- | --- |
| `latitude` | Report location latitude. |
| `longitude` | Report location longitude. |
| `location_name` | Resolved or matched location name. |
| `priority` | Set based on speed severity in auto reports. |
| `reported_at` | Timestamp for incident report. |

## 14. External Services

### Leaflet

Leaflet CSS and JS are loaded from CDN in pages that need maps.

```html
https://unpkg.com/leaflet@1.9.4/dist/leaflet.css
https://unpkg.com/leaflet@1.9.4/dist/leaflet.js
```

### OpenStreetMap tiles

Default tile URL:

```text
https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png
```

### Nominatim

Used for:

- Reverse geocoding.
- Search fallback.

Default base URL:

```text
https://nominatim.openstreetmap.org
```

### LocationIQ

Used as primary autocomplete provider when API key is configured.

Default base URL:

```text
https://api.locationiq.com/v1
```

Required env:

```env
MAP_AUTOCOMPLETE_API_KEY=your_locationiq_key
```

## 15. Security, Rate Limits, and Reliability

Rate limits:

| Endpoint | Limit |
| --- | --- |
| `/maps/reverse-geocode` | `30` requests per minute |
| `/maps/search` | `60` requests per minute |
| `/auto-speed-reports/evaluate` | `180` requests per minute |
| `/auto-speed-reports` | `12` requests per minute |

Reliability behavior:

- Reverse geocode failures return JSON with `display_name: null`.
- Search uses fallback provider when primary provider fails.
- Search results are cached.
- Auto report store re-validates rule/segment match before creating a report.
- Duplicate auto reports are blocked for 600 seconds per rule/session.

## 16. Presentation Summary

For presentation, explain the map integration in this order:

1. `config/map.php` stores map provider settings.
2. `MapConfigService` sends safe map config to Blade.
3. Blade renders `<x-map.canvas>` or a hotspot map container.
4. Leaflet initializes map on the frontend.
5. `MapController` provides reverse geocode and location search APIs.
6. Road segments save GeoJSON-like geometry.
7. Road rules attach speed limits to road segments.
8. Home page GPS checks user location and speed.
9. Auto speed APIs match location to rules and create reports.
10. Hotspot maps display dangerous areas using markers and radius circles.
