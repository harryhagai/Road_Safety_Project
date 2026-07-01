@extends('layouts.app')

@section('title', 'Driver Violation Report - RSRS')

@section('content')
    @php
        $speed = number_format((float) ($pending['speed_kmh'] ?? 0), 1);
        $speedLimit = isset($pending['speed_limit_kmh']) && $pending['speed_limit_kmh'] !== null
            ? number_format((float) $pending['speed_limit_kmh'], 1).' km/h'
            : 'Not applicable';
        $duration = max(0, (int) ($pending['duration_seconds'] ?? 0));
    @endphp

    <div class="passenger-report-page driver-report-page container-xl px-3 py-4">
        <section class="passenger-report-shell driver-report-shell">
            <aside class="passenger-report-summary">
                <span class="passenger-report-eyebrow"><i class="bi bi-speedometer2"></i> Violation detected</span>
                <h1>Driver report submission</h1>
                <p>RSRS captured the violation details automatically and is submitting the report to road officers.</p>

                <div class="passenger-report-detected">
                    <div><span>Violation</span><strong>{{ $pending['violation_type'] }}</strong></div>
                    <div><span>Location</span><strong>{{ $pending['location_name'] }}</strong></div>
                    <div><span>Observed speed</span><strong>{{ $speed }} km/h</strong></div>
                    <div><span>Rule</span><strong>{{ $pending['rule_name'] }} @if($speedLimit !== 'Not applicable') - {{ $speedLimit }} @endif</strong></div>
                    <div><span>Duration</span><strong>{{ $duration }} seconds</strong></div>
                    <div><span>Coordinates</span><strong>{{ number_format($pending['latitude'], 6) }}, {{ number_format($pending['longitude'], 6) }}</strong></div>
                    <div><span>Session expires</span><strong>{{ \Illuminate\Support\Carbon::createFromTimestamp($pending['expires_at'])->diffForHumans() }}</strong></div>
                </div>
            </aside>

            <div class="passenger-report-form-panel">
                <div class="passenger-report-heading">
                    <span><i class="bi bi-shield-check"></i> Driver report</span>
                    <h2>Submitting captured violation</h2>
                    <p>No extra details are required. The report will be linked to your driver account and vehicle profile.</p>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('driver.reports.store') }}" class="passenger-report-form driver-report-form" data-driver-auto-submit-form>
                    @csrf
                    <input type="hidden" name="pending_token" value="{{ $pending['token'] }}">

                    <div class="driver-report-confirmation" aria-live="polite">
                        <span class="driver-report-spinner" aria-hidden="true"></span>
                        <div>
                            <strong>Submitting automatically</strong>
                            <span>Reference number will be generated after submission.</span>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rsrsPassengerReport.css') }}?v={{ filemtime(public_path('css/rsrsPassengerReport.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/rsrsDriverReport.css') }}?v={{ filemtime(public_path('css/rsrsDriverReport.css')) }}">
@endpush

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-driver-auto-submit-form]');

            if (!form || form.dataset.submitted === '1') {
                return;
            }

            form.dataset.submitted = '1';
            window.setTimeout(() => form.submit(), 450);
        })();
    </script>
@endpush
