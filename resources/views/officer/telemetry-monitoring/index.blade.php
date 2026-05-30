@extends('layouts.officerDashboardLayout')

@section('title', 'Live Vehicle Monitoring')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <style>
        .telemetry-map-shell {
            position: relative;
            height: calc(100vh - 220px);
            min-height: 560px;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid rgba(26, 35, 55, 0.12);
            box-shadow: 0 12px 28px rgba(22, 29, 44, 0.14);
            background: #e9eef5;
        }
        .telemetry-map-canvas {
            width: 100%;
            height: 100%;
        }
        .telemetry-header-stats {
            display: flex;
            align-items: stretch;
            gap: 0.5rem;
            flex-wrap: nowrap;
            justify-content: flex-end;
            white-space: nowrap;
        }
        .telemetry-header-stats__item {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
            gap: 0.1rem;
            min-width: 100px;
            padding: 0.45rem 0.6rem;
            border-radius: 10px;
            border: 1px solid rgba(25, 40, 62, 0.12);
            background: #fff;
            font-size: 0.8rem;
            color: #324761;
        }
        .telemetry-header-stats__item strong {
            font-size: 0.84rem;
            color: #1f3247;
        }
        .telemetry-count-label {
            background: transparent;
            border: 0;
            box-shadow: none;
            color: #fff;
            font-weight: 700;
            font-size: 12px;
            margin: 0;
        }
        @media (max-width: 768px) {
            .telemetry-map-shell {
                height: calc(100vh - 190px);
                min-height: 460px;
            }
            .telemetry-header-stats {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
    </style>
@endpush

@section('page_header_actions')
    <div class="telemetry-header-stats">
        <div class="telemetry-header-stats__item"><span>Total Vehicles</span><strong id="totalVehicles">0</strong></div>
        <div class="telemetry-header-stats__item"><span>Red Alerts</span><strong id="redAlerts">0</strong></div>
        <div class="telemetry-header-stats__item"><span>Last Refresh</span><strong id="lastRefresh">-</strong></div>
    </div>
@endsection

@section('content')
    <div class="container-fluid px-3 px-lg-4 pb-4">
        <div class="telemetry-map-shell">
            <div id="telemetryMap" class="telemetry-map-canvas"></div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (() => {
            const endpoint = @json(route('officer.telemetry-monitoring.live'));
            const segments = @json($segments);
            const map = L.map('telemetryMap', {
                zoomControl: false,
                preferCanvas: true,
            }).setView([-6.7924, 39.2083], 12);

            L.control.zoom({
                position: 'bottomright',
            }).addTo(map);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            const layerSegments = L.layerGroup().addTo(map);
            const layerTelemetry = L.layerGroup().addTo(map);
            const layerTracks = L.layerGroup().addTo(map);

            const extractPolygon = (boundaryCoordinates) => {
                const root = boundaryCoordinates || {};
                return root?.geometry?.coordinates?.[0]
                    || root?.features?.[0]?.geometry?.coordinates?.[0]
                    || root?.coordinates?.[0]
                    || [];
            };

            const drawSegments = () => {
                layerSegments.clearLayers();
                const bounds = [];

                segments.forEach((segment) => {
                    const rawCoordinates = extractPolygon(segment.boundary_coordinates);
                    if (!Array.isArray(rawCoordinates) || rawCoordinates.length < 3) {
                        return;
                    }

                    const latLngs = rawCoordinates
                        .map((pair) => Array.isArray(pair) && pair.length >= 2 ? [Number(pair[1]), Number(pair[0])] : null)
                        .filter((point) => point && Number.isFinite(point[0]) && Number.isFinite(point[1]));

                    if (latLngs.length < 3) {
                        return;
                    }

                    const polygon = L.polygon(latLngs, {
                        color: '#2e5984',
                        weight: 2,
                        fillColor: '#6fa8dc',
                        fillOpacity: 0.2,
                    }).addTo(layerSegments);

                    polygon.bindPopup(`<strong>${segment.segment_name || 'Unnamed segment'}</strong>`);
                    bounds.push(...latLngs);
                });

                if (bounds.length > 0) {
                    map.fitBounds(bounds, {padding: [20, 20]});
                }
            };

            const colorByStatus = (status) => {
                if (status === 'red') return '#b91c1c';
                if (status === 'blue') return '#d97706';
                return '#166534';
            };

            const distanceMeters = (lat1, lon1, lat2, lon2) => {
                const toRad = (deg) => (deg * Math.PI) / 180;
                const earthRadius = 6371000;
                const dLat = toRad(lat2 - lat1);
                const dLon = toRad(lon2 - lon1);
                const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
                    Math.sin(dLon / 2) * Math.sin(dLon / 2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                return earthRadius * c;
            };

            const clusterPointsBy3m = (points) => {
                const clusters = [];

                points.forEach((point) => {
                    let matched = null;
                    for (const cluster of clusters) {
                        const meter = distanceMeters(
                            point.latitude,
                            point.longitude,
                            cluster.center.latitude,
                            cluster.center.longitude
                        );
                        if (meter <= 3) {
                            matched = cluster;
                            break;
                        }
                    }

                    if (!matched) {
                        clusters.push({
                            center: {
                                latitude: point.latitude,
                                longitude: point.longitude,
                            },
                            points: [point],
                        });
                        return;
                    }

                    matched.points.push(point);
                    const count = matched.points.length;
                    matched.center.latitude = matched.points.reduce((sum, p) => sum + p.latitude, 0) / count;
                    matched.center.longitude = matched.points.reduce((sum, p) => sum + p.longitude, 0) / count;
                });

                return clusters;
            };

            const headingLabel = (heading) => {
                if (!Number.isFinite(heading)) return 'Unknown';
                const dirs = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
                const idx = Math.round(heading / 45) % 8;
                return `${dirs[idx]} (${heading.toFixed(0)}°)`;
            };

            const renderTelemetry = (rows, tracks) => {
                layerTelemetry.clearLayers();
                layerTracks.clearLayers();
                let redCount = 0;
                const vehicleIds = new Set();
                const latestPoints = [];

                const safeTracks = Array.isArray(tracks) ? tracks : [];
                safeTracks.forEach((track) => {
                    const pts = Array.isArray(track.points) ? track.points : [];
                    if (pts.length >= 2) {
                        const latLngs = pts
                            .map((p) => [Number(p.latitude), Number(p.longitude)])
                            .filter((p) => Number.isFinite(p[0]) && Number.isFinite(p[1]));

                        if (latLngs.length >= 2) {
                            L.polyline(latLngs, {
                                color: '#1f3247',
                                weight: 2,
                                opacity: 0.45,
                                dashArray: '4,6',
                            }).addTo(layerTracks);
                        }
                    }

                    if (track.latest) {
                        latestPoints.push(track.latest);
                    }
                });

                rows.forEach((row) => {
                    const lat = Number(row.latitude);
                    const lng = Number(row.longitude);
                    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                        return;
                    }

                    vehicleIds.add(row.vehicle_reg_no || row.telemetry_id);
                    if (row.status_color === 'red') {
                        redCount++;
                    }
                });

                const clusters = clusterPointsBy3m(latestPoints);

                clusters.forEach((cluster) => {
                    const count = cluster.points.length;
                    const center = [cluster.center.latitude, cluster.center.longitude];

                    if (count === 1) {
                        const row = cluster.points[0];
                        const color = colorByStatus(row.status_color);
                        const heading = Number(row.heading);
                        const marker = L.circleMarker(center, {
                            radius: 8,
                            color: color,
                            weight: 2,
                            fillColor: color,
                            fillOpacity: 0.92,
                        }).addTo(layerTelemetry);

                        L.circle(center, {
                            radius: 100,
                            color: color,
                            weight: 1,
                            fillColor: color,
                            fillOpacity: 0.12,
                        }).addTo(layerTelemetry);

                        marker.bindPopup(`
                            <div class="roadofficer-hotspot-popup">
                                <h6>${row.vehicle_reg_no || 'Unknown vehicle'}</h6>
                                <p><strong>Status:</strong> ${(row.status_color || 'unknown').toUpperCase()}</p>
                                <p><strong>Speed:</strong> ${Number(row.current_speed || 0).toFixed(2)} km/h</p>
                                <p><strong>Direction:</strong> ${headingLabel(heading)}</p>
                                <p><strong>Segment:</strong> ${row.segment_name || 'Unmapped'}</p>
                                <p><strong>Updated:</strong> ${row.created_at || '-'}</p>
                            </div>
                        `);
                        marker.bindTooltip(`
                            <strong>${row.vehicle_reg_no || 'Unknown vehicle'}</strong><br>
                            ${Number(row.current_speed || 0).toFixed(2)} km/h | ${(row.status_color || 'unknown').toUpperCase()}<br>
                            ${headingLabel(heading)}
                        `, {
                            sticky: true,
                            direction: 'top',
                            opacity: 0.95,
                        });
                        return;
                    }

                    const marker = L.circleMarker(center, {
                        radius: 11,
                        color: '#d97706',
                        weight: 2,
                        fillColor: '#d97706',
                        fillOpacity: 0.92,
                    }).addTo(layerTelemetry);

                    L.circle(center, {
                        radius: Math.min(220, 120 + (count * 6)),
                        color: '#d97706',
                        weight: 1,
                        fillColor: '#d97706',
                        fillOpacity: 0.12,
                    }).addTo(layerTelemetry);

                    const vehicles = cluster.points.map((p) => p.vehicle_reg_no || 'Unknown').join(', ');
                    marker.bindTooltip(String(count), {
                        permanent: true,
                        direction: 'center',
                        className: 'telemetry-count-label',
                    });
                    marker.bindPopup(`<strong>${count} vehicles within 3m</strong><br>${vehicles}`);
                    marker.on('mouseover', () => marker.openPopup());
                    marker.on('mouseout', () => marker.closePopup());
                });

                document.getElementById('totalVehicles').textContent = vehicleIds.size;
                document.getElementById('redAlerts').textContent = redCount;
                document.getElementById('lastRefresh').textContent = new Date().toLocaleTimeString();
            };

            const fetchLiveTelemetry = async () => {
                try {
                    const response = await fetch(endpoint, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const payload = await response.json();
                    renderTelemetry(
                        Array.isArray(payload.data) ? payload.data : [],
                        Array.isArray(payload.tracks) ? payload.tracks : []
                    );
                } catch (_error) {
                    document.getElementById('lastRefresh').textContent = 'Error';
                }
            };

            drawSegments();
            fetchLiveTelemetry();
            setInterval(fetchLiveTelemetry, 10000);
            window.addEventListener('resize', () => map.invalidateSize());
        })();
    </script>
@endsection


