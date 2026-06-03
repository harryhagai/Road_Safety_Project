<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PassengerTrip;
use App\Models\Report;
use App\Models\TripTelemetry;
use App\Models\TripViolation;
use App\Models\VehicleTelemetry;
use App\Models\ViolationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PassengerTripController extends Controller
{
    private const MAX_TRIP_HOURS = 8;
    private const TELEMETRY_INTERVAL_SECONDS = 30;

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:120'],
            'route_name' => ['nullable', 'string', 'max:255'],
            'started_at' => ['nullable', 'date'],
            'start_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'start_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'metadata' => ['nullable', 'array'],
        ]);

        $deviceId = trim($validated['device_id']);
        $startedAt = isset($validated['started_at'])
            ? Carbon::parse($validated['started_at'])
            : now();

        $this->expireOldTrips($deviceId);

        $existingTrip = PassengerTrip::query()
            ->where('device_id', $deviceId)
            ->where('status', PassengerTrip::STATUS_ACTIVE)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($existingTrip) {
            return response()->json([
                'saved' => true,
                'resumed' => true,
                'trip' => $this->tripPayload($existingTrip),
            ]);
        }

        $trip = PassengerTrip::create([
            'public_reference' => $this->makeTripReference(),
            'device_id' => $deviceId,
            'route_name' => $validated['route_name'] ?? null,
            'status' => PassengerTrip::STATUS_ACTIVE,
            'started_at' => $startedAt,
            'expires_at' => $startedAt->copy()->addHours(self::MAX_TRIP_HOURS),
            'start_latitude' => $validated['start_latitude'] ?? null,
            'start_longitude' => $validated['start_longitude'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        return response()->json([
            'saved' => true,
            'resumed' => false,
            'trip' => $this->tripPayload($trip),
        ], 201);
    }

    public function telemetry(Request $request, PassengerTrip $trip): JsonResponse
    {
        if (! $this->ensureTripCanReceiveUpdates($trip)) {
            return response()->json([
                'saved' => false,
                'message' => 'Trip is not active.',
                'trip' => $this->tripPayload($trip->fresh()),
            ], 409);
        }

        $validated = $request->validate([
            'recorded_at' => ['nullable', 'date'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'speed_kmh' => ['nullable', 'numeric', 'min:0', 'max:320'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'network_type' => ['nullable', 'string', 'max:40'],
        ]);

        $telemetry = DB::transaction(function () use ($trip, $validated): TripTelemetry {
            $telemetry = TripTelemetry::create([
                'trip_id' => $trip->id,
                'recorded_at' => isset($validated['recorded_at']) ? Carbon::parse($validated['recorded_at']) : now(),
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'speed_kmh' => $validated['speed_kmh'] ?? 0,
                'accuracy_meters' => $validated['accuracy_meters'] ?? null,
                'battery_level' => $validated['battery_level'] ?? null,
                'network_type' => $validated['network_type'] ?? null,
            ]);

            VehicleTelemetry::create([
                'citizen_device_no' => 'TRIP-'.$trip->public_reference,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'current_speed' => $validated['speed_kmh'] ?? 0,
                'heading' => null,
                'segment_id' => null,
            ]);

            return $telemetry;
        });

        return response()->json([
            'saved' => true,
            'telemetry_id' => $telemetry->id,
            'trip' => $this->tripPayload($trip->fresh()),
        ], 201);
    }

    public function violation(Request $request, PassengerTrip $trip): JsonResponse
    {
        if (! $this->ensureTripCanReceiveUpdates($trip)) {
            return response()->json([
                'saved' => false,
                'message' => 'Trip is not active.',
                'trip' => $this->tripPayload($trip->fresh()),
            ], 409);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:5000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $violation = DB::transaction(function () use ($trip, $validated): TripViolation {
            $violationType = $this->resolveViolationType($validated['type']);
            $reportedAt = isset($validated['recorded_at']) ? Carbon::parse($validated['recorded_at']) : now();
            $description = $validated['description']
                ?: 'Passenger submitted a '.$this->humanizeViolationType($validated['type']).' report from Android trip tracking.';

            $report = Report::create([
                'reference_no' => $this->makeReportReference(),
                'violation_type_id' => $violationType->id,
                'description' => $description,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'location_name' => $trip->route_name ? 'Passenger trip: '.$trip->route_name : 'Passenger Android trip',
                'status' => 'submitted',
                'priority' => $this->priorityForViolation($validated['type']),
                'reported_at' => $reportedAt,
            ]);

            return TripViolation::create([
                'trip_id' => $trip->id,
                'report_id' => $report->id,
                'type' => $validated['type'],
                'description' => $description,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'recorded_at' => $reportedAt,
                'status' => 'submitted',
            ]);
        });

        return response()->json([
            'saved' => true,
            'violation_id' => $violation->id,
            'report_id' => $violation->report_id,
            'report_reference_no' => $violation->report?->reference_no,
            'trip' => $this->tripPayload($trip->fresh()),
        ], 201);
    }

    public function stop(Request $request, PassengerTrip $trip): JsonResponse
    {
        $validated = $request->validate([
            'ended_at' => ['nullable', 'date'],
            'end_reason' => ['nullable', 'string', Rule::in([
                PassengerTrip::STATUS_COMPLETED,
                PassengerTrip::STATUS_EXPIRED,
                PassengerTrip::STATUS_CANCELLED,
                PassengerTrip::STATUS_FAILED,
            ])],
            'end_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'end_longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        if ($trip->status === PassengerTrip::STATUS_ACTIVE) {
            $endReason = $validated['end_reason'] ?? PassengerTrip::STATUS_COMPLETED;
            $trip->update([
                'status' => $endReason,
                'ended_at' => isset($validated['ended_at']) ? Carbon::parse($validated['ended_at']) : now(),
                'end_reason' => $endReason,
                'end_latitude' => $validated['end_latitude'] ?? null,
                'end_longitude' => $validated['end_longitude'] ?? null,
            ]);
        }

        return response()->json([
            'saved' => true,
            'trip' => $this->tripPayload($trip->fresh()),
        ]);
    }

    public function status(PassengerTrip $trip): JsonResponse
    {
        $this->ensureTripCanReceiveUpdates($trip);
        $trip->refresh();

        return response()->json([
            'trip' => $this->tripPayload($trip),
            'telemetry_count' => $trip->telemetry()->count(),
            'violation_count' => $trip->violations()->count(),
            'latest_telemetry' => $trip->telemetry()
                ->latest('recorded_at')
                ->first(['id', 'recorded_at', 'latitude', 'longitude', 'speed_kmh', 'accuracy_meters', 'battery_level', 'network_type']),
        ]);
    }

    private function ensureTripCanReceiveUpdates(PassengerTrip $trip): bool
    {
        if ($trip->status !== PassengerTrip::STATUS_ACTIVE) {
            return false;
        }

        if ($trip->expires_at && $trip->expires_at->isPast()) {
            $trip->update([
                'status' => PassengerTrip::STATUS_EXPIRED,
                'ended_at' => now(),
                'end_reason' => PassengerTrip::STATUS_EXPIRED,
            ]);

            return false;
        }

        return true;
    }

    private function expireOldTrips(string $deviceId): void
    {
        PassengerTrip::query()
            ->where('device_id', $deviceId)
            ->where('status', PassengerTrip::STATUS_ACTIVE)
            ->where('expires_at', '<=', now())
            ->update([
                'status' => PassengerTrip::STATUS_EXPIRED,
                'ended_at' => now(),
                'end_reason' => PassengerTrip::STATUS_EXPIRED,
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tripPayload(PassengerTrip $trip): array
    {
        return [
            'id' => $trip->id,
            'public_reference' => $trip->public_reference,
            'device_id' => $trip->device_id,
            'route_name' => $trip->route_name,
            'status' => $trip->status,
            'started_at' => $trip->started_at?->toIso8601String(),
            'ended_at' => $trip->ended_at?->toIso8601String(),
            'expires_at' => $trip->expires_at?->toIso8601String(),
            'max_duration_hours' => self::MAX_TRIP_HOURS,
            'telemetry_interval_seconds' => self::TELEMETRY_INTERVAL_SECONDS,
            'web_links' => [
                'home' => url('/'),
                'about' => url('/about'),
                'privacy' => url('/privacy'),
                'help' => url('/contact'),
                'full_site' => url('/'),
            ],
        ];
    }

    private function resolveViolationType(string $type): ViolationType
    {
        $name = match (Str::slug($type, '_')) {
            'overspeeding', 'speeding' => 'Overspeeding',
            'unsafe_overtaking', 'dangerous_overtaking' => 'Dangerous Overtaking',
            'drunk_driving' => 'Drunk Driving',
            'overloading' => 'Overloading',
            'road_damage' => 'Road Damage',
            'traffic_obstruction' => 'Traffic Obstruction',
            'reckless_driving' => 'Reckless Driving',
            default => 'Other Passenger Report',
        };

        return ViolationType::firstOrCreate(
            ['name' => $name],
            [
                'description' => 'Passenger-submitted Android trip report.',
                'is_active' => true,
            ],
        );
    }

    private function priorityForViolation(string $type): string
    {
        return in_array(Str::slug($type, '_'), ['overspeeding', 'reckless_driving', 'unsafe_overtaking', 'dangerous_overtaking'], true)
            ? 'high'
            : 'normal';
    }

    private function humanizeViolationType(string $type): string
    {
        return Str::of($type)->replace(['_', '-'], ' ')->headline()->lower()->toString();
    }

    private function makeTripReference(): string
    {
        do {
            $referenceNo = 'TRP-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (PassengerTrip::where('public_reference', $referenceNo)->exists());

        return $referenceNo;
    }

    private function makeReportReference(): string
    {
        do {
            $referenceNo = 'RPT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Report::where('reference_no', $referenceNo)->exists());

        return $referenceNo;
    }
}
