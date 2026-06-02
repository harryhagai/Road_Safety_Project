// OSRM route matching helpers for road-segment creation.

(function () {
    const namespace = window.RsrsRoadSegments = window.RsrsRoadSegments || {};
    const MAX_SEGMENT_DETOUR_FACTOR = 2.8;
    const MAX_MIDPOINT_DRIFT_METERS = 30;

    function joinMatchedCoordinates(matchings) {
        const merged = [];

        matchings.forEach((matching) => {
            const coords = matching?.geometry?.coordinates;
            if (!Array.isArray(coords) || coords.length === 0) return;

            coords.forEach((coord) => {
                if (!Array.isArray(coord) || coord.length < 2) return;
                const prev = merged[merged.length - 1];
                if (prev && Math.abs(prev[0] - coord[0]) < 1e-7 && Math.abs(prev[1] - coord[1]) < 1e-7) {
                    return;
                }
                merged.push(coord);
            });
        });

        return merged;
    }

    function mergeCoordinates(target, chunk) {
        chunk.forEach((coord) => {
            if (!Array.isArray(coord) || coord.length < 2) return;
            const prev = target[target.length - 1];
            if (prev && Math.abs(prev[0] - coord[0]) < 1e-7 && Math.abs(prev[1] - coord[1]) < 1e-7) {
                return;
            }
            target.push(coord);
        });
    }

    async function fetchPairMatch(start, end) {
        const profile = 'driving';
        const coordinates = `${start.lng},${start.lat};${end.lng},${end.lat}`;

        const tryRadii = [10, 20, 35];
        for (const radius of tryRadii) {
            const matchUrl = `https://router.project-osrm.org/match/v1/${profile}/${coordinates}?overview=full&geometries=geojson&tidy=true&gaps=ignore&radiuses=${radius};${radius}&annotations=false`;
            const matchResponse = await fetch(matchUrl, { method: 'GET' });
            const matchData = await matchResponse.json().catch(() => ({}));
            if (matchResponse.ok && matchData.code === 'Ok' && Array.isArray(matchData.matchings) && matchData.matchings.length > 0) {
                const matchedCoords = joinMatchedCoordinates(matchData.matchings);
                if (matchedCoords.length >= 2) {
                    return matchedCoords;
                }
            }
        }

        const routeUrl = `https://router.project-osrm.org/route/v1/${profile}/${coordinates}?overview=full&geometries=geojson&steps=false&continue_straight=true`;
        const routeResponse = await fetch(routeUrl, { method: 'GET' });
        const routeData = await routeResponse.json().catch(() => ({}));
        if (routeResponse.ok && routeData.code === 'Ok' && Array.isArray(routeData.routes) && routeData.routes[0]?.geometry?.coordinates) {
            return routeData.routes[0].geometry.coordinates;
        }

        return [[start.lng, start.lat], [end.lng, end.lat]];
    }

    function isSegmentGeometryReasonable(start, end, segmentCoords) {
        if (!Array.isArray(segmentCoords) || segmentCoords.length < 2) {
            return false;
        }

        const directMeters = Math.max(1, namespace.distanceMeters(start, end));
        const segmentMeters = namespace.lineLengthKm(segmentCoords) * 1000;
        const detourFactor = segmentMeters / directMeters;
        if (detourFactor > MAX_SEGMENT_DETOUR_FACTOR) {
            return false;
        }

        const midPoint = {
            lat: (start.lat + end.lat) / 2,
            lng: (start.lng + end.lng) / 2,
        };
        const midpointDrift = namespace.pointToLineDistanceMeters(midPoint, segmentCoords);
        return midpointDrift <= MAX_MIDPOINT_DRIFT_METERS;
    }

    async function snapPointToNearestRoad(point) {
        const profile = 'driving';
        const coords = `${point.lng},${point.lat}`;
        const radii = [12, 22, 35];

        for (const radius of radii) {
            const nearestUrl = `https://router.project-osrm.org/nearest/v1/${profile}/${coords}?number=1&radiuses=${radius}`;
            const response = await fetch(nearestUrl, { method: 'GET' });
            const data = await response.json().catch(() => ({}));

            if (!response.ok || data.code !== 'Ok' || !Array.isArray(data.waypoints) || !data.waypoints[0]?.location) {
                continue;
            }

            const snapped = data.waypoints[0].location;
            if (!Array.isArray(snapped) || snapped.length < 2) {
                continue;
            }

            const snappedPoint = { lng: Number(snapped[0]), lat: Number(snapped[1]) };
            if (!Number.isFinite(snappedPoint.lat) || !Number.isFinite(snappedPoint.lng)) {
                continue;
            }

            if (namespace.distanceMeters(point, snappedPoint) <= 45) {
                return snappedPoint;
            }
        }

        return point;
    }

    async function fetchOsrmMatchedOrRoute(points) {
        if (points.length < 2) {
            throw new Error('At least two points are required.');
        }

        const snappedPoints = [];
        for (const point of points) {
            snappedPoints.push(await snapPointToNearestRoad(point));
        }

        const merged = [];
        for (let index = 1; index < snappedPoints.length; index += 1) {
            const start = snappedPoints[index - 1];
            const end = snappedPoints[index];
            const segmentCoords = await fetchPairMatch(start, end);

            if (isSegmentGeometryReasonable(start, end, segmentCoords)) {
                mergeCoordinates(merged, segmentCoords);
            } else {
                mergeCoordinates(merged, [[start.lng, start.lat], [end.lng, end.lat]]);
            }
        }

        if (merged.length < 2) {
            throw new Error('Could not build a road shape from selected points.');
        }

        return merged;
    }

    Object.assign(namespace, {
        fetchOsrmMatchedOrRoute,
    });
})();
