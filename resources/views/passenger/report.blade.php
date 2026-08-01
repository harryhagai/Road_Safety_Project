@extends('layouts.app')

@section('title', 'Passenger Violation Report - RSRS')

@section('content')
    @php
        $sessionExpiresAt = \Illuminate\Support\Carbon::createFromTimestamp((int) $pending['expires_at']);
    @endphp

    <div class="passenger-report-page container-xl px-3 py-4">
        <section class="passenger-report-shell">
            <aside class="passenger-report-summary">
                <span class="passenger-report-eyebrow"><i class="bi bi-exclamation-triangle"></i> Violation detected</span>
                <h1>Help identify the bus</h1>
                <p>The violation has been detected. Add the bus details before submitting.</p>

                <div class="passenger-report-detected">
                    <div><span>Violation</span><strong>{{ $pending['violation_type'] }}</strong></div>
                    <div><span>Location</span><strong>{{ $pending['location_name'] }}</strong></div>
                    <div><span>Coordinates</span><strong>{{ number_format($pending['latitude'], 6) }}, {{ number_format($pending['longitude'], 6) }}</strong></div>
                    <div class="passenger-session-countdown-card">
                        <span>Session expires</span>
                        <strong>
                            <time
                                class="passenger-session-countdown"
                                datetime="{{ $sessionExpiresAt->toIso8601String() }}"
                                data-session-countdown
                                data-expires-at="{{ (int) $pending['expires_at'] }}"
                                data-expired-redirect="{{ route('home') }}"
                                aria-live="polite"
                                role="timer"
                            >{{ $sessionExpiresAt->diffForHumans() }}</time>
                        </strong>
                    </div>
                </div>
            </aside>

            <div class="passenger-report-form-panel">
                <div class="passenger-report-heading">
                    <span><i class="bi bi-bus-front"></i> Passenger report</span>
                    <h2>Bus and evidence details</h2>
                    <p><strong>Required</strong> fields must be completed. Optional information can help officers investigate.</p>
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

                <form method="POST" action="{{ route('passenger.reports.store') }}" class="passenger-report-form" data-passenger-report-form data-bus-suggestions-url="{{ route('passenger.bus-suggestions') }}">
                    @csrf
                    <input type="hidden" name="pending_token" value="{{ $pending['token'] }}">

                    <div class="passenger-report-grid">
                        <div class="passenger-field">
                            <label for="bus_operator">Bus operator / company <span>Required</span></label>
                            <div class="passenger-autocomplete" data-bus-autocomplete>
                                <input id="bus_operator" name="bus_operator" type="text" value="{{ old('bus_operator') }}" maxlength="191" placeholder="Example: ABC Transport" autocomplete="off" data-bus-operator-input required>
                                <div class="passenger-autocomplete__menu" data-bus-suggestions role="listbox" hidden></div>
                            </div>
                        </div>

                        <div class="passenger-field">
                            <label for="bus_plate_number">Bus plate number <span>Required</span></label>
                            <div class="passenger-autocomplete" data-bus-autocomplete>
                                <input id="bus_plate_number" name="bus_plate_number" type="text" value="{{ old('bus_plate_number') }}" maxlength="50" placeholder="Example: T 123 ABC" autocomplete="off" data-bus-plate-input required>
                                <div class="passenger-autocomplete__menu" data-bus-suggestions role="listbox" hidden></div>
                            </div>
                        </div>

                        <div class="passenger-field">
                            <label for="bus_route">Bus route <small>Optional</small></label>
                            <input id="bus_route" name="bus_route" type="text" value="{{ old('bus_route') }}" maxlength="191" placeholder="Example: Posta - Mbezi">
                        </div>

                        <div class="passenger-field">
                            <label for="passenger_name">Your name <small>Optional</small></label>
                            <input id="passenger_name" name="passenger_name" type="text" value="{{ old('passenger_name') }}" maxlength="191" placeholder="You may remain anonymous">
                        </div>

                        <div class="passenger-field">
                            <label for="passenger_phone">Phone number <small>Optional</small></label>
                            <input id="passenger_phone" name="passenger_phone" type="tel" value="{{ old('passenger_phone') }}" maxlength="50" placeholder="For officer follow-up only">
                        </div>
                    </div>

                    <div class="passenger-field">
                        <label for="passenger_notes">Additional information <small>Optional</small></label>
                        <textarea id="passenger_notes" name="passenger_notes" rows="4" maxlength="2000" placeholder="Bus color, direction, landmark, or anything officers should know.">{{ old('passenger_notes') }}</textarea>
                    </div>

                    <div class="passenger-report-submit">
                        <button type="submit" class="btn btn-dark" data-passenger-submit>
                            <i class="bi bi-send-check-fill"></i> Submit passenger report
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rsrsPassengerReport.css') }}?v={{ filemtime(public_path('css/rsrsPassengerReport.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/rsrsPassengerReport.js') }}?v={{ filemtime(public_path('js/rsrsPassengerReport.js')) }}"></script>
@endpush
