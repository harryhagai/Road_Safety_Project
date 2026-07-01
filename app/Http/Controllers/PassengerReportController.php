<?php

namespace App\Http\Controllers;

use App\Models\EvidenceFile;
use App\Models\Report;
use App\Models\RuleViolation;
use App\Models\ViolationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PassengerReportController extends Controller
{
    private const PENDING_SESSION_KEY = 'passenger.pending_violation';

    private const MAX_EVIDENCE_BYTES = 3_000_000;

    public function create(Request $request): View|RedirectResponse
    {
        $pending = $this->validPendingViolation($request);

        if (! $pending) {
            return redirect()->route('home')
                ->with('status', 'No active passenger violation is waiting for bus details.');
        }

        return view('passenger.report', ['pending' => $pending]);
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
            'evidence_image' => ['nullable', 'string', 'max:4100000'],
        ]);

        if (! hash_equals((string) $pending['token'], $validated['pending_token'])) {
            throw ValidationException::withMessages([
                'pending_token' => 'This passenger report session is no longer valid.',
            ]);
        }

        $evidenceImage = $validated['evidence_image'] ?? null;
        [$imageData, $mimeType] = is_string($evidenceImage) && trim($evidenceImage) !== ''
            ? $this->decodeEvidenceImage($evidenceImage)
            : [null, null];
        $plateNumber = Str::upper(preg_replace('/\s+/', ' ', trim($validated['bus_plate_number'])) ?? '');
        $extension = $mimeType
            ? match ($mimeType) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            }
            : null;

        $report = DB::transaction(function () use ($request, $pending, $validated, $imageData, $mimeType, $plateNumber, $extension) {
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

            if ($imageData !== null && $mimeType !== null && $extension !== null) {
                EvidenceFile::create([
                    'report_id' => $report->id,
                    'file_name' => 'passenger-evidence-'.$report->reference_no.'.'.$extension,
                    'file_path' => null,
                    'file_data' => $imageData,
                    'file_type' => $mimeType,
                    'file_size' => strlen($imageData),
                ]);
            }

            return $report;
        });

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

    /**
     * @return array{0: string, 1: string}
     */
    private function decodeEvidenceImage(string $dataUrl): array
    {
        if (! preg_match('/^data:(image\/(?:jpeg|png|webp));base64,([A-Za-z0-9+\/=\r\n]+)$/', $dataUrl, $matches)) {
            throw ValidationException::withMessages([
                'evidence_image' => 'Capture a valid image using the camera.',
            ]);
        }

        $imageData = base64_decode($matches[2], true);

        if ($imageData === false || strlen($imageData) === 0) {
            throw ValidationException::withMessages([
                'evidence_image' => 'The captured image could not be read.',
            ]);
        }

        if (strlen($imageData) > self::MAX_EVIDENCE_BYTES) {
            throw ValidationException::withMessages([
                'evidence_image' => 'The captured image is too large. Capture it again.',
            ]);
        }

        $imageInfo = @getimagesizefromstring($imageData);

        if (! is_array($imageInfo) || ($imageInfo['mime'] ?? null) !== $matches[1]) {
            throw ValidationException::withMessages([
                'evidence_image' => 'The captured evidence is not a valid image.',
            ]);
        }

        return [$imageData, $matches[1]];
    }

    private function optionalText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function makeReferenceNumber(): string
    {
        do {
            $referenceNo = 'RPT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Report::where('reference_no', $referenceNo)->exists());

        return $referenceNo;
    }
}
