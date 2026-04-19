(function () {
    // Fix for Leaflet default icon paths when using CDN or Proxy
    if (typeof L !== 'undefined' && L.Icon && L.Icon.Default) {
        L.Icon.Default.mergeOptions({
            iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
            iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
            shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        });
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

    function createSelectionIcon() {
        return L.divIcon({
            className: 'geo-map-selection-marker',
            html: '<span class="geo-map-selection-marker__pin"></span>',
            iconSize: [26, 26],
            iconAnchor: [13, 26],
        });
    }

    function createUserLocationIcon() {
        return L.divIcon({
            className: 'geo-map-user-marker',
            html: '<span class="geo-map-user-marker__dot"></span>',
            iconSize: [18, 18],
            iconAnchor: [9, 9],
        });
    }

    function bindMap(root) {
        const config = JSON.parse(root.dataset.mapConfig || '{}');
        const mode = root.dataset.mapMode || 'picker';
        const shell = root.closest('[data-map-shell]');
        const coordinatesLabel = shell?.querySelector('[data-map-coordinates]');
        const recenterButton = shell?.querySelector('[data-map-recenter]');

        const map = L.map(root, {
            zoomControl: false,
            fadeAnimation: true,
            markerZoomAnimation: true,
            preferCanvas: true,
        }).setView(
            [config.defaultCenter.lat, config.defaultCenter.lng],
            config.defaultZoom
        );

        L.control.zoom({ position: 'bottomright' }).addTo(map);
        L.control.scale({
            metric: true,
            imperial: false,
            position: 'bottomleft',
            maxWidth: 140,
        }).addTo(map);

        L.tileLayer(config.tiles.url, {
            minZoom: config.minZoom,
            maxZoom: config.maxZoom,
            attribution: config.tiles.attribution,
            updateWhenIdle: true,
            keepBuffer: 4,
        }).addTo(map);

        let marker = null;
        let userLocationMarker = null;
        let userAccuracyCircle = null;
        let reverseGeocodeController = null;
        let lastReverseGeocodePoint = null;

        function ensureSize() {
            requestAnimationFrame(() => map.invalidateSize());
        }

        map.whenReady(() => {
            ensureSize();

            const readyEventDetail = { map, mode, rootId: root.id || null };
            root.dispatchEvent(new CustomEvent('rsrs:map-ready', { detail: readyEventDetail }));
            document.dispatchEvent(new CustomEvent('rsrs:map-ready', { detail: readyEventDetail }));
        });

        window.addEventListener('resize', ensureSize);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                ensureSize();
            }
        });

        if (typeof ResizeObserver !== 'undefined') {
            const observer = new ResizeObserver(ensureSize);
            observer.observe(root);
        }

        function updateCoordinateText(lat, lng) {
            if (!coordinatesLabel) {
                const coordinatesPanel = document.getElementById('selectedCoordinatesPanel');

                if (coordinatesPanel) {
                    coordinatesPanel.textContent = `${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`;
                }

                return;
            }

            coordinatesLabel.textContent = `${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`;

            const coordinatesPanel = document.getElementById('selectedCoordinatesPanel');

            if (coordinatesPanel) {
                coordinatesPanel.textContent = `${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`;
            }
        }

        async function resolveLocation(lat, lng) {
            if (reverseGeocodeController) {
                reverseGeocodeController.abort();
            }

            reverseGeocodeController = new AbortController();

            const response = await fetch(
                `${config.reverseGeocodeUrl}?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`,
                {
                    headers: {
                        Accept: 'application/json',
                    },
                    signal: reverseGeocodeController.signal,
                }
            );

            if (!response.ok) {
                throw new Error('Failed to reverse geocode the selected point.');
            }

            return response.json();
        }

        async function handleSelection(lat, lng, options = {}) {
            const shouldResolveLocation = options.resolveLocation !== false;

            if (mode !== 'segment-builder') {
                if (!marker) {
                    marker = L.marker([lat, lng], { icon: createSelectionIcon() }).addTo(map);
                } else {
                    marker.setLatLng([lat, lng]);
                }
            }

            updateCoordinateText(lat, lng);

            if (!shouldResolveLocation) {
                map.fire('rsrs:location-resolved', {
                    lat,
                    lng,
                    displayName: null,
                    address: {},
                });
                return;
            }

            const locationTarget = document.getElementById('mapResolvedLocation');

            if (locationTarget) {
                locationTarget.textContent = 'Resolving location name...';
            }

            const point = { lat, lng };
            if (lastReverseGeocodePoint && distanceInMeters(lastReverseGeocodePoint, point) < 12) {
                return;
            }

            lastReverseGeocodePoint = point;

            try {
                const result = await resolveLocation(lat, lng);

                if (locationTarget) {
                    locationTarget.textContent = result.display_name || 'No address description returned.';
                }

                map.fire('rsrs:location-resolved', {
                    lat,
                    lng,
                    displayName: result.display_name || null,
                    address: result.address || {},
                });
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                if (locationTarget) {
                    locationTarget.textContent = 'Location lookup failed. You can still continue with raw coordinates.';
                }

                map.fire('rsrs:location-resolved', {
                    lat,
                    lng,
                    displayName: null,
                    address: {},
                });
            }
        }

        function setUserLocation(lat, lng, options = {}) {
            const accuracy = Number(options.accuracy);

            if (!userLocationMarker) {
                userLocationMarker = L.marker([lat, lng], {
                    icon: createUserLocationIcon(),
                    interactive: false,
                    keyboard: false,
                    zIndexOffset: 1000,
                }).addTo(map);
            } else {
                userLocationMarker.setLatLng([lat, lng]);
            }

            if (Number.isFinite(accuracy) && accuracy > 0) {
                const clampedAccuracy = Math.min(Math.max(accuracy, 6), 150);

                if (!userAccuracyCircle) {
                    userAccuracyCircle = L.circle([lat, lng], {
                        radius: clampedAccuracy,
                        stroke: false,
                        fillColor: '#1f70ff',
                        fillOpacity: 0.16,
                        interactive: false,
                    }).addTo(map);
                } else {
                    userAccuracyCircle.setLatLng([lat, lng]);
                    userAccuracyCircle.setRadius(clampedAccuracy);
                }
            }
        }

        map.on('click', function (event) {
            handleSelection(event.latlng.lat, event.latlng.lng);
            map.fire('rsrs:point-selected', {
                lat: event.latlng.lat,
                lng: event.latlng.lng,
            });
        });

        recenterButton?.addEventListener('click', function () {
            map.setView([config.defaultCenter.lat, config.defaultCenter.lng], config.defaultZoom);
        });

        root.mapApi = {
            map,
            ensureSize,
            selectPoint: handleSelection,
            setUserLocation,
            centerOn(lat, lng, zoom = map.getZoom(), animate = true) {
                map.setView([lat, lng], zoom, { animate });
            },
        };
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-map-root]').forEach(bindMap);
    });
})();
