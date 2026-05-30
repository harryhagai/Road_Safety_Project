<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\RoadRule;
use App\Models\RoadSegment;
use App\Models\RuleViolation;
use App\Models\VehicleTelemetry;
use App\Models\ViolationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VehicleTelemetryController extends Controller
{
    private const DEFAULT_COUNTRY_SPEED_LIMIT = 80.0;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_reg_no' => ['required', 'string', 'max:60'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'current_speed' => ['nullable', 'numeric', 'min:0', 'max:320'],
            'heading' => ['nullable', 'numeric', 'min:0', 'max:360'],
        ]);

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];
        $speed = round((float) ($validated['current_speed'] ?? 0), 2);
        $heading = isset($validated['heading']) ? round((float) $validated['heading'], 2) : null;

        $latestForVehicle = VehicleTelemetry::query()
            ->where('vehicle_reg_no', $validated['vehicle_reg_no'])
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
        $speedLimit = $this->resolveSpeedLimitForSegment($segment?->id);
        $statusColor = $this->resolveStatusColor($speed, $speedLimit);

        $telemetry = VehicleTelemetry::create([
            'vehicle_reg_no' => $validated['vehicle_reg_no'],
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current_speed' => $speed,
            'heading' => $heading,
            'status_color' => $statusColor,
            'segment_id' => $segment?->id,
        ]);

        $reportReference = null;
        if ($statusColor === 'red') {
            $reportReference = $this->createViolationReportFromTelemetry($telemetry, $segment, $speedLimit);
        }

        return response()->json([
            'saved' => true,
            'telemetry_id' => $telemetry->telemetry_id,
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

        $mappedRows = $rows->map(fn (VehicleTelemetry $item): array => [
                'telemetry_id' => $item->telemetry_id,
                'vehicle_reg_no' => $item->vehicle_reg_no,
                'latitude' => (float) $item->latitude,
                'longitude' => (float) $item->longitude,
                'current_speed' => (float) $item->current_speed,
                'heading' => $item->heading !== null ? (float) $item->heading : null,
                'status_color' => $item->status_color,
                'segment_name' => $item->segment?->segment_name,
                'created_at' => optional($item->created_at)->toDateTimeString(),
            ]);

        $tracks = $rows
            ->groupBy('vehicle_reg_no')
            ->map(function ($items, $vehicleRegNo): array {
                $ordered = $items->sortBy('telemetry_id')->values();
                $points = $ordered->map(fn (VehicleTelemetry $item): array => [
                    'latitude' => (float) $item->latitude,
                    'longitude' => (float) $item->longitude,
                    'heading' => $item->heading !== null ? (float) $item->heading : null,
                    'status_color' => $item->status_color,
                    'created_at' => optional($item->created_at)->toDateTimeString(),
                ])->all();

                $latest = $ordered->last();

                return [
                    'vehicle_reg_no' => (string) $vehicleRegNo,
                    'points' => $points,
                    'latest' => $latest ? [
                        'telemetry_id' => $latest->telemetry_id,
                        'vehicle_reg_no' => $latest->vehicle_reg_no,
                        'latitude' => (float) $latest->latitude,
                        'longitude' => (float) $latest->longitude,
                        'current_speed' => (float) $latest->current_speed,
                        'heading' => $latest->heading !== null ? (float) $latest->heading : null,
                        'status_color' => $latest->status_color,
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
        try {
            return RoadSegment::query()
                ->whereNotNull('boundary_coordinates')
                ->whereRaw(
                    "ST_Contains(
                        ST_GeomFromGeoJSON(
                            COALESCE(
                                JSON_UNQUOTE(JSON_EXTRACT(boundary_coordinates, '$.geometry')),
                                JSON_UNQUOTE(JSON_EXTRACT(boundary_coordinates, '$.features[0].geometry')),
                                JSON_UNQUOTE(boundary_coordinates)
                            )
                        ),
                        ST_SRID(POINT(?, ?), 4326)
                    )",
                    [$longitude, $latitude]
                )
                ->first();
        } catch (\Throwable) {
            return RoadSegment::query()
                ->whereNotNull('boundary_coordinates')
                ->get()
                ->first(function (RoadSegment $segment) use ($latitude, $longitude): bool {
                    return $this->pointInsideSegmentPolygon($latitude, $longitude, $segment->boundary_coordinates ?? []);
                });
        }
    }

    private function pointInsideSegmentPolygon(float $latitude, float $longitude, array $geometry): bool
    {
        $coordinates = data_get($geometry, 'geometry.coordinates.0')
            ?? data_get($geometry, 'features.0.geometry.coordinates.0')
            ?? data_get($geometry, 'coordinates.0')
            ?? [];

        if (!is_array($coordinates) || count($coordinates) < 3) {
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

    private function resolveSpeedLimitForSegment(?int $segmentId): float
    {
        if (!$segmentId) {
            return self::DEFAULT_COUNTRY_SPEED_LIMIT;
        }

        $rule = RoadRule::query()
            ->where('segment_id', $segmentId)
            ->where('rule_type', 'speed_limit')
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', now());
            })
            ->latest('id')
            ->first();

        if (!$rule) {
            return self::DEFAULT_COUNTRY_SPEED_LIMIT;
        }

        if (preg_match('/\d+(?:\.\d+)?/', (string) $rule->rule_value, $matches)) {
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
        float $speedLimit
    ): string {
        $report = DB::transaction(function () use ($telemetry, $segment, $speedLimit) {
            $violationType = ViolationType::firstOrCreate(
                ['name' => 'Overspeeding'],
                ['description' => 'Vehicle operating above posted speed limit.', 'is_active' => true]
            );

            $report = Report::create([
                'reference_no' => $this->makeReferenceNumber(),
                'violation_type_id' => $violationType->id,
                'description' => sprintf(
                    'Telemetry red alert: %s moving at %.2f km/h above limit %.2f km/h.',
                    $telemetry->vehicle_reg_no,
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

            $rule = null;
            if ($segment?->id) {
                $rule = RoadRule::query()
                    ->where('segment_id', $segment->id)
                    ->where('rule_type', 'speed_limit')
                    ->where('is_active', true)
                    ->latest('id')
                    ->first();
            }

            if ($rule) {
                RuleViolation::create([
                    'report_id' => $report->id,
                    'rule_id' => $rule->id,
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
