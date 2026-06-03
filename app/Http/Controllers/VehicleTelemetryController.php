<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\RoadSegment;
use App\Models\RuleViolation;
use App\Models\VehicleTelemetry;
use App\Models\ViolationType;
use App\Services\SegmentRuleResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VehicleTelemetryController extends Controller
{
    public function __construct(private readonly SegmentRuleResolver $segmentRuleResolver) {}

    private const DEFAULT_COUNTRY_SPEED_LIMIT = 80.0;
    private const STATIONARY_SPEED_THRESHOLD_KMH = 1.0;
    private const NO_PARKING_SPEED_THRESHOLD_KMH = 1.0;
    private const REQUIRED_NO_PARKING_SECONDS = 30;
    private const NO_PARKING_MAX_HEARTBEAT_GAP_SECONDS = 75;
    private const NO_PARKING_STATE_TTL_SECONDS = 180;
    private const MIN_OVERSPEED_MARGIN_KMH = 3.0;
    private const TELEMETRY_REPORT_COOLDOWN_SECONDS = 600;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'citizen_device_no' => ['nullable', 'string', 'max:60'],
            'vehicle_reg_no' => ['nullable', 'string', 'max:60'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'current_speed' => ['nullable', 'numeric', 'min:0', 'max:320'],
            'heading' => ['nullable', 'numeric', 'min:0', 'max:360'],
        ]);
        $deviceNo = trim((string) ($validated['citizen_device_no'] ?? $validated['vehicle_reg_no'] ?? ''));
        if ($deviceNo === '') {
            return response()->json([
                'saved' => false,
                'message' => 'citizen_device_no is required.',
            ], 422);
        }

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];
        $hasSpeedMeasurement = array_key_exists('current_speed', $validated) && $validated['current_speed'] !== null;
        $speed = $this->normalizeSpeedKmh($hasSpeedMeasurement ? (float) $validated['current_speed'] : 0.0);
        $heading = isset($validated['heading']) ? round((float) $validated['heading'], 2) : null;

        $latestForVehicle = VehicleTelemetry::query()
            ->where('citizen_device_no', $deviceNo)
            ->latest('telemetry_id')
            ->first();

        $coordinateUnchanged = $latestForVehicle &&
            round((float) $latestForVehicle->latitude, 6) === round($latitude, 6) &&
            round((float) $latestForVehicle->longitude, 6) === round($longitude, 6);
        $speedUnchanged = $latestForVehicle && round((float) $latestForVehicle->current_speed, 2) === round($speed, 2);

        $segment = $this->resolveSegmentBySpatialLookup($latitude, $longitude);
        $speedRule = $segment ? $this->segmentRuleResolver->resolveSpeedLimitRuleForSegment($segment) : null;
        $noParkingRule = $segment ? $this->segmentRuleResolver->resolveNoParkingRuleForSegment($segment) : null;
        $speedLimit = $this->resolveSpeedLimitFromRule($speedRule);
        $hasNoParkingViolation = $hasSpeedMeasurement && $this->shouldCreateNoParkingReport($speed, $noParkingRule);
        $statusColor = $hasNoParkingViolation ? 'red' : $this->resolveStatusColor($speed, $speedLimit);

        if ($coordinateUnchanged && $speedUnchanged && ! $hasNoParkingViolation) {
            return response()->json([
                'saved' => false,
                'unchanged_coordinate' => true,
                'message' => 'Coordinate and speed unchanged, telemetry skipped.',
                'telemetry_id' => $latestForVehicle->telemetry_id,
            ]);
        }

        $telemetry = VehicleTelemetry::create([
            'citizen_device_no' => $deviceNo,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current_speed' => $speed,
            'heading' => $heading,
            'segment_id' => $segment?->id,
        ]);

        $reportReference = null;
        $reportCreated = false;
        $reportDuplicate = false;
        $noParkingState = null;
        $ruleAlert = $statusColor === 'red' ? 'speed_limit' : null;

        if ($hasNoParkingViolation) {
            $noParkingState = $this->evaluateNoParkingReportState(
                $deviceNo,
                (int) $segment->id,
                (int) $noParkingRule['segment_type_rule_id']
            );
            $ruleAlert = $noParkingState['can_report'] ? 'no_parking' : 'no_parking_pending';

            if ($noParkingState['can_report']) {
                $reportResult = $this->createNoParkingReportFromTelemetry($telemetry, $segment, $noParkingRule);
                $reportReference = $reportResult['reference_no'];
                $reportCreated = $reportResult['created'];
                $reportDuplicate = $reportResult['duplicate'];
            }
        } elseif ($noParkingRule) {
            $this->clearNoParkingReportState(
                $deviceNo,
                (int) $segment->id,
                (int) $noParkingRule['segment_type_rule_id']
            );
        } elseif ($statusColor === 'red') {
            $reportReference = $this->createViolationReportFromTelemetry($telemetry, $segment, $speedLimit, $speedRule);
        }

        return response()->json([
            'saved' => true,
            'telemetry_id' => $telemetry->telemetry_id,
            'citizen_device_no' => $telemetry->citizen_device_no,
            'vehicle_reg_no' => $telemetry->citizen_device_no,
            'status_color' => $statusColor,
            'speed_limit' => $speedLimit,
            'speed_measured' => $hasSpeedMeasurement,
            'segment' => $segment?->segment_name,
            'rule_alert' => $ruleAlert,
            'report_created' => $reportCreated,
            'report_duplicate' => $reportDuplicate,
            'report_reference_no' => $reportReference,
            'no_parking' => $noParkingState ? [
                'elapsed_seconds' => $noParkingState['elapsed_seconds'],
                'remaining_seconds' => $noParkingState['remaining_seconds'],
                'required_seconds' => self::REQUIRED_NO_PARKING_SECONDS,
                'can_report' => $noParkingState['can_report'],
            ] : null,
        ]);
    }

    public function live(): JsonResponse
    {
        $rows = VehicleTelemetry::query()
            ->with('segment:id,segment_name')
            ->latest('telemetry_id')
            ->limit(700)
            ->get();
        $segmentIds = $rows->pluck('segment_id')->filter()->unique()->map(fn ($value) => (int) $value)->all();
        $speedLimitsBySegment = $this->speedLimitsBySegmentIds($segmentIds);
        $noParkingRulesBySegment = $this->noParkingRulesBySegmentIds($segmentIds);

        $mappedRows = $rows->map(fn (VehicleTelemetry $item): array => [
                'telemetry_id' => $item->telemetry_id,
                'citizen_device_no' => $item->citizen_device_no,
                'vehicle_reg_no' => $item->citizen_device_no,
                'latitude' => (float) $item->latitude,
                'longitude' => (float) $item->longitude,
                'current_speed' => (float) $item->current_speed,
                'heading' => $item->heading !== null ? (float) $item->heading : null,
                'status_color' => $this->resolveTelemetryStatusColor(
                    (float) $item->current_speed,
                    $item->segment_id ? (int) $item->segment_id : null,
                    $speedLimitsBySegment,
                    $noParkingRulesBySegment
                ),
                'segment_name' => $item->segment?->segment_name,
                'created_at' => optional($item->created_at)->toDateTimeString(),
            ]);

        $tracks = $rows
            ->groupBy('citizen_device_no')
            ->map(function ($items, $vehicleRegNo) use ($speedLimitsBySegment, $noParkingRulesBySegment): array {
                $ordered = $items->sortBy('telemetry_id')->values();
                $points = $ordered->map(fn (VehicleTelemetry $item): array => [
                    'latitude' => (float) $item->latitude,
                    'longitude' => (float) $item->longitude,
                    'heading' => $item->heading !== null ? (float) $item->heading : null,
                    'status_color' => $this->resolveTelemetryStatusColor(
                        (float) $item->current_speed,
                        $item->segment_id ? (int) $item->segment_id : null,
                        $speedLimitsBySegment,
                        $noParkingRulesBySegment
                    ),
                    'created_at' => optional($item->created_at)->toDateTimeString(),
                ])->all();

                $latest = $ordered->last();

                return [
                    'citizen_device_no' => (string) $vehicleRegNo,
                    'vehicle_reg_no' => (string) $vehicleRegNo,
                    'points' => $points,
                    'latest' => $latest ? [
                        'telemetry_id' => $latest->telemetry_id,
                        'citizen_device_no' => $latest->citizen_device_no,
                        'vehicle_reg_no' => $latest->citizen_device_no,
                        'latitude' => (float) $latest->latitude,
                        'longitude' => (float) $latest->longitude,
                        'current_speed' => (float) $latest->current_speed,
                        'heading' => $latest->heading !== null ? (float) $latest->heading : null,
                        'status_color' => $this->resolveTelemetryStatusColor(
                            (float) $latest->current_speed,
                            $latest->segment_id ? (int) $latest->segment_id : null,
                            $speedLimitsBySegment,
                            $noParkingRulesBySegment
                        ),
                        'segment_name' => $latest->segment?->segment_name,
                        'created_at' => optional($latest->created_at)->toDateTimeString(),
                    ] : null,
                ];
            })
            ->values();

        return response()->json([
            'data' => $mappedRows,
            'tracks' => $tracks,
        ]);
    }

    private function resolveSegmentBySpatialLookup(float $latitude, float $longitude): ?RoadSegment
    {
        $candidate = null;
        $nearestDistance = INF;

        foreach (
            RoadSegment::query()
                ->whereNotNull('boundary_coordinates')
                ->with('segmentType:id,name')
                ->get() as $segment
        ) {
            $distance = $this->distanceToSegmentGeometryMeters($latitude, $longitude, $segment->boundary_coordinates ?? []);
            $matchBuffer = $this->segmentMatchBufferMeters($segment);

            if ($distance <= $matchBuffer && $distance < $nearestDistance) {
                $nearestDistance = $distance;
                $candidate = $segment;
            }
        }

        return $candidate;
    }

    private function segmentMatchBufferMeters(RoadSegment $segment): float
    {
        $type = strtolower(trim((string) ($segment->segment_type_name ?? '')));

        if (str_contains($type, 'highway') || str_contains($type, 'trunk') || str_contains($type, 'junction')) {
            return 60.0;
        }

        if (str_contains($type, 'primary')) {
            return 50.0;
        }

        if (str_contains($type, 'secondary') || str_contains($type, 'arterial')) {
            return 40.0;
        }

        if (str_contains($type, 'residential') || str_contains($type, 'local')) {
            return 30.0;
        }

        return 40.0;
    }

    private function distanceToSegmentGeometryMeters(float $latitude, float $longitude, array $geometry): float
    {
        $rawCoordinates = data_get($geometry, 'geometry.coordinates')
            ?? data_get($geometry, 'features.0.geometry.coordinates')
            ?? data_get($geometry, 'coordinates')
            ?? $geometry;
        $geometryType = strtolower((string) (
            data_get($geometry, 'geometry.type')
            ?? data_get($geometry, 'features.0.geometry.type')
            ?? data_get($geometry, 'type')
            ?? ''
        ));

        if (! is_array($rawCoordinates) || $rawCoordinates === []) {
            return INF;
        }

        $target = ['lat' => $latitude, 'lng' => $longitude];
        $minimum = INF;

        foreach ($this->coordinateLines($rawCoordinates) as $linePoints) {
            if (str_contains($geometryType, 'polygon') && count($linePoints) >= 3 && $this->pointInsideNormalizedPolygon($target, $linePoints)) {
                return 0.0;
            }

            if (count($linePoints) < 2) {
                continue;
            }

            $minimum = min($minimum, $this->distanceToNormalizedPolylineMeters($target, $linePoints));
        }

        return $minimum;
    }

    private function coordinateLines(array $coordinates): array
    {
        if ($this->isCoordinatePair($coordinates)) {
            $point = $this->normalizeGeometryCoordinate($coordinates);

            return $point ? [[$point]] : [];
        }

        $looksLikeLine = $coordinates !== [] && collect($coordinates)
            ->every(fn ($coordinate): bool => $this->isCoordinatePair($coordinate));

        if ($looksLikeLine) {
            $line = collect($coordinates)
                ->map(fn ($coordinate) => $this->normalizeGeometryCoordinate($coordinate))
                ->filter(fn (?array $point): bool => $point !== null)
                ->values()
                ->all();

            return $line !== [] ? [$line] : [];
        }

        $lines = [];

        foreach ($coordinates as $coordinateGroup) {
            if (is_array($coordinateGroup)) {
                array_push($lines, ...$this->coordinateLines($coordinateGroup));
            }
        }

        return $lines;
    }

    private function isCoordinatePair(mixed $coordinate): bool
    {
        if (! is_array($coordinate)) {
            return false;
        }

        if (isset($coordinate['lat'], $coordinate['lng']) || isset($coordinate['latitude'], $coordinate['longitude'])) {
            return true;
        }

        $values = array_values($coordinate);

        return count($values) >= 2
            && is_numeric($values[0])
            && is_numeric($values[1]);
    }

    private function normalizeGeometryCoordinate(array $coordinate): ?array
    {
        if (isset($coordinate['lat'], $coordinate['lng'])) {
            return $this->validGeometryPoint((float) $coordinate['lat'], (float) $coordinate['lng']);
        }

        if (isset($coordinate['latitude'], $coordinate['longitude'])) {
            return $this->validGeometryPoint((float) $coordinate['latitude'], (float) $coordinate['longitude']);
        }

        $values = array_values($coordinate);
        if (count($values) < 2 || ! is_numeric($values[0]) || ! is_numeric($values[1])) {
            return null;
        }

        $first = (float) $values[0];
        $second = (float) $values[1];

        if (abs($first) <= 20 && abs($second) > 20) {
            return $this->validGeometryPoint($first, $second);
        }

        return $this->validGeometryPoint($second, $first);
    }

    private function validGeometryPoint(float $latitude, float $longitude): ?array
    {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        return [
            'lat' => $latitude,
            'lng' => $longitude,
        ];
    }

    private function pointInsideNormalizedPolygon(array $point, array $polygonPoints): bool
    {
        if (count($polygonPoints) < 3) {
            return false;
        }

        $inside = false;
        $total = count($polygonPoints);

        for ($i = 0, $j = $total - 1; $i < $total; $j = $i++) {
            $xi = (float) $polygonPoints[$i]['lng'];
            $yi = (float) $polygonPoints[$i]['lat'];
            $xj = (float) $polygonPoints[$j]['lng'];
            $yj = (float) $polygonPoints[$j]['lat'];

            $crosses = (($yi > $point['lat']) !== ($yj > $point['lat']))
                && ($point['lng'] < (($xj - $xi) * ($point['lat'] - $yi) / (($yj - $yi) ?: 0.0000001) + $xi));

            if ($crosses) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function distanceToNormalizedPolylineMeters(array $point, array $linePoints): float
    {
        $minimum = INF;

        for ($index = 0; $index < count($linePoints) - 1; $index++) {
            $minimum = min(
                $minimum,
                $this->distanceToLineSegmentMeters($point, $linePoints[$index], $linePoints[$index + 1])
            );
        }

        return $minimum;
    }

    private function pointInsideSegmentPolygon(float $latitude, float $longitude, array $coordinates): bool
    {
        if (! is_array($coordinates) || count($coordinates) < 3) {
            return false;
        }

        $inside = false;
        $total = count($coordinates);

        for ($i = 0, $j = $total - 1; $i < $total; $j = $i++) {
            $xi = (float) ($coordinates[$i][0] ?? 0.0);
            $yi = (float) ($coordinates[$i][1] ?? 0.0);
            $xj = (float) ($coordinates[$j][0] ?? 0.0);
            $yj = (float) ($coordinates[$j][1] ?? 0.0);

            $crosses = (($yi > $latitude) !== ($yj > $latitude))
                && ($longitude < (($xj - $xi) * ($latitude - $yi) / (($yj - $yi) ?: 0.0000001) + $xi));

            if ($crosses) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    private function distanceToPolylineMeters(float $latitude, float $longitude, array $coordinates): float
    {
        $points = collect($coordinates)
            ->map(function ($coordinate): ?array {
                if (! is_array($coordinate) || count($coordinate) < 2) {
                    return null;
                }

                return [
                    'lat' => (float) $coordinate[1],
                    'lng' => (float) $coordinate[0],
                ];
            })
            ->filter(fn (?array $point): bool => $point !== null)
            ->values()
            ->all();

        if (count($points) < 2) {
            return INF;
        }

        $minimum = INF;
        $target = ['lat' => $latitude, 'lng' => $longitude];

        for ($index = 0; $index < count($points) - 1; $index++) {
            $minimum = min(
                $minimum,
                $this->distanceToLineSegmentMeters($target, $points[$index], $points[$index + 1])
            );
        }

        return $minimum;
    }

    private function distanceToLineSegmentMeters(array $point, array $start, array $end): float
    {
        $metersPerDegreeLat = 111320.0;
        $metersPerDegreeLng = 111320.0 * cos(deg2rad($point['lat']));

        $px = $point['lng'] * $metersPerDegreeLng;
        $py = $point['lat'] * $metersPerDegreeLat;
        $sx = $start['lng'] * $metersPerDegreeLng;
        $sy = $start['lat'] * $metersPerDegreeLat;
        $ex = $end['lng'] * $metersPerDegreeLng;
        $ey = $end['lat'] * $metersPerDegreeLat;

        $dx = $ex - $sx;
        $dy = $ey - $sy;

        if (abs($dx) < 0.000001 && abs($dy) < 0.000001) {
            return hypot($px - $sx, $py - $sy);
        }

        $t = max(0, min(1, (($px - $sx) * $dx + ($py - $sy) * $dy) / (($dx * $dx + $dy * $dy) ?: 0.0000001)));
        $closestX = $sx + ($t * $dx);
        $closestY = $sy + ($t * $dy);

        return hypot($px - $closestX, $py - $closestY);
    }

    private function speedLimitsBySegmentIds(array $segmentIds): array
    {
        if ($segmentIds === []) {
            return [];
        }

        return RoadSegment::query()
            ->whereIn('id', $segmentIds)
            ->with([
                'segmentType.defaultRules' => function ($query) {
                    $query->select('id', 'segment_type_id', 'rule_name', 'rule_type', 'rule_value', 'description', 'is_active', 'sort_order')
                        ->orderBy('sort_order');
                },
            ])
            ->get(['id', 'segment_type_id'])
            ->mapWithKeys(function (RoadSegment $segment): array {
                $rule = $this->segmentRuleResolver->resolveSpeedLimitRuleForSegment($segment);
                return [(int) $segment->id => $this->resolveSpeedLimitFromRule($rule)];
            })
            ->all();
    }

    private function noParkingRulesBySegmentIds(array $segmentIds): array
    {
        if ($segmentIds === []) {
            return [];
        }

        return RoadSegment::query()
            ->whereIn('id', $segmentIds)
            ->with([
                'segmentType.defaultRules' => function ($query) {
                    $query->select('id', 'segment_type_id', 'rule_name', 'rule_type', 'rule_value', 'description', 'is_active', 'sort_order')
                        ->orderBy('sort_order');
                },
            ])
            ->get(['id', 'segment_type_id'])
            ->mapWithKeys(function (RoadSegment $segment): array {
                return [(int) $segment->id => (bool) $this->segmentRuleResolver->resolveNoParkingRuleForSegment($segment)];
            })
            ->all();
    }

    private function resolveSpeedLimitFromRule(?array $speedRule): float
    {
        if (! $speedRule) {
            return self::DEFAULT_COUNTRY_SPEED_LIMIT;
        }

        if (preg_match('/\d+(?:\.\d+)?/', (string) ($speedRule['rule_value'] ?? ''), $matches)) {
            return (float) $matches[0];
        }

        return self::DEFAULT_COUNTRY_SPEED_LIMIT;
    }

    private function resolveTelemetryStatusColor(
        float $speed,
        ?int $segmentId,
        array $speedLimitsBySegment,
        array $noParkingRulesBySegment
    ): string {
        if (
            $segmentId &&
            ($noParkingRulesBySegment[$segmentId] ?? false) &&
            $speed < self::NO_PARKING_SPEED_THRESHOLD_KMH
        ) {
            return 'red';
        }

        return $this->resolveStatusColor(
            $speed,
            $segmentId ? ($speedLimitsBySegment[$segmentId] ?? self::DEFAULT_COUNTRY_SPEED_LIMIT) : self::DEFAULT_COUNTRY_SPEED_LIMIT
        );
    }

    private function resolveStatusColor(float $speed, float $speedLimit): string
    {
        if ($speed <= $speedLimit) {
            return 'green';
        }

        if ($this->shouldCreateTelemetryReport($speed, $speedLimit)) {
            return 'red';
        }

        if (abs($speed - $speedLimit) < 0.0001) {
            return 'blue';
        }

        return 'blue';
    }

    private function createViolationReportFromTelemetry(
        VehicleTelemetry $telemetry,
        ?RoadSegment $segment,
        float $speedLimit,
        ?array $speedRule
    ): string {
        if (! $segment?->id || ! $speedRule) {
            return '';
        }

        $existingReference = $this->findRecentTelemetryReportReference(
            (string) $telemetry->citizen_device_no,
            (int) $segment->id,
            (int) $speedRule['segment_type_rule_id']
        );

        if ($existingReference) {
            return $existingReference;
        }

        $report = DB::transaction(function () use ($telemetry, $segment, $speedLimit, $speedRule) {
            $violationType = ViolationType::firstOrCreate(
                ['name' => 'Overspeeding'],
                ['description' => 'Vehicle operating above posted speed limit.', 'is_active' => true]
            );

            $report = Report::create([
                'reference_no' => $this->makeReferenceNumber(),
                'violation_type_id' => $violationType->id,
                'description' => sprintf(
                    'Telemetry red alert: %s moving at %.2f km/h above limit %.2f km/h.',
                    $telemetry->citizen_device_no,
                    (float) $telemetry->current_speed,
                    $speedLimit
                ),
                'latitude' => $telemetry->latitude,
                'longitude' => $telemetry->longitude,
                'location_name' => $segment?->segment_name,
                'status' => 'submitted',
                'priority' => 'high',
                'reported_at' => now(),
            ]);

            RuleViolation::create([
                'report_id' => $report->id,
                'segment_id' => $segment->id,
                'segment_type_rule_id' => $speedRule['segment_type_rule_id'],
                'rule_name_snapshot' => $speedRule['rule_name'],
                'rule_type_snapshot' => $speedRule['rule_type'],
                'rule_value_snapshot' => $speedRule['rule_value'],
                'rule_description_snapshot' => $speedRule['description'],
                'matched_automatically' => true,
                'confidence_score' => 96.50,
            ]);

            return $report;
        });

        return $report->reference_no;
    }

    /**
     * @return array{reference_no: ?string, created: bool, duplicate: bool}
     */
    private function createNoParkingReportFromTelemetry(
        VehicleTelemetry $telemetry,
        ?RoadSegment $segment,
        ?array $noParkingRule
    ): array {
        if (! $segment?->id || ! $noParkingRule) {
            return [
                'reference_no' => null,
                'created' => false,
                'duplicate' => false,
            ];
        }

        $existingReference = $this->findRecentTelemetryReportReference(
            (string) $telemetry->citizen_device_no,
            (int) $segment->id,
            (int) $noParkingRule['segment_type_rule_id'],
            'Telemetry no-parking alert'
        );

        if ($existingReference) {
            return [
                'reference_no' => $existingReference,
                'created' => false,
                'duplicate' => true,
            ];
        }

        $report = DB::transaction(function () use ($telemetry, $segment, $noParkingRule) {
            $violationType = ViolationType::firstOrCreate(
                ['name' => 'No parking'],
                ['description' => 'Vehicle detected as stationary in a no-parking segment.', 'is_active' => true]
            );

            $report = Report::create([
                'reference_no' => $this->makeReferenceNumber(),
                'violation_type_id' => $violationType->id,
                'description' => sprintf(
                    'Telemetry no-parking alert: %s stationary at %.2f km/h on no-parking segment %s.',
                    $telemetry->citizen_device_no,
                    (float) $telemetry->current_speed,
                    $segment->segment_name
                ),
                'latitude' => $telemetry->latitude,
                'longitude' => $telemetry->longitude,
                'location_name' => $segment->segment_name,
                'status' => 'submitted',
                'priority' => 'medium',
                'reported_at' => now(),
            ]);

            RuleViolation::create([
                'report_id' => $report->id,
                'segment_id' => $segment->id,
                'segment_type_rule_id' => $noParkingRule['segment_type_rule_id'],
                'rule_name_snapshot' => $noParkingRule['rule_name'],
                'rule_type_snapshot' => $noParkingRule['rule_type'],
                'rule_value_snapshot' => $noParkingRule['rule_value'],
                'rule_description_snapshot' => $noParkingRule['description'],
                'matched_automatically' => true,
                'confidence_score' => 92.00,
            ]);

            return $report;
        });

        return [
            'reference_no' => $report->reference_no,
            'created' => true,
            'duplicate' => false,
        ];
    }

    /**
     * @return array{elapsed_seconds: int, remaining_seconds: int, can_report: bool}
     */
    private function evaluateNoParkingReportState(string $deviceNo, int $segmentId, int $segmentTypeRuleId): array
    {
        $key = $this->noParkingStateCacheKey($deviceNo, $segmentId, $segmentTypeRuleId);
        $now = now()->timestamp;
        $state = Cache::get($key);

        if (
            ! is_array($state) ||
            ! isset($state['started_at'], $state['last_seen_at']) ||
            $now - (int) $state['last_seen_at'] > self::NO_PARKING_MAX_HEARTBEAT_GAP_SECONDS
        ) {
            $state = [
                'started_at' => $now,
                'last_seen_at' => $now,
            ];
        }

        $state['last_seen_at'] = $now;
        Cache::put($key, $state, now()->addSeconds(self::NO_PARKING_STATE_TTL_SECONDS));

        $elapsedSeconds = max(0, $now - (int) $state['started_at']);
        $remainingSeconds = max(0, self::REQUIRED_NO_PARKING_SECONDS - $elapsedSeconds);

        return [
            'elapsed_seconds' => $elapsedSeconds,
            'remaining_seconds' => $remainingSeconds,
            'can_report' => $elapsedSeconds >= self::REQUIRED_NO_PARKING_SECONDS,
        ];
    }

    private function clearNoParkingReportState(string $deviceNo, int $segmentId, int $segmentTypeRuleId): void
    {
        Cache::forget($this->noParkingStateCacheKey($deviceNo, $segmentId, $segmentTypeRuleId));
    }

    private function noParkingStateCacheKey(string $deviceNo, int $segmentId, int $segmentTypeRuleId): string
    {
        return 'vehicle_telemetry.no_parking.'.sha1($deviceNo.'|'.$segmentId.'|'.$segmentTypeRuleId);
    }

    private function normalizeSpeedKmh(float $speed): float
    {
        $cleanSpeed = round(max(0, $speed), 2);

        if ($cleanSpeed < self::STATIONARY_SPEED_THRESHOLD_KMH) {
            return 0.0;
        }

        return $cleanSpeed;
    }

    private function shouldCreateTelemetryReport(float $speedKmh, float $speedLimit): bool
    {
        if ($speedKmh <= $speedLimit) {
            return false;
        }

        if ($speedKmh < self::STATIONARY_SPEED_THRESHOLD_KMH) {
            return false;
        }

        return ($speedKmh - $speedLimit) >= self::MIN_OVERSPEED_MARGIN_KMH;
    }

    private function shouldCreateNoParkingReport(float $speedKmh, ?array $noParkingRule): bool
    {
        return (bool) $noParkingRule && $speedKmh < self::NO_PARKING_SPEED_THRESHOLD_KMH;
    }

    private function findRecentTelemetryReportReference(
        string $deviceNo,
        int $segmentId,
        int $segmentTypeRuleId,
        string $descriptionPrefix = 'Telemetry red alert'
    ): ?string
    {
        $recent = Report::query()
            ->where('reported_at', '>=', now()->subSeconds(self::TELEMETRY_REPORT_COOLDOWN_SECONDS))
            ->where('description', 'like', $descriptionPrefix.': '.$deviceNo.'%')
            ->whereHas('ruleViolations', function ($query) use ($segmentId, $segmentTypeRuleId): void {
                $query
                    ->where('segment_id', $segmentId)
                    ->where('segment_type_rule_id', $segmentTypeRuleId);
            })
            ->latest('id')
            ->first(['reference_no']);

        return $recent?->reference_no;
    }

    private function makeReferenceNumber(): string
    {
        do {
            $referenceNo = 'RPT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Report::where('reference_no', $referenceNo)->exists());

        return $referenceNo;
    }
}
