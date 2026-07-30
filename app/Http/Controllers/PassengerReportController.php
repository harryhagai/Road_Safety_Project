<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\RuleViolation;
use App\Models\User;
use App\Models\ViolationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PassengerReportController extends Controller
{
    private const PENDING_SESSION_KEY = 'passenger.pending_violation';

    private const DUPLICATE_WINDOW_SECONDS = 600;

    public function create(Request $request): View|RedirectResponse
    {
        $pending = $this->validPendingViolation($request);

        if (! $pending) {
            return redirect()->route('home')
                ->with('status', 'No active passenger violation is waiting for bus details.');
        }

        return view('passenger.report', ['pending' => $pending]);
    }

    public function busSuggestions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        if (Str::length($search) < 2) {
            return response()->json(['data' => []]);
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

        $drivers = User::query()
            ->select(['id', 'name', 'vehicle_name', 'plate_number', 'organization'])
            ->where('role', User::ROLE_DRIVER)
            ->where('is_active', true)
            ->where(function ($query) use ($like): void {
                $query
                    ->where('organization', 'like', $like)
                    ->orWhere('vehicle_name', 'like', $like)
                    ->orWhere('plate_number', 'like', $like);
            })
            ->orderByRaw(
                'CASE WHEN plate_number LIKE ? THEN 0 WHEN organization LIKE ? THEN 1 ELSE 2 END',
                [$like, $like]
            )
            ->orderBy('organization')
            ->limit(8)
            ->get();

        return response()->json([
            'data' => $drivers->map(fn (User $driver): array => [
                'id' => $driver->id,
                'operator' => $driver->organization ?: $driver->vehicle_name ?: $driver->name,
                'vehicle' => $driver->vehicle_name,
                'plate_number' => $driver->plate_number,
                'label' => trim(sprintf(
                    '%s %s',
                    $driver->organization ?: $driver->vehicle_name ?: $driver->name,
                    $driver->plate_number ? '('.$driver->plate_number.')' : ''
                )),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $pending = $this->validPendingViolation($request);

        if (! $pending) {
            return redirect()->route('home')
                ->with('status', 'The passenger report session expired. Please detect the violation again.');
        }

        $validated = $request->validate([
            'pending_token' => ['required', 'string', 'size:40'],
            'bus_operator' => ['required', 'string', 'max:191'],
            'bus_plate_number' => ['required', 'string', 'max:50'],
            'bus_route' => ['nullable', 'string', 'max:191'],
            'passenger_name' => ['nullable', 'string', 'max:191'],
            'passenger_phone' => ['nullable', 'string', 'max:50'],
            'passenger_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! hash_equals((string) $pending['token'], $validated['pending_token'])) {
            throw ValidationException::withMessages([
                'pending_token' => 'This passenger report session is no longer valid.',
            ]);
        }

        $plateNumber = Str::upper(preg_replace('/\s+/', ' ', trim($validated['bus_plate_number'])) ?? '');

        $report = DB::transaction(function () use ($request, $pending, $validated, $plateNumber) {
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
                'driver_id' => null,
                'submitted_by_user_id' => $request->user()?->id,
                'reporter_type' => 'passenger',
                'bus_operator' => trim($validated['bus_operator']),
                'bus_plate_number' => $plateNumber,
                'bus_route' => $this->optionalText($validated['bus_route'] ?? null),
                'passenger_name' => $this->optionalText($validated['passenger_name'] ?? null),
                'passenger_phone' => $this->optionalText($validated['passenger_phone'] ?? null),
                'passenger_notes' => $this->optionalText($validated['passenger_notes'] ?? null),
            ]);

            RuleViolation::create([
                'report_id' => $report->id,
                'segment_id' => $pending['segment_id'],
                'segment_type_rule_id' => $pending['rule_id'],
                'rule_name_snapshot' => $pending['rule_name'],
                'rule_type_snapshot' => $pending['rule_type'],
                'rule_value_snapshot' => $pending['rule_value'],
                'rule_description_snapshot' => $pending['rule_description'],
                'matched_automatically' => true,
                'confidence_score' => $pending['confidence_score'],
            ]);

            return $report;
        });

        $this->rememberSubmittedRule($request, $pending, $report->reference_no);
        $this->clearDetectedRuleSession($request, $pending);
        $request->session()->forget(self::PENDING_SESSION_KEY);

        return redirect()->route('passenger.reports.success')
            ->with('passenger_report_reference', $report->reference_no);
    }

    public function success(Request $request): View|RedirectResponse
    {
        $reference = $request->session()->get('passenger_report_reference');

        if (! $reference) {
            return redirect()->route('home');
        }

        return view('passenger.success', ['reference' => $reference]);
    }

    private function validPendingViolation(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_SESSION_KEY);

        if (! is_array($pending) || (int) ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget(self::PENDING_SESSION_KEY);

            return null;
        }

        return $pending;
    }

    private function optionalText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function rememberSubmittedRule(Request $request, array $pending, string $referenceNo): void
    {
        if (! isset($pending['segment_id'], $pending['rule_id'])) {
            return;
        }

        $request->session()->put(
            $this->reportedSessionKey((int) $pending['segment_id'], (int) $pending['rule_id']),
            [
                'reference_no' => $referenceNo,
                'reported_at' => now()->timestamp,
                'expires_at' => now()->addSeconds(self::DUPLICATE_WINDOW_SECONDS)->timestamp,
            ]
        );
    }

    private function clearDetectedRuleSession(Request $request, array $pending): void
    {
        if (! isset($pending['segment_id'], $pending['rule_id'])) {
            return;
        }

        $segmentId = (int) $pending['segment_id'];
        $ruleId = (int) $pending['rule_id'];

        $request->session()->forget([
            sprintf('auto_speed.exceeded.%d.%d.%d', 0, $segmentId, $ruleId),
            sprintf('auto_no_parking.stationary.%d.%d.%d', 0, $segmentId, $ruleId),
        ]);
    }

    private function reportedSessionKey(int $segmentId, int $ruleId): string
    {
        return sprintf('auto_speed.reported.%d.%d.%d', 0, $segmentId, $ruleId);
    }

    private function makeReferenceNumber(): string
    {
        do {
            $referenceNo = 'RPT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Report::where('reference_no', $referenceNo)->exists());

        return $referenceNo;
    }
}
