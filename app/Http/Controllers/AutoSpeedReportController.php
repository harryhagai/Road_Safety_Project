<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\RoadSegment;
use App\Models\RuleViolation;
use App\Models\ViolationType;
use App\Services\SegmentRuleResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Web controller that coordinates the AutoSpeedReportController request lifecycle.
 */
class AutoSpeedReportController extends Controller
{
    public function __construct(private readonly SegmentRuleResolver $segmentRuleResolver) {}

    private const ACTIVE_RULES_CACHE_SECONDS = 20;
    private const REQUIRED_EXCEEDED_SECONDS = 30;
    private const DUPLICATE_WINDOW_SECONDS = 600;
    private const BASE_SEGMENT_TOLERANCE_METERS = 30;
    private const MAX_SEGMENT_TOLERANCE_METERS = 120;

    /**
     * Handle the evaluate workflow for this class.
     */

    public function evaluate(Request $request): JsonResponse
    {
        $validated = $this->validateTelemetry($request);
        $accuracy = (float) ($validated['accuracy'] ?? self::BASE_SEGMENT_TOLERANCE_METERS);
        $match = $this->matchSpeedRule(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $accuracy
        );

        if (! $match) {
            $this->clearExceededSession();

            return response()->json([
                'matched' => false,
                'message' => 'No monitored speed segment nearby.',
            ]);
        }

        $speedKmh = (float) $validated['speed_kmh'];
        $exceeded = $speedKmh > $match['speed_limit_kmh'];
        $sessionKey = $this->exceededSessionKey($match['segment']->id, $match['rule']->id);

        if ($exceeded) {
            $startedAt = session($sessionKey);

            if (! is_numeric($startedAt)) {
                $startedAt = now()->timestamp;
                session()->put($sessionKey, $startedAt);
            }
        } else {
            session()->forget($sessionKey);
            $startedAt = null;
        }

        $exceededSeconds = $startedAt ? max(0, now()->timestamp - (int) $startedAt) : 0;
        $reporting = $this->reportingSnapshot((int) $match['segment']->id, (int) $match['rule']->id);

        return response()->json([
            'matched' => true,
            'exceeded' => $exceeded,
            'can_submit' => $exceeded && $exceededSeconds >= self::REQUIRED_EXCEEDED_SECONDS,
            'exceeded_seconds' => $exceededSeconds,
            'required_seconds' => self::REQUIRED_EXCEEDED_SECONDS,
            'distance_meters' => round($match['distance_meters'], 1),
            'matching_buffer_meters' => round($match['matching_buffer_meters'], 1),
            'speed_kmh' => round($speedKmh, 1),
            'speed_limit_kmh' => $match['speed_limit_kmh'],
            'segment' => [
                'id' => $match['segment']->id,
                'name' => $match['segment']->segment_name,
                'db_name' => $match['segment']->segment_name,
            ],
            'rule' => [
                'id' => $match['rule']->id,
                'name' => $match['rule']->rule_name,
                'value' => $match['rule']->rule_value,
            ],
            'reporting' => $reporting,
        ]);
    }

    /**
     * Validate the request and persist a new record.
     */

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateTelemetry($request) + $request->validate([
            'rule_id' => ['required', 'integer', 'exists:segment_type_rules,id'],
            'segment_id' => ['required', 'integer', 'exists:road_segments,id'],
        ]);
        $accuracy = (float) ($validated['accuracy'] ?? self::BASE_SEGMENT_TOLERANCE_METERS);

        $match = $this->matchSpeedRule(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $accuracy
        );

        if (! $match || (int) $validated['rule_id'] !== (int) $match['rule']->id || (int) $validated['segment_id'] !== (int) $match['segment']->id) {
            return response()->json([
                'reported' => false,
                'reason' => 'rule_mismatch',
                'message' => 'The current location no longer matches that speed rule.',
            ], 409);
        }

        $speedKmh = (float) $validated['speed_kmh'];

        if ($speedKmh <= $match['speed_limit_kmh']) {
            session()->forget($this->exceededSessionKey($match['segment']->id, $match['rule']->id));

            return response()->json([
                'reported' => false,
                'reason' => 'speed_within_limit',
                'message' => 'The current speed is within the saved limit.',
            ], 409);
        }

        $startedAt = session($this->exceededSessionKey($match['segment']->id, $match['rule']->id));
        $exceededSeconds = is_numeric($startedAt) ? max(0, now()->timestamp - (int) $startedAt) : 0;

