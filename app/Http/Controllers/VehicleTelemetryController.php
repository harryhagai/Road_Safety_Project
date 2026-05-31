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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VehicleTelemetryController extends Controller
{
    public function __construct(private readonly SegmentRuleResolver $segmentRuleResolver) {}

    private const DEFAULT_COUNTRY_SPEED_LIMIT = 80.0;

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
        $speed = round((float) ($validated['current_speed'] ?? 0), 2);
        $heading = isset($validated['heading']) ? round((float) $validated['heading'], 2) : null;

        $latestForVehicle = VehicleTelemetry::query()
            ->where('citizen_device_no', $deviceNo)
            ->latest('telemetry_id')
            ->first();

        if (
            $latestForVehicle &&
            round((float) $latestForVehicle->latitude, 6) === round($latitude, 6) &&
            round((float) $latestForVehicle->longitude, 6) === round($longitude, 6)
        ) {
            return response()->json([
                'saved' => false,
                'unchanged_coordinate' => true,
                'message' => 'Coordinate unchanged, telemetry skipped.',
                'telemetry_id' => $latestForVehicle->telemetry_id,
            ]);
        }

        $segment = $this->resolveSegmentBySpatialLookup($latitude, $longitude);
        $speedRule = $segment ? $this->segmentRuleResolver->resolveSpeedLimitRuleForSegment($segment) : null;
        $speedLimit = $this->resolveSpeedLimitFromRule($speedRule);
        $statusColor = $this->resolveStatusColor($speed, $speedLimit);

        $telemetry = VehicleTelemetry::create([
            'citizen_device_no' => $deviceNo,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current_speed' => $speed,
            'heading' => $heading,
            'segment_id' => $segment?->id,
        ]);

        $reportReference = null;
        if ($statusColor === 'red') {
            $reportReference = $this->createViolationReportFromTelemetry($telemetry, $segment, $speedLimit, $speedRule);
        }

        return response()->json([
            'saved' => true,
            'telemetry_id' => $telemetry->telemetry_id,
            'citizen_device_no' => $telemetry->citizen_device_no,
            'vehicle_reg_no' => $telemetry->citizen_device_no,
            'status_color' => $statusColor,
            'speed_limit' => $speedLimit,
            'segment' => $segment?->segment_name,
            'report_reference_no' => $reportReference,
        ]);
    }

    public function live(): JsonResponse
    {
        $rows = VehicleTelemetry::query()
            ->with('segment:id,segment_name')
            ->latest('telemetry_id')
            ->limit(700)
            ->get();
        $speedLimitsBySegment = $this->speedLimitsBySegmentIds(
            $rows->pluck('segment_id')->filter()->unique()->map(fn ($value) => (int) $value)->all()
        );

        $mappedRows = $rows->map(fn (VehicleTelemetry $item): array => [
                'telemetry_id' => $item->telemetry_id,
                'citizen_device_no' => $item->citizen_device_no,
                'vehicle_reg_no' => $item->citizen_device_no,
                'latitude' => (float) $item->latitude,
                'longitude' => (float) $item->longitude,
                'current_speed' => (float) $item->current_speed,
                'heading' => $item->heading !== null ? (float) $item->heading : null,
                'status_color' => $this->resolveStatusColor(
                    (float) $item->current_speed,
                    $item->segment_id ? ($speedLimitsBySegment[(int) $item->segment_id] ?? self::DEFAULT_COUNTRY_SPEED_LIMIT) : self::DEFAULT_COUNTRY_SPEED_LIMIT
                ),
                'segment_name' => $item->segment?->segment_name,
                'created_at' => optional($item->created_at)->toDateTimeString(),
            ]);

        $tracks = $rows
            ->groupBy('citizen_device_no')
            ->map(function ($items, $vehicleRegNo): array {
                $ordered = $items->sortBy('telemetry_id')->values();
                $points = $ordered->map(fn (VehicleTelemetry $item): array => [
                    'latitude' => (float) $item->latitude,
                    'longitude' => (float) $item->longitude,
                    'heading' => $item->heading !== null ? (float) $item->heading : null,
                    'status_color' => $this->resolveStatusColor(
                        (float) $item->current_speed,
                        $item->segment_id ? ($speedLimitsBySegment[(int) $item->segment_id] ?? self::DEFAULT_COUNTRY_SPEED_LIMIT) : self::DEFAULT_COUNTRY_SPEED_LIMIT
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
                        'status_color' => $this->resolveStatusColor(
                            (float) $latest->current_speed,
                            $latest->segment_id ? ($speedLimitsBySegment[(int) $latest->segment_id] ?? self::DEFAULT_COUNTRY_SPEED_LIMIT) : self::DEFAULT_COUNTRY_SPEED_LIMIT
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
        $maxMatchDistanceMeters = 40.0;

        foreach (RoadSegment::query()->whereNotNull('boundary_coordinates')->get() as $segment) {
            $distance = $this->distanceToSegmentGeometryMeters($latitude, $longitude, $segment->boundary_coordinates ?? []);
            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $candidate = $segment;
            }
        }

        return $candidate && $nearestDistance <= $maxMatchDistanceMeters ? $candidate : null;
    }

    private function distanceToSegmentGeometryMeters(float $latitude, float $longitude, array $geometry): float
    {
        $rawCoordinates = data_get($geometry, 'geometry.coordinates')
            ?? data_get($geometry, 'features.0.geometry.coordinates')
            ?? data_get($geometry, 'coordinates')
            ?? [];
        $geometryType = strtolower((string) (
            data_get($geometry, 'geometry.type')
            ?? data_get($geometry, 'features.0.geometry.type')
            ?? data_get($geometry, 'type')
            ?? ''
        ));

        if (! is_array($rawCoordinates) || $rawCoordinates === []) {
            return INF;
        }

        if (str_contains($geometryType, 'polygon')) {
            $ring = $rawCoordinates[0] ?? [];
            if ($this->pointInsideSegmentPolygon($latitude, $longitude, is_array($ring) ? $ring : [])) {
                return 0.0;
            }

            return $this->distanceToPolylineMeters($latitude, $longitude, is_array($ring) ? $ring : []);
        }

        return $this->distanceToPolylineMeters($latitude, $longitude, $rawCoordinates);
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

    private function resolveStatusColor(float $speed, float $speedLimit): string
    {
        if ($speed < $speedLimit) {
            return 'green';
        }

        if (abs($speed - $speedLimit) < 0.0001) {
            return 'blue';
        }

        return 'red';
    }

    private function createViolationReportFromTelemetry(
        VehicleTelemetry $telemetry,
        ?RoadSegment $segment,
        float $speedLimit,
        ?array $speedRule
    ): string {
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

            if ($segment?->id && $speedRule) {
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
            }

            return $report;
        });

        return $report->reference_no;
    }

    private function makeReferenceNumber(): string
    {
        do {
            $referenceNo = 'RPT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Report::where('reference_no', $referenceNo)->exists());

        return $referenceNo;
    }
}
