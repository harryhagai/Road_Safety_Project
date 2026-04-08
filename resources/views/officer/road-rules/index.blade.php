@extends('layouts.officerDashboardLayout')

@section('page_header_actions')
    <button type="button" class="btn geo-header-btn" data-bs-toggle="modal" data-bs-target="#createRoadRuleModal" id="openRoadRuleModalBtn">
        <i class="bi bi-plus-circle"></i>
        <span>New Rule</span>
    </button>
@endsection

@section('content')
    <div class="container-fluid geo-workspace px-1 px-lg-2 py-2">
        <div class="row g-2 geo-workspace__grid">
            <div class="col-12 col-xl-8">
                <section class="geo-card geo-card--fill geo-card--map">
                    <div class="geo-card__header">
                        <div>
                            <h2 class="geo-card__title">Road rules map</h2>
                            <p class="geo-card__text mb-0">Review available segments and highlight the rule coverage on the map.</p>
                        </div>
                    </div>

                    <x-map.canvas id="roadRuleMap" :config="$mapConfig" height="calc(100vh - 235px)" :show-toolbar="false" mode="viewer" />
                </section>
            </div>

            <div class="col-12 col-xl-4">
                <section class="geo-card geo-card--fill geo-card--inspector">
                    <div class="geo-card__header">
                        <div>
                            <h2 class="geo-card__title">Rule details</h2>
                            <p class="geo-card__text mb-0">Select a rule or segment to preview what is already configured.</p>
                        </div>
                    </div>

                    <div class="geo-location-panel geo-location-panel--compact">
                        <div class="geo-location-panel__label">Selected segment</div>
                        <div id="ruleSelectedSegmentPanel" class="geo-location-panel__value">No segment selected.</div>
                    </div>

                    <div class="geo-location-panel geo-location-panel--compact">
                        <div class="geo-location-panel__label">Rule coverage</div>
                        <div id="ruleCoveragePanel" class="geo-location-panel__value">Choose a rule to inspect its segment.</div>
                    </div>

                    <div class="geo-location-panel">
                        <div class="geo-location-panel__label">Rule status</div>
                        <div id="ruleStatusPanel" class="geo-location-panel__value">No rule selected.</div>
                    </div>

                    <div class="geo-segment-list">
                        <div class="geo-segment-list__header">
                            <span>Saved rules</span>
                            <span class="geo-segment-list__count">{{ $rules->count() }}</span>
                        </div>

                        <div class="geo-segment-list__body">
                            @forelse ($rules as $rule)
                                <button
                                    type="button"
                                    class="geo-segment-item geo-rule-item"
                                    data-road-rule='@json($rule)'
                                >
                                    <span class="geo-segment-item__title">{{ $rule['rule_name'] }}</span>
                                    <span class="geo-segment-item__meta">
                                        {{ $rule['rule_type'] }}
                                        @if ($rule['rule_value'])
                                            | {{ $rule['rule_value'] }}
                                        @endif
                                    </span>
                                </button>
                            @empty
                                <div class="geo-segment-list__empty">No road rules saved yet.</div>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createRoadRuleModal" tabindex="-1" aria-labelledby="createRoadRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content geo-modal">
                <div class="modal-header geo-modal__header">
                    <div class="geo-modal__title-wrap">
                        <span class="geo-modal__icon">
                            <i class="bi bi-sign-turn-right"></i>
                        </span>
                        <div>
                            <h5 class="modal-title geo-modal__title" id="createRoadRuleModalLabel">New road rule</h5>
                            <div class="geo-modal__subtitle">Attach a rule to an existing road segment.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form method="POST" action="{{ route('officer.road-rules.store') }}">
                    @csrf
                    <div class="modal-body geo-modal__body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="rule_name" class="form-label">Rule name</label>
                                <input type="text" class="form-control" id="rule_name" name="rule_name" value="{{ old('rule_name') }}" placeholder="e.g. Morogoro speed control" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="segment_id" class="form-label">Linked segment</label>
                                <select class="form-select" id="segment_id" name="segment_id" required>
                                    <option value="">Select segment</option>
                                    @foreach ($segments as $segment)
                                        <option value="{{ $segment['id'] }}" @selected((string) old('segment_id') === (string) $segment['id'])>
                                            {{ $segment['segment_name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="rule_type" class="form-label">Rule type</label>
                                <select class="form-select" id="rule_type" name="rule_type" required>
                                    <option value="">Select type</option>
                                    <option value="speed_limit" @selected(old('rule_type') === 'speed_limit')>Speed limit</option>
                                    <option value="no_parking" @selected(old('rule_type') === 'no_parking')>No parking</option>
                                    <option value="one_way" @selected(old('rule_type') === 'one_way')>One way</option>
                                    <option value="school_zone" @selected(old('rule_type') === 'school_zone')>School zone</option>
                                    <option value="pedestrian_zone" @selected(old('rule_type') === 'pedestrian_zone')>Pedestrian zone</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="rule_value" class="form-label">Rule value</label>
                                <input type="text" class="form-control" id="rule_value" name="rule_value" value="{{ old('rule_value') }}" placeholder="e.g. 50 km/h">
                            </div>
                            <div class="col-12">
                                <label for="location_name" class="form-label">Location name</label>
                                <input type="text" class="form-control" id="location_name" name="location_name" value="{{ old('location_name') }}" placeholder="Optional display name for this rule area">
                            </div>
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Describe what this rule enforces">{{ old('description') }}</textarea>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="effective_from" class="form-label">Effective from</label>
                                <input type="datetime-local" class="form-control" id="effective_from" name="effective_from" value="{{ old('effective_from') }}">
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="effective_to" class="form-label">Effective to</label>
                                <input type="datetime-local" class="form-control" id="effective_to" name="effective_to" value="{{ old('effective_to') }}">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Rule is active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer geo-modal__footer">
                        <button type="button" class="btn geo-modal__secondary-btn" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i>
                            <span>Cancel</span>
                        </button>
                        <button type="submit" class="btn geo-modal__primary-btn">
                            <i class="bi bi-check2-circle"></i>
                            <span>Save rule</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="{{ asset('css/rsrsMap.css') }}">
@endpush

@section('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
@endsection

@push('scripts')
    <script>
        window.roadRulePage = {
            segments: @json($segments),
            rules: @json($rules),
        };
    </script>
    <script src="{{ asset('js/rsrsMapPicker.js') }}"></script>
    <script src="{{ asset('js/rsrsRoadRules.js') }}"></script>
@endpush
