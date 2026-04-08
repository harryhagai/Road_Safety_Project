(function () {
    function bindMap(root) {
        const config = JSON.parse(root.dataset.mapConfig || '{}');
        const mode = root.dataset.mapMode || 'picker';
        const shell = root.closest('[data-map-shell]');
        const coordinatesLabel = shell?.querySelector('[data-map-coordinates]');
        const recenterButton = shell?.querySelector('[data-map-recenter]');

        const map = L.map(root).setView(
            [config.defaultCenter.lat, config.defaultCenter.lng],
            config.defaultZoom
        );

        L.tileLayer(config.tiles.url, {
            minZoom: config.minZoom,
            maxZoom: config.maxZoom,
            attribution: config.tiles.attribution,
        }).addTo(map);

        let marker = null;

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
            const response = await fetch(
                `${config.reverseGeocodeUrl}?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`,
                {
                    headers: {
                        Accept: 'application/json',
                    },
                }
            );

            if (!response.ok) {
                throw new Error('Failed to reverse geocode the selected point.');
            }

            return response.json();
        }

        async function handleSelection(lat, lng) {
            if (mode !== 'segment-builder') {
                if (!marker) {
                    marker = L.marker([lat, lng]).addTo(map);
                } else {
                    marker.setLatLng([lat, lng]);
                }
            }

            updateCoordinateText(lat, lng);

            const locationTarget = document.getElementById('mapResolvedLocation');

            if (locationTarget) {
                locationTarget.textContent = 'Resolving location name...';
            }

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
            selectPoint: handleSelection,
        };
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-map-root]').forEach(bindMap);
    });
})();
