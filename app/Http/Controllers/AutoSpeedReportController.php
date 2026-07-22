<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\RoadSegment;
use App\Models\RuleViolation;
use App\Models\ViolationType;
use App\Services\SegmentRuleResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Web controller that coordinates the AutoSpeedReportController request lifecycle.
 */
class AutoSpeedReportController extends Controller
{
    public function __construct(private readonly SegmentRuleResolver $segmentRuleResolver) {}

    private const DRIVER_PENDING_SESSION_KEY = 'driver.pending_violation';

    private const DRIVER_REPORT_REFERENCE_SESSION_KEY = 'driver.report_reference';

    private const DRIVER_REPORT_DUPLICATE_SESSION_KEY = 'driver.report_duplicate';

    private const ACTIVE_RULES_CACHE_SECONDS = 20;

    private const REQUIRED_EXCEEDED_SECONDS = 30;

    private const DUPLICATE_WINDOW_SECONDS = 600;

    private const NO_PARKING_STATIONARY_SPEED_KMH = 1.0;

    private const BASE_SEGMENT_TOLERANCE_METERS = 15;

    private const ACCURACY_MARGIN_METERS = 0.5;

    private const MAX_SEGMENT_TOLERANCE_METERS = 50;

    private const MAX_ACCEPTABLE_GPS_ACCURACY_METERS = 80;

    /**
     * Handle the evaluate workflow for this class.
     */
    public function evaluate(Request $request): JsonResponse
    {
        $validated = $this->validateSpeedSample($request);
        $accuracy = isset($validated['accuracy']) ? (float) $validated['accuracy'] : null;
        if (! $this->isAccuracyReliable($accuracy)) {
            $this->clearExceededSession();

            return response()->json([
                'matched' => false,
                'reason' => 'low_accuracy',
                'accuracy_meters' => $accuracy,
                'required_accuracy_meters' => self::MAX_ACCEPTABLE_GPS_ACCURACY_METERS,
                'message' => 'GPS accuracy is too low for reliable segment matching.',
            ]);
        }

        $speedKmh = (float) $validated['speed_kmh'];
        $noParkingMatch = $this->matchNoParkingRule(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            (float) $accuracy
        );

        if ($noParkingMatch && $speedKmh <= self::NO_PARKING_STATIONARY_SPEED_KMH) {
            return response()->json($this->noParkingEvaluationPayload($noParkingMatch, $speedKmh, $validated));
        }

        if ($noParkingMatch) {
            session()->forget($this->noParkingSessionKey($noParkingMatch['segment']->id, $noParkingMatch['rule']->id));
        }

        $match = $this->matchSpeedRule(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            (float) $accuracy
        );

        if (! $match) {
            $segmentMatch = $this->matchRoadSegmentGeometry(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                (float) $accuracy
            );

            $this->clearExceededSession();

            if ($segmentMatch) {
                $displayRule = $this->displayRuleForSegment($segmentMatch['segment']);

                return response()->json([
                    'matched' => true,
                    'has_speed_rule' => false,
                    'exceeded' => false,
                    'can_submit' => false,
                    'exceeded_seconds' => 0,
                    'required_seconds' => self::REQUIRED_EXCEEDED_SECONDS,
                    'distance_meters' => round($segmentMatch['distance_meters'], 1),
                    'matching_buffer_meters' => round($segmentMatch['matching_buffer_meters'], 1),
                    'speed_kmh' => round((float) $validated['speed_kmh'], 1),
                    'speed_limit_kmh' => null,
                    'segment' => [
                        'id' => $segmentMatch['segment']->id,
                        'name' => $segmentMatch['segment']->segment_name,
                        'db_name' => $segmentMatch['segment']->segment_name,
                    ],
                    'rule' => $displayRule,
                    'message' => 'Segment detected, but no active speed rule is configured.',
                ]);
            }

            return response()->json([
                'matched' => false,
                'message' => 'No monitored speed segment nearby.',
            ]);
        }

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

        $payload = [
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
        ];

        if ($payload['can_submit']) {
            $payload = $this->withViolationContinuation(
                $payload,
                $validated,
                $match,
                'Overspeeding',
                'Vehicle operating beyond the allowed speed limit.',
                sprintf(
                    'Passenger-observed overspeeding: %.1f km/h recorded against a %.0f km/h speed limit for %d seconds on %s.',
                    $speedKmh,
                    $match['speed_limit_kmh'],
                    $exceededSeconds,
                    $match['segment']->segment_name
                ),
                $this->priorityForSpeed($speedKmh, $match['speed_limit_kmh']),
                [
                    'speed_kmh' => $speedKmh,
                    'speed_limit_kmh' => $match['speed_limit_kmh'],
                    'duration_seconds' => $exceededSeconds,
                ]
            );
        }

        return response()->json($payload);
    }