        if ($exceededSeconds < self::REQUIRED_EXCEEDED_SECONDS) {
            return response()->json([
                'reported' => false,
                'reason' => 'duration_pending',
                'exceeded_seconds' => $exceededSeconds,
                'required_seconds' => self::REQUIRED_EXCEEDED_SECONDS,
            ], 409);
        }

        $duplicate = session($this->reportedSessionKey($match['segment']->id, $match['rule']->id));
        if (is_array($duplicate) && now()->timestamp - (int) ($duplicate['reported_at'] ?? 0) < self::DUPLICATE_WINDOW_SECONDS) {
            return response()->json([
                'reported' => true,
                'duplicate' => true,
                'reference_no' => $duplicate['reference_no'] ?? null,
            ]);
        }

        $report = DB::transaction(function () use ($validated, $match, $speedKmh, $exceededSeconds) {
            $violationType = ViolationType::firstOrCreate(
                ['name' => 'Overspeeding'],
                [
                    'description' => 'Vehicle operating beyond the allowed speed limit.',
                    'is_active' => true,
                ]
            );

            $report = Report::create([
                'reference_no' => $this->makeReferenceNumber(),
                'violation_type_id' => $violationType->id,
                'description' => sprintf(
                    'Automatic overspeeding report: %.1f km/h recorded against a %.0f km/h speed limit for %d seconds on %s.',
                    $speedKmh,
                    $match['speed_limit_kmh'],
                    $exceededSeconds,
                    $match['segment']->segment_name
                ),
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'location_name' => $match['segment']->segment_name,
                'status' => 'submitted',
                'priority' => $this->priorityForSpeed($speedKmh, $match['speed_limit_kmh']),
                'reported_at' => now(),
            ]);

            RuleViolation::create([
                'report_id' => $report->id,
                'segment_id' => $match['segment']->id,
                'segment_type_rule_id' => $match['rule']->id,
                'rule_name_snapshot' => $match['rule']->rule_name,
                'rule_type_snapshot' => $match['rule']->rule_type,
                'rule_value_snapshot' => $match['rule']->rule_value,
                'rule_description_snapshot' => $match['rule']->description,
                'matched_automatically' => true,
                'confidence_score' => $this->confidenceForDistance($match['distance_meters']),
            ]);

            return $report;
        });

        session()->put($this->reportedSessionKey($match['segment']->id, $match['rule']->id), [
            'reference_no' => $report->reference_no,
            'reported_at' => now()->timestamp,
        ]);
        session()->forget($this->exceededSessionKey($match['segment']->id, $match['rule']->id));

