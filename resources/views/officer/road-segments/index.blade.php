@extends('layouts.officerDashboardLayout')

@section('page_header_actions')
    <button type="button" class="btn geo-header-btn" data-bs-toggle="modal" data-bs-target="#createRoadSegmentModal"
        id="openSegmentModalBtn">
        <i class="bi bi-plus-circle"></i>
        <span>New Segment</span>
    </button>
    <button type="button" class="btn geo-header-btn geo-header-btn--light" id="undoSegmentPointBtn">
        <i class="bi bi-arrow-counterclockwise"></i>
        <span>Undo</span>
    </button>
    <button type="button" class="btn geo-header-btn geo-header-btn--light" id="clearSegmentPointsBtn">
        <i class="bi bi-eraser"></i>
        <span>Clear Path</span>
    </button>
@endsection

@section('content')
    <div class="container-fluid geo-workspace px-1 px-lg-2 py-2">
        <div class="row g-2 geo-workspace__grid">
            <div class="col-12 col-xl-8">
                <section class="geo-card geo-card--fill geo-card--map">
                    <div class="geo-card__header">
                        <div>
                            <h2 class="geo-card__title">Road segment mapping</h2>
                            <p class="geo-card__text mb-0">Search a location or click points on the map to trace a road segment path.</p>
                        </div>
                    </div>

                    <div class="geo-map-search">
                        <label for="roadSegmentLocationSearch" class="geo-map-search__label">Find location</label>
                        <div class="geo-map-search__input-wrap">
                            <i class="bi bi-search"></i>
                            <input
                                type="search"
                                id="roadSegmentLocationSearch"
                                class="form-control"
                                placeholder="Search place, road, ward, or landmark"
                                autocomplete="off"
                                spellcheck="false"
                            >
                            <button type="button" class="btn geo-map-search__clear" id="roadSegmentLocationSearchClear" hidden>
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                        <div id="roadSegmentLocationSearchStatus" class="geo-map-search__status" aria-live="polite">
                            Start typing to find a location and jump the map there.
                        </div>
                        <div id="roadSegmentLocationSearchResults" class="geo-map-search__results" hidden></div>
                    </div>

                    <x-map.canvas id="roadSegmentMapLab" :config="$mapConfig" height="calc(100vh - 235px)" :show-toolbar="false"
                        mode="segment-builder" />
                </section>
            </div>

            <div class="col-12 col-xl-4">
                <section class="geo-card geo-card--fill geo-card--inspector">
                    <div class="geo-card__header">
                        <div>
                            <h2 class="geo-card__title">Segment details</h2>
                            <p class="geo-card__text mb-0">Review the current selection and save it through the modal form.
                            </p>
                        </div>
                    </div>

                    <div class="geo-location-panel geo-location-panel--compact">
                        <div class="geo-location-panel__label">Selected coordinates</div>
                        <div id="selectedCoordinatesPanel" class="geo-location-panel__value">
                            Click on the map to choose a location.
                        </div>
                    </div>

                    <div class="geo-location-panel geo-location-panel--compact">
                        <div class="geo-location-panel__label">Resolved location</div>
                        <div id="mapResolvedLocation" class="geo-location-panel__value">
                            Location name will appear here after reverse geocoding.
                        </div>
                    </div>

                    <div class="geo-location-panel geo-location-panel--compact">
                        <div class="geo-location-panel__label">Segment points</div>
                        <div id="segmentPointCount" class="geo-location-panel__value">0 points selected</div>
                    </div>

                    <div class="geo-location-panel">
                        <div class="geo-location-panel__label">Estimated length</div>
                        <div id="segmentLengthPreview" class="geo-location-panel__value">0.00 km</div>
                    </div>

                    <div class="geo-segment-list">
                        <div class="geo-segment-list__header">
                            <span>Saved segments</span>
                            <span class="geo-segment-list__count">{{ $segments->count() }}</span>
                        </div>

                        <div class="geo-segment-list__body">
                            @forelse ($segments as $segment)
                                <button type="button" class="geo-segment-item"
                                    data-existing-segment='@json($segment)'>
                                    <span class="geo-segment-item__title">{{ $segment['segment_name'] }}</span>
                                    <span class="geo-segment-item__meta">
                                        {{ $segment['segment_type'] ?: 'General segment' }}
                                        @if ($segment['length_km'])
                                            • {{ number_format((float) $segment['length_km'], 2) }} km
                                        @endif
                                    </span>
                                </button>
                            @empty
                                <div class="geo-segment-list__empty">No road segments saved yet.</div>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createRoadSegmentModal" tabindex="-1" aria-labelledby="createRoadSegmentModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content geo-modal">
                <div class="modal-header geo-modal__header">
                    <div class="geo-modal__title-wrap">
                        <span class="geo-modal__icon">
                            <i class="bi bi-signpost-split"></i>
                        </span>
                        <div>
                            <h5 class="modal-title geo-modal__title" id="createRoadSegmentModalLabel">New road segment</h5>
                            <div class="geo-modal__subtitle">Save the traced segment with its details.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form method="POST" action="{{ route('officer.road-segments.store') }}" id="roadSegmentForm">
                    @csrf
                    <div class="modal-body geo-modal__body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="segment_name" class="form-label">Segment name</label>
                                <input type="text" class="form-control" id="segment_name" name="segment_name"
                                    value="{{ old('segment_name') }}" placeholder="e.g. Morogoro Road - Ubungo stretch"
                                    required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="segment_type_id" class="form-label">Segment type</label>
                                <select class="form-select" id="segment_type_id" name="segment_type_id">
                                    <option value="">Select type</option>
                                    @foreach ($segmentTypes as $segmentType)
                                        <option value="{{ $segmentType->id }}" @selected((string) old('segment_type_id') === (string) $segmentType->id)>
                                            {{ $segmentType->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"
                                    placeholder="Optional notes about this road segment">{{ old('description') }}</textarea>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="length_km" class="form-label">Estimated length (km)</label>
                                <input type="number" class="form-control" id="length_km" name="length_km"
                                    value="{{ old('length_km') }}" min="0" step="0.01"
                                    placeholder="Auto-filled from the map">
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="segment_point_summary" class="form-label">Selected points</label>
                                <input type="text" class="form-control" id="segment_point_summary" value="0 points"
                                    readonly>
                            </div>
                        </div>

                        <input type="hidden" name="boundary_coordinates" id="boundary_coordinates">
                    </div>
                    <div class="modal-footer geo-modal__footer">
                        <button type="button" class="btn geo-modal__secondary-btn" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i>
                            <span>Cancel</span>
                        </button>
                        <button type="submit" class="btn geo-modal__primary-btn">
                            <i class="bi bi-check2-circle"></i>
                            <span>Save segment</span>
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
        window.roadSegmentPage = {
            existingSegments: @json($segments),
        };
    </script>
    <script src="{{ asset('js/rsrsMapPicker.js') }}"></script>
    <script src="{{ asset('js/rsrsRoadSegments.js') }}"></script>
@endpush