    /**
     * Validate the request and persist a new record.
     */
    public function store(Request $request): JsonResponse
    {
        $driverId = Auth::user()?->isDriver() ? Auth::id() : null;

        if (! $driverId) {
            return response()->json([
                'reported' => false,
                'reason' => 'driver_authentication_required',
                'message' => 'Driver login is required before a report can be submitted.',
            ], 401);
        }

        $validated = $this->validateSpeedSample($request) + $request->validate([
            'rule_id' => ['required', 'integer', 'exists:segment_type_rules,id'],
            'segment_id' => ['required', 'integer', Rule::exists('road_segments', 'id')->whereNull('deleted_at')],
        ]);
        $accuracy = isset($validated['accuracy']) ? (float) $validated['accuracy'] : null;
        if (! $this->isAccuracyReliable($accuracy)) {
            return response()->json([
                'reported' => false,
                'reason' => 'low_accuracy',
                'accuracy_meters' => $accuracy,
                'required_accuracy_meters' => self::MAX_ACCEPTABLE_GPS_ACCURACY_METERS,
                'message' => 'GPS accuracy is too low for reliable report submission.',
            ], 409);
        }

        $match = $this->matchSpeedRule(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            (float) $accuracy
        );

        if (! $match || (int) $validated['rule_id'] !== (int) $match['rule']->id || (int) $validated['segment_id'] !== (int) $match['segment']->id) {
            $noParkingMatch = $this->matchNoParkingRule(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                (float) $accuracy
            );

            if (
                $noParkingMatch &&
                (int) $validated['rule_id'] === (int) $noParkingMatch['rule']->id &&
                (int) $validated['segment_id'] === (int) $noParkingMatch['segment']->id
            ) {
                return $this->storeNoParkingReport($validated, $noParkingMatch, $driverId);
            }

            return response()->json([
                'reported' => false,
                'reason' => 'rule_mismatch',
                'message' => 'The current location no longer matches that rule.',
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
        if (
            is_array($duplicate) &&
            ! empty($duplicate['reference_no']) &&
            now()->timestamp - (int) ($duplicate['reported_at'] ?? 0) < self::DUPLICATE_WINDOW_SECONDS
        ) {
            return response()->json([
                'reported' => true,
                'duplicate' => true,
                'reference_no' => $duplicate['reference_no'] ?? null,
                'driver_id' => $driverId,
                'submitted_by_user_id' => $driverId,
            ]);
        }

        $report = DB::transaction(function () use ($validated, $match, $speedKmh, $exceededSeconds, $driverId) {
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
                'driver_id' => $driverId,
                'submitted_by_user_id' => $driverId,
                'reporter_type' => 'driver',
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
            'driver_id' => $report->driver_id,
        ], 201);
    }

    public function createDriverReport(Request $request): View|RedirectResponse
    {
        $pending = $this->validDriverPendingViolation($request);

        if (! $pending) {
            return redirect()->route('home')
                ->with('status', 'No active driver violation is waiting for submission.');
        }

        return view('driver.report', ['pending' => $pending]);
    }

    public function storeDriverReport(Request $request): RedirectResponse
    {
        $pending = $this->validDriverPendingViolation($request);

        if (! $pending) {
            return redirect()->route('home')
                ->with('status', 'The driver report session expired. Please detect the violation again.');
        }

        $validated = $request->validate([
            'pending_token' => ['required', 'string', 'size:40'],
        ]);

        if (! hash_equals((string) $pending['token'], $validated['pending_token'])) {
            return back()->withErrors([
                'pending_token' => 'This driver report session is no longer valid.',
            ]);
        }

        $driverId = Auth::user()?->isDriver() ? (int) Auth::id() : null;

        if (! $driverId || (int) ($pending['driver_id'] ?? 0) !== $driverId) {
            $request->session()->forget(self::DRIVER_PENDING_SESSION_KEY);

            return redirect()->route('home')
                ->with('status', 'The driver report session no longer matches your account.');
        }

        $duplicate = session($this->reportedSessionKey((int) $pending['segment_id'], (int) $pending['rule_id']));
        if (
            is_array($duplicate) &&
            ! empty($duplicate['reference_no']) &&
            now()->timestamp - (int) ($duplicate['reported_at'] ?? 0) < self::DUPLICATE_WINDOW_SECONDS
        ) {
            $request->session()->forget(self::DRIVER_PENDING_SESSION_KEY);

            return redirect()->route('driver.reports.success')
                ->with(self::DRIVER_REPORT_REFERENCE_SESSION_KEY, $duplicate['reference_no'] ?? null)
                ->with(self::DRIVER_REPORT_DUPLICATE_SESSION_KEY, true);
        }

        $report = $this->createDriverReportFromPending($pending, $driverId);

        session()->put($this->reportedSessionKey((int) $pending['segment_id'], (int) $pending['rule_id']), [
            'reference_no' => $report->reference_no,
            'reported_at' => now()->timestamp,
        ]);

        $this->clearPendingViolationSession($pending);
        $request->session()->forget(self::DRIVER_PENDING_SESSION_KEY);

        return redirect()->route('driver.reports.success')
            ->with(self::DRIVER_REPORT_REFERENCE_SESSION_KEY, $report->reference_no)
            ->with(self::DRIVER_REPORT_DUPLICATE_SESSION_KEY, false);
    }

    public function driverReportSuccess(Request $request): View|RedirectResponse
    {
        $reference = $request->session()->get(self::DRIVER_REPORT_REFERENCE_SESSION_KEY);

        if (! $reference) {
            return redirect()->route('home');
        }

        return view('driver.success', [
            'reference' => $reference,
            'duplicate' => (bool) $request->session()->get(self::DRIVER_REPORT_DUPLICATE_SESSION_KEY, false),
        ]);
    }

    private function storeNoParkingReport(array $validated, array $match, int $driverId): JsonResponse
    {
        $speedKmh = (float) $validated['speed_kmh'];

        if ($speedKmh > self::NO_PARKING_STATIONARY_SPEED_KMH) {
            session()->forget($this->noParkingSessionKey($match['segment']->id, $match['rule']->id));

            return response()->json([
                'reported' => false,
                'reason' => 'vehicle_moving',
                'message' => 'The vehicle is moving inside the no parking area.',
            ], 409);
        }

        $startedAt = session($this->noParkingSessionKey($match['segment']->id, $match['rule']->id));
        $stationarySeconds = is_numeric($startedAt) ? max(0, now()->timestamp - (int) $startedAt) : 0;

        if ($stationarySeconds < self::REQUIRED_EXCEEDED_SECONDS) {
            return response()->json([
                'reported' => false,
                'reason' => 'duration_pending',
                'exceeded_seconds' => $stationarySeconds,
                'required_seconds' => self::REQUIRED_EXCEEDED_SECONDS,
            ], 409);
        }

        $report = DB::transaction(function () use ($validated, $match, $stationarySeconds, $driverId) {
            $violationType = ViolationType::firstOrCreate(
                ['name' => 'No Parking'],
                [
                    'description' => 'Vehicle parked or remained stationary in a no parking area.',
                    'is_active' => true,
                ]
            );

            $report = Report::create([
                'reference_no' => $this->makeReferenceNumber(),
                'violation_type_id' => $violationType->id,
                'description' => sprintf(
                    'Automatic no parking report: device remained stationary for %d seconds on %s.',
                    $stationarySeconds,
                    $match['segment']->segment_name
                ),
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'location_name' => $match['segment']->segment_name,
                'status' => 'submitted',
                'priority' => 'medium',
                'reported_at' => now(),
                'driver_id' => $driverId,
                'submitted_by_user_id' => $driverId,
                'reporter_type' => 'driver',
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

        session()->forget($this->noParkingSessionKey($match['segment']->id, $match['rule']->id));

        return response()->json([
            'reported' => true,
            'duplicate' => false,
            'reference_no' => $report->reference_no,
            'driver_id' => $report->driver_id,
        ], 201);
    }

    /**
     * Validate the speed/location sample used by automatic reporting.
     */
    private function validateSpeedSample(Request $request): array
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
            max(self::BASE_SEGMENT_TOLERANCE_METERS, $accuracy + self::ACCURACY_MARGIN_METERS)
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

            $distance = $this->distanceToSegmentGeometryMeters(
                $latitude,
                $longitude,
                $segment->boundary_coordinates ?? []
            );

            if (! is_finite($distance)) {
                continue;
            }

            $matchingBuffer = max($tolerance, $this->segmentBufferMeters($segment));
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

    private function matchNoParkingRule(float $latitude, float $longitude, float $accuracy): ?array
    {
        $cacheKey = 'auto_speed.no_parking_rules.snapshot';
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
        $tolerance = min(
            self::MAX_SEGMENT_TOLERANCE_METERS,
            max(self::BASE_SEGMENT_TOLERANCE_METERS, $accuracy + self::ACCURACY_MARGIN_METERS)
        );

        foreach ($segments as $segment) {
            $resolvedRule = $this->segmentRuleResolver->resolveNoParkingRuleForSegment($segment);
            if (! $resolvedRule) {
                continue;
            }

            $distance = $this->distanceToSegmentGeometryMeters(
                $latitude,
                $longitude,
                $segment->boundary_coordinates ?? []
            );

            if (! is_finite($distance)) {
                continue;
            }

            $matchingBuffer = max($tolerance, $this->segmentBufferMeters($segment));

            if ($distance > $matchingBuffer) {
                continue;
            }

            if (! $bestMatch || $distance < $bestMatch['distance_meters']) {
                $bestMatch = [
                    'rule' => $this->ruleDataObject($resolvedRule),
                    'segment' => $segment,
                    'distance_meters' => $distance,
                    'matching_buffer_meters' => $matchingBuffer,
                ];
            }
        }

        return $bestMatch;
    }

    private function ruleDataObject(array $resolvedRule): object
    {
        return (object) [
            'id' => (int) $resolvedRule['segment_type_rule_id'],
            'rule_name' => $resolvedRule['rule_name'],
            'rule_type' => $resolvedRule['rule_type'],
            'rule_value' => $resolvedRule['rule_value'],
            'description' => $resolvedRule['description'],
        ];
    }

    private function noParkingEvaluationPayload(array $match, float $speedKmh, array $validated): array
    {
        $sessionKey = $this->noParkingSessionKey($match['segment']->id, $match['rule']->id);
        $startedAt = session($sessionKey);

        if (! is_numeric($startedAt)) {
            $startedAt = now()->timestamp;
            session()->put($sessionKey, $startedAt);
        }

        $stationarySeconds = max(0, now()->timestamp - (int) $startedAt);

        $payload = [
            'matched' => true,
            'has_speed_rule' => false,
            'is_no_parking_rule' => true,
            'requires_stationary' => true,
            'exceeded' => true,
            'can_submit' => $stationarySeconds >= self::REQUIRED_EXCEEDED_SECONDS,
            'exceeded_seconds' => $stationarySeconds,
            'required_seconds' => self::REQUIRED_EXCEEDED_SECONDS,
            'distance_meters' => round($match['distance_meters'], 1),
            'matching_buffer_meters' => round($match['matching_buffer_meters'], 1),
            'speed_kmh' => round($speedKmh, 1),
            'speed_limit_kmh' => null,
            'segment' => [
                'id' => $match['segment']->id,
                'name' => $match['segment']->segment_name,
                'db_name' => $match['segment']->segment_name,
            ],
            'rule' => [
                'id' => $match['rule']->id,
                'name' => $match['rule']->rule_name,
                'type' => $match['rule']->rule_type,
                'value' => $match['rule']->rule_value,
                'display' => $this->formatRuleDisplay([
                    'rule_name' => $match['rule']->rule_name,
                    'rule_value' => $match['rule']->rule_value,
                ]),
            ],
            'reporting' => $this->reportingSnapshot((int) $match['segment']->id, (int) $match['rule']->id),
        ];

        if ($payload['can_submit']) {
            $payload = $this->withViolationContinuation(
                $payload,
                $validated,
                $match,
                'No Parking',
                'Vehicle parked or remained stationary in a no parking area.',
                sprintf(
                    'Passenger-observed no parking violation: vehicle remained stationary for %d seconds on %s.',
                    $stationarySeconds,
                    $match['segment']->segment_name
                ),
                'medium',
                [
                    'speed_kmh' => $speedKmh,
                    'speed_limit_kmh' => null,
                    'duration_seconds' => $stationarySeconds,
                ]
            );
        }

        return $payload;
    }

    private function withViolationContinuation(
        array $payload,
        array $validated,
        array $match,
        string $violationType,
        string $violationDescription,
        string $description,
        string $priority,
        array $metrics = []
    ): array {
        $pending = array_merge(
            [
                'token' => Str::random(40),
                'expires_at' => now()->addMinutes(10)->timestamp,
            ],
            $this->pendingViolationData(
                $validated,
                $match,
                $violationType,
                $violationDescription,
                $description,
                $priority,
                $metrics
            )
        );

        if (Auth::user()?->isDriver()) {
            $pending['driver_id'] = (int) Auth::id();

            session()->put(self::DRIVER_PENDING_SESSION_KEY, $pending);

            $payload['driver_report_url'] = route('driver.reports.create');
            $payload['report_mode'] = 'driver_confirmation_required';

            return $payload;
        }

        $recentPassengerReport = $this->recentPassengerReportForRule(
            (int) $pending['segment_id'],
            (int) $pending['rule_id']
        );

        if ($recentPassengerReport) {
            $payload['can_submit'] = false;
            $payload['duplicate'] = true;
            $payload['reference_no'] = $recentPassengerReport['reference_no'] ?? null;
            $payload['report_mode'] = 'passenger_recently_submitted';
            $payload['message'] = 'Passenger report for this rule was already submitted recently.';

            return $payload;
        }

        session()->put('passenger.pending_violation', $pending);

        $payload['passenger_report_url'] = route('passenger.reports.create');
        $payload['report_mode'] = 'passenger_details_required';

        return $payload;
    }

    private function pendingViolationData(
        array $validated,
        array $match,
        string $violationType,
        string $violationDescription,
        string $description,
        string $priority,
        array $metrics = []
    ): array {
        return [
            'violation_type' => $violationType,
            'violation_description' => $violationDescription,
            'description' => $description,
            'latitude' => (float) $validated['latitude'],
            'longitude' => (float) $validated['longitude'],
            'speed_kmh' => isset($metrics['speed_kmh']) && is_numeric($metrics['speed_kmh'])
                ? round((float) $metrics['speed_kmh'], 1)
                : round((float) ($validated['speed_kmh'] ?? 0), 1),
            'speed_limit_kmh' => isset($metrics['speed_limit_kmh']) && is_numeric($metrics['speed_limit_kmh'])
                ? round((float) $metrics['speed_limit_kmh'], 1)
                : null,
            'duration_seconds' => max(0, (int) ($metrics['duration_seconds'] ?? 0)),
            'location_name' => $match['segment']->segment_name,
            'priority' => $priority,
            'segment_id' => (int) $match['segment']->id,
            'rule_id' => (int) $match['rule']->id,
            'rule_name' => $match['rule']->rule_name,
            'rule_type' => $match['rule']->rule_type,
            'rule_value' => $match['rule']->rule_value,
            'rule_description' => $match['rule']->description,
            'confidence_score' => $this->confidenceForDistance($match['distance_meters']),
        ];
    }

    private function validDriverPendingViolation(Request $request): ?array
    {
        $pending = $request->session()->get(self::DRIVER_PENDING_SESSION_KEY);
        $requiredFields = [
            'token',
            'expires_at',
            'driver_id',
            'violation_type',
            'violation_description',
            'description',
            'latitude',
            'longitude',
            'location_name',
            'priority',
            'segment_id',
            'rule_id',
            'rule_name',
            'rule_type',
            'rule_value',
            'confidence_score',
        ];

        if (! is_array($pending) || (int) ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget(self::DRIVER_PENDING_SESSION_KEY);

            return null;
        }

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $pending)) {
                $request->session()->forget(self::DRIVER_PENDING_SESSION_KEY);

                return null;
            }
        }

        if ((int) ($pending['driver_id'] ?? 0) !== (int) Auth::id()) {
            $request->session()->forget(self::DRIVER_PENDING_SESSION_KEY);

            return null;
        }

        return $pending;
    }

    private function createDriverReportFromPending(array $pending, int $driverId): Report
    {
        return DB::transaction(function () use ($pending, $driverId) {
            $violationType = ViolationType::firstOrCreate(
                ['name' => $pending['violation_type']],
                [
                    'description' => $pending['violation_description'],
                    'is_active' => true,
                ]
            );

            $report = Report::create([
                'reference_no' => $this->makeReferenceNumber(),
                'violation_type_id' => $violationType->id,
                'description' => $pending['description'],
                'latitude' => $pending['latitude'],
                'longitude' => $pending['longitude'],
                'location_name' => $pending['location_name'],
                'status' => 'submitted',
                'priority' => $pending['priority'],
                'reported_at' => now(),
                'driver_id' => $driverId,
                'submitted_by_user_id' => $driverId,
                'reporter_type' => 'driver',
            ]);

            RuleViolation::create([
                'report_id' => $report->id,
                'segment_id' => (int) $pending['segment_id'],
                'segment_type_rule_id' => (int) $pending['rule_id'],
                'rule_name_snapshot' => $pending['rule_name'],
                'rule_type_snapshot' => $pending['rule_type'],
                'rule_value_snapshot' => $pending['rule_value'],
                'rule_description_snapshot' => $pending['rule_description'] ?? null,
                'matched_automatically' => true,
                'confidence_score' => (float) $pending['confidence_score'],
            ]);

            return $report;
        });
    }

    private function clearPendingViolationSession(array $pending): void
    {
        if (! isset($pending['segment_id'], $pending['rule_id'])) {
            return;
        }

        session()->forget([
            $this->exceededSessionKey((int) $pending['segment_id'], (int) $pending['rule_id']),
            $this->noParkingSessionKey((int) $pending['segment_id'], (int) $pending['rule_id']),
        ]);
    }

    private function matchRoadSegmentGeometry(float $latitude, float $longitude, float $accuracy): ?array
    {
        $cacheKey = 'auto_speed.road_segments.geometry.snapshot';
        $segments = Cache::remember($cacheKey, now()->addSeconds(self::ACTIVE_RULES_CACHE_SECONDS), function () {
            return RoadSegment::query()
                ->whereNotNull('boundary_coordinates')
                ->with('segmentType:id,name')
                ->get(['id', 'segment_name', 'segment_type_id', 'boundary_coordinates']);
        });

        $bestMatch = null;
        $tolerance = min(
            self::MAX_SEGMENT_TOLERANCE_METERS,
            max(self::BASE_SEGMENT_TOLERANCE_METERS, $accuracy + self::ACCURACY_MARGIN_METERS)
        );

        foreach ($segments as $segment) {
            $distance = $this->distanceToSegmentGeometryMeters(
                $latitude,
                $longitude,
                $segment->boundary_coordinates ?? []
            );

            if (! is_finite($distance)) {
                continue;
            }

            $matchingBuffer = max($tolerance, $this->segmentBufferMeters($segment));

            if ($distance > $matchingBuffer) {
                continue;
            }

            if (! $bestMatch || $distance < $bestMatch['distance_meters']) {
                $bestMatch = [
                    'segment' => $segment,
                    'distance_meters' => $distance,
                    'matching_buffer_meters' => $matchingBuffer,
                ];
            }
        }

        return $bestMatch;
    }

    private function displayRuleForSegment(RoadSegment $segment): ?array
    {
        $rule = $this->segmentRuleResolver->resolveNoParkingRuleForSegment($segment)
            ?? $this->segmentRuleResolver->resolveEffectiveRulesForSegment($segment)->first();

        if (! $rule) {
            return null;
        }

        return [
            'id' => (int) $rule['segment_type_rule_id'],
            'name' => $rule['rule_name'],
            'type' => $rule['rule_type'],
            'value' => $rule['rule_value'],
            'description' => $rule['description'],
            'display' => $this->formatRuleDisplay($rule),
        ];
    }

    private function formatRuleDisplay(array $rule): string
    {
        $name = trim((string) ($rule['rule_name'] ?? ''));
        $value = trim((string) ($rule['rule_value'] ?? ''));

        if ($name !== '' && $value !== '' && strtolower($name) !== strtolower($value)) {
            return sprintf('%s - %s', strtoupper($name), strtoupper($value));
        }

        return strtoupper($name !== '' ? $name : ($value !== '' ? $value : 'CONFIGURED'));
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
     * Require GPS precision good enough for realistic segment matching.
     */
    private function isAccuracyReliable(?float $accuracy): bool
    {
        if (! is_finite((float) $accuracy)) {
            return false;
        }

        return $accuracy > 0 && $accuracy <= self::MAX_ACCEPTABLE_GPS_ACCURACY_METERS;
    }

    private function distanceToSegmentGeometryMeters(float $latitude, float $longitude, ?array $geometry): float
    {
        if (! is_array($geometry) || $geometry === []) {
            return INF;
        }

        $coordinates = data_get($geometry, 'geometry.coordinates')
            ?? data_get($geometry, 'features.0.geometry.coordinates')
            ?? data_get($geometry, 'coordinates')
            ?? $geometry;
        $geometryType = strtolower((string) (
            data_get($geometry, 'geometry.type')
            ?? data_get($geometry, 'features.0.geometry.type')
            ?? data_get($geometry, 'type')
            ?? ''
        ));

        if (! is_array($coordinates) || $coordinates === []) {
            return INF;
        }

        $target = ['lat' => $latitude, 'lng' => $longitude];
        $minimum = INF;

        foreach ($this->coordinateLines($coordinates) as $linePoints) {
            if (str_contains($geometryType, 'polygon') && count($linePoints) >= 3 && $this->pointInsidePolygon($target, $linePoints)) {
                return 0.0;
            }

            if (count($linePoints) < 2) {
                continue;
            }

            $minimum = min($minimum, $this->distanceToPolylineMeters($target, $linePoints));
        }

        return $minimum;
    }

    private function coordinateLines(array $coordinates): array
    {
        if ($this->isCoordinatePair($coordinates)) {
            $point = $this->normalizeCoordinate($coordinates);

            return $point ? [[$point]] : [];
        }

        $looksLikeLine = $coordinates !== [] && collect($coordinates)
            ->every(fn ($coordinate): bool => $this->isCoordinatePair($coordinate));

        if ($looksLikeLine) {
            $line = collect($coordinates)
                ->map(fn ($coordinate) => $this->normalizeCoordinate($coordinate))
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

    private function pointInsidePolygon(array $point, array $polygonPoints): bool
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
        return sprintf('auto_speed.exceeded.%d.%d.%d', $this->authenticatedDriverId(), $segmentId, $ruleId);
    }

    private function noParkingSessionKey(int $segmentId, int $ruleId): string
    {
        return sprintf('auto_no_parking.stationary.%d.%d.%d', $this->authenticatedDriverId(), $segmentId, $ruleId);
    }

    /**
     * Handle the reportedSessionKey workflow for this class.
     */
    private function reportedSessionKey(int $segmentId, int $ruleId): string
    {
        return sprintf('auto_speed.reported.%d.%d.%d', $this->authenticatedDriverId(), $segmentId, $ruleId);
    }

    private function recentPassengerReportForRule(int $segmentId, int $ruleId): ?array
    {
        $reported = session($this->reportedSessionKey($segmentId, $ruleId));

        if (
            ! is_array($reported) ||
            empty($reported['reference_no']) ||
            now()->timestamp - (int) ($reported['reported_at'] ?? 0) >= self::DUPLICATE_WINDOW_SECONDS
        ) {
            return null;
        }

        return $reported;
    }

    private function authenticatedDriverId(): int
    {
        return Auth::user()?->isDriver() ? (int) Auth::id() : 0;
    }

    /**
     * Handle the clearExceededSession workflow for this class.
     */
    private function clearExceededSession(): void
    {
        foreach (array_keys(session()->all()) as $key) {
            if (str_starts_with($key, 'auto_speed.exceeded.') || str_starts_with($key, 'auto_no_parking.stationary.')) {
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