        return response()->json([
            'reported' => true,
            'duplicate' => false,
            'reference_no' => $report->reference_no,
        ], 201);
    }

    /**
     * Handle the validateTelemetry workflow for this class.
     */

    private function validateTelemetry(Request $request): array
    {
        return $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'speed_kmh' => ['required', 'numeric', 'min:0', 'max:320'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'heading' => ['nullable', 'numeric', 'min:0', 'max:360'],
        ]);
    }

    /**
     * Handle the matchSpeedRule workflow for this class.
     */

    private function matchSpeedRule(float $latitude, float $longitude, float $accuracy): ?array
    {
        $cacheKey = 'auto_speed.active_rules.snapshot';
        $segments = Cache::remember($cacheKey, now()->addSeconds(self::ACTIVE_RULES_CACHE_SECONDS), function () {
            return RoadSegment::query()
                ->whereNotNull('boundary_coordinates')
                ->with([
                    'segmentType:id,name',
                    'segmentType.defaultRules' => function ($query) {
                        $query->select('id', 'segment_type_id', 'rule_name', 'rule_type', 'rule_value', 'description', 'is_active', 'sort_order')
                            ->orderBy('sort_order');
                    },
                ])
                ->get(['id', 'segment_name', 'segment_type_id', 'boundary_coordinates']);
        });

        $bestMatch = null;
        $nearestMatch = null;
        $tolerance = min(
            self::MAX_SEGMENT_TOLERANCE_METERS,
            max(self::BASE_SEGMENT_TOLERANCE_METERS, $accuracy + 80)
        );

        foreach ($segments as $segment) {
            $resolvedRule = $this->segmentRuleResolver->resolveSpeedLimitRuleForSegment($segment);
            if (! $resolvedRule) {
                continue;
            }

            $speedLimit = $this->parseSpeedLimit((string) ($resolvedRule['rule_value'] ?? ''));
            if (! $speedLimit) {
                continue;
            }

            $points = $this->segmentPoints($segment->boundary_coordinates);
            if (count($points) < 2) {
                continue;
            }

            $distance = $this->distanceToPolylineMeters(['lat' => $latitude, 'lng' => $longitude], $points);
            $segmentBuffer = $this->segmentBufferMeters($segment);
            $matchingBuffer = max($tolerance, $segmentBuffer);
            $ruleData = (object) [
                'id' => (int) $resolvedRule['segment_type_rule_id'],
                'rule_name' => $resolvedRule['rule_name'],
                'rule_type' => $resolvedRule['rule_type'],
                'rule_value' => $resolvedRule['rule_value'],
                'description' => $resolvedRule['description'],
            ];

            if (! $nearestMatch || $distance < $nearestMatch['distance_meters']) {
                $nearestMatch = [
                    'rule' => $ruleData,
                    'segment' => $segment,
                    'speed_limit_kmh' => $speedLimit,
                    'distance_meters' => $distance,
                    'matching_buffer_meters' => $matchingBuffer,
                ];
            }

            if ($distance > $matchingBuffer) {
                continue;
            }

            if (! $bestMatch || $distance < $bestMatch['distance_meters']) {
                $bestMatch = [
                    'rule' => $ruleData,
                    'segment' => $segment,
                    'speed_limit_kmh' => $speedLimit,
                    'distance_meters' => $distance,
                    'matching_buffer_meters' => $matchingBuffer,
                ];
            }
        }

        return $bestMatch ?: (
            $nearestMatch && $nearestMatch['distance_meters'] <= $nearestMatch['matching_buffer_meters']
                ? $nearestMatch
                : null
        );
    }

    /**
     * Estimate segment detection buffer from road type metadata.
     */
    private function segmentBufferMeters(RoadSegment $segment): float
    {
        $type = strtolower(trim((string) ($segment->segment_type_name ?? '')));

        if ($type === '') {
            return self::BASE_SEGMENT_TOLERANCE_METERS;
        }

        if (str_contains($type, 'highway') || str_contains($type, 'trunk') || str_contains($type, 'junction')) {
            return 40.0;
        }

        if (str_contains($type, 'primary')) {
            return 30.0;
        }

        if (str_contains($type, 'secondary') || str_contains($type, 'arterial')) {
            return 24.0;
        }

        if (str_contains($type, 'residential') || str_contains($type, 'local')) {
            return 15.0;
        }

        return 20.0;
    }

    /**
     * Handle the parseSpeedLimit workflow for this class.
     */

    private function parseSpeedLimit(?string $value): ?float
    {
        if (! $value || ! preg_match('/\d+(?:\.\d+)?/', $value, $matches)) {
            return null;
        }

        $speedLimit = (float) $matches[0];

        return $speedLimit > 0 ? $speedLimit : null;
    }

    /**
     * Handle the segmentPoints workflow for this class.
     */

    private function segmentPoints(?array $geometry): array
    {
        $coordinates = $this->extractCoordinates($geometry);

        if (! is_array($coordinates)) {
            return [];
        }

        return collect($coordinates)
            ->map(function ($coordinate) {
                return $this->normalizeCoordinate($coordinate);
            })
            ->filter(fn (?array $point): bool => $point !== null)
            ->values()
            ->all();
    }

    /**
     * Handle the extractCoordinates workflow for this class.
     */

    private function extractCoordinates(?array $geometry): array
    {
        if (! is_array($geometry)) {
            return [];
        }

        $coordinates = data_get($geometry, 'geometry.coordinates');

        if (is_array($coordinates)) {
            return $coordinates;
        }

        $coordinates = data_get($geometry, 'features.0.geometry.coordinates');

        if (is_array($coordinates)) {
            return $coordinates;
        }

        $coordinates = data_get($geometry, 'coordinates');

        if (is_array($coordinates)) {
            return $coordinates;
        }

        return $geometry;
    }

    /**
     * Handle the normalizeCoordinate workflow for this class.
     */

    private function normalizeCoordinate(mixed $coordinate): ?array
    {
        if (! is_array($coordinate)) {
            return null;
        }

        if (isset($coordinate['lat'], $coordinate['lng'])) {
            return $this->validPoint((float) $coordinate['lat'], (float) $coordinate['lng']);
        }

        if (isset($coordinate['latitude'], $coordinate['longitude'])) {
            return $this->validPoint((float) $coordinate['latitude'], (float) $coordinate['longitude']);
        }

        if (count($coordinate) < 2) {
            return null;
        }

        $first = (float) $coordinate[0];
        $second = (float) $coordinate[1];

        // GeoJSON stores [lng, lat], but some map payloads arrive as [lat, lng].
        if (abs($first) <= 20 && abs($second) > 20) {
            return $this->validPoint($first, $second);
        }

        return $this->validPoint($second, $first);
    }

    /**
     * Handle the validPoint workflow for this class.
     */

    private function validPoint(float $latitude, float $longitude): ?array
    {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        return [
            'lat' => $latitude,
            'lng' => $longitude,
        ];
    }

    /**
     * Handle the distanceToPolylineMeters workflow for this class.
     */

    private function distanceToPolylineMeters(array $point, array $linePoints): float
    {
        $minimum = INF;

        for ($index = 0; $index < count($linePoints) - 1; $index++) {
            $minimum = min(
                $minimum,
                $this->distanceToSegmentMeters($point, $linePoints[$index], $linePoints[$index + 1])
            );
        }

        return $minimum;
    }

    /**
     * Handle the distanceToSegmentMeters workflow for this class.
     */

    private function distanceToSegmentMeters(array $point, array $start, array $end): float
    {
        $metersPerDegreeLat = 111_320;
        $metersPerDegreeLng = 111_320 * cos(deg2rad($point['lat']));

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

        $t = max(0, min(1, (($px - $sx) * $dx + ($py - $sy) * $dy) / ($dx * $dx + $dy * $dy)));
        $closestX = $sx + $t * $dx;
        $closestY = $sy + $t * $dy;

        return hypot($px - $closestX, $py - $closestY);
    }

    /**
     * Handle the confidenceForDistance workflow for this class.
     */

    private function confidenceForDistance(float $distanceMeters): float
    {
        return round(max(55, min(99, 100 - $distanceMeters)), 2);
    }

    /**
     * Handle the priorityForSpeed workflow for this class.
     */

    private function priorityForSpeed(float $speedKmh, float $limitKmh): string
    {
        $overBy = $speedKmh - $limitKmh;

        if ($overBy >= 30) {
            return 'high';
        }

        if ($overBy >= 15) {
            return 'medium';
        }

        return 'normal';
    }

    /**
     * Handle the makeReferenceNumber workflow for this class.
     */

    private function makeReferenceNumber(): string
    {
        do {
            $referenceNo = 'RPT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Report::where('reference_no', $referenceNo)->exists());

        return $referenceNo;
    }

    /**
     * Handle the exceededSessionKey workflow for this class.
     */

    private function exceededSessionKey(int $segmentId, int $ruleId): string
    {
        return "auto_speed.exceeded.{$segmentId}.{$ruleId}";
    }

    /**
     * Handle the reportedSessionKey workflow for this class.
     */

    private function reportedSessionKey(int $segmentId, int $ruleId): string
    {
        return "auto_speed.reported.{$segmentId}.{$ruleId}";
    }

    /**
     * Handle the clearExceededSession workflow for this class.
     */

    private function clearExceededSession(): void
    {
        foreach (array_keys(session()->all()) as $key) {
            if (str_starts_with($key, 'auto_speed.exceeded.')) {
                session()->forget($key);
            }
        }
    }

    /**
     * Build live reporting snapshot for the matched speed rule.
     */
    private function reportingSnapshot(int $segmentId, int $segmentTypeRuleId): array
    {
        $latestViolation = RuleViolation::query()
            ->where('segment_id', $segmentId)
            ->where('segment_type_rule_id', $segmentTypeRuleId)
            ->with('report:id,reference_no,status,reported_at')
            ->latest('id')
            ->first();

        $latestReport = $latestViolation?->report;
        $totalReports = RuleViolation::query()
            ->where('segment_id', $segmentId)
            ->where('segment_type_rule_id', $segmentTypeRuleId)
            ->count();

        return [
            'total_reports_for_rule' => $totalReports,
            'latest_reference_no' => $latestReport?->reference_no,
            'latest_status' => $latestReport?->status,
            'latest_reported_at' => optional($latestReport?->reported_at)?->toIso8601String(),
        ];
    }
}
