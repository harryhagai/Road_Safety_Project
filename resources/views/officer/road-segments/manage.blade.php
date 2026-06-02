{{-- Dedicated management workspace for previewing, editing, and deleting road segments. --}}

@extends('layouts.officerDashboardLayout')

@section('page_header_actions')
    <a href="{{ route('officer.road-segments.index') }}" class="btn geo-header-btn geo-header-btn--light">
        <i class="bi bi-bezier2"></i>
        <span>Open Map Builder</span>
    </a>
@endsection

@section('content')
    <div class="container-fluid geo-workspace px-1 px-lg-2 py-2 geo-manage-workspace">
        <div class="row g-2 geo-workspace__grid">
            <div class="col-12 col-xl-8">
                <section class="geo-card geo-card--fill geo-card--map">
                    <div class="geo-card__header">
                        <div>
                            <h2 class="geo-card__title">Segment preview map</h2>
                            <p class="geo-card__text mb-0">Click any segment on the right to preview it on the map before editing or deleting.</p>
                        </div>
                    </div>

                    <x-map.canvas id="roadSegmentManagementMap" :config="$mapConfig" height="100%" :show-toolbar="false"
                        mode="segment-manager" />
                </section>
            </div>

            <div class="col-12 col-xl-4">
                <section class="geo-card geo-card--fill geo-card--inspector">
                    <div class="geo-card__header">
                        <div>
                            <h2 class="geo-card__title">Segments</h2>
                            <p class="geo-card__text mb-0">Preview, update, or remove saved segments.</p>
                        </div>
                    </div>

                    <div class="geo-location-panel geo-location-panel--compact">
                        <div class="geo-location-panel__label">Preview status</div>
                        <div id="segmentManageStatus" class="geo-location-panel__value">
                            Select a segment from the list to preview it on the map.
                        </div>
                    </div>

                    <div class="geo-segment-list">
                        <div class="geo-segment-list__header">
                            <span>Saved segments</span>
                            <span class="geo-segment-list__count">{{ $segments->count() }}</span>
                        </div>

                        <div class="geo-segment-list__body">
                            @forelse ($segments as $segment)
                                <article class="geo-segment-item">
                                    <button type="button" class="geo-segment-item__focus"
                                        data-existing-segment-focus
                                        data-existing-segment='@json($segment)'>
                                        <span class="geo-segment-item__title">{{ $segment['segment_name'] }}</span>
                                        <span class="geo-segment-item__meta">
                                            {{ $segment['segment_type'] ?: 'General segment' }}
                                            @if ($segment['length_km'])
                                                &bull; {{ number_format((float) $segment['length_km'], 2) }} km
                                            @endif
                                        </span>
                                    </button>
                                    <div class="geo-segment-item__actions">
                                        <button type="button" class="geo-segment-item__action-btn" title="Edit segment"
                                            data-edit-segment-trigger data-segment='@json($segment)' data-bs-toggle="modal"
                                            data-bs-target="#editRoadSegmentModal">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" class="geo-segment-item__action-btn geo-segment-item__action-btn--danger"
                                            title="Delete segment" data-delete-segment-trigger data-segment='@json($segment)'
                                            data-bs-toggle="modal" data-bs-target="#deleteRoadSegmentModal">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </article>
                            @empty
                                <div class="geo-segment-list__empty">No road segments saved yet.</div>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editRoadSegmentModal" tabindex="-1" aria-labelledby="editRoadSegmentModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content geo-modal">
                <div class="modal-header geo-modal__header">
                    <div class="geo-modal__title-wrap">
                        <span class="geo-modal__icon">
                            <i class="bi bi-pencil-square"></i>
                        </span>
                        <div>
                            <h5 class="modal-title geo-modal__title" id="editRoadSegmentModalLabel">Edit road segment</h5>
                            <div class="geo-modal__subtitle">Update segment details.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form method="POST" id="editRoadSegmentForm">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="redirect_to" value="{{ request()->getPathInfo() }}">
                    <div class="modal-body geo-modal__body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="edit_segment_name" class="form-label">Segment name</label>
                                <input type="text" class="form-control" id="edit_segment_name" name="segment_name" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="edit_segment_type_id" class="form-label">Segment type</label>
                                <select class="form-select" id="edit_segment_type_id" name="segment_type_id">
                                    <option value="">Select type</option>
                                    @foreach ($segmentTypes as $segmentType)
                                        <option value="{{ $segmentType->id }}">{{ $segmentType->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Rules preview from selected segment type</label>
                                <div id="editSegmentTypeRulesPreview" class="form-control" style="min-height: 94px; height: auto;">
                                    Select a segment type to preview default rules.
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="edit_description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="edit_length_km" class="form-label">Estimated length (km)</label>
                                <input type="number" class="form-control" id="edit_length_km" name="length_km" min="0" step="0.01">
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
                            <span>Update segment</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteRoadSegmentModal" tabindex="-1" aria-labelledby="deleteRoadSegmentModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content geo-modal">
                <div class="modal-header geo-modal__header">
                    <div class="geo-modal__title-wrap">
                        <span class="geo-modal__icon">
                            <i class="bi bi-trash3"></i>
                        </span>
                        <div>
                            <h5 class="modal-title geo-modal__title" id="deleteRoadSegmentModalLabel">Delete road segment</h5>
                            <div class="geo-modal__subtitle">This action cannot be undone.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="deleteRoadSegmentForm">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="redirect_to" value="{{ request()->getPathInfo() }}">
                    <div class="modal-body geo-modal__body">
                        <p class="mb-0">
                            You are about to delete <strong id="deleteRoadSegmentName">this segment</strong>.
                            Continue?
                        </p>
                    </div>
                    <div class="modal-footer geo-modal__footer">
                        <button type="button" class="btn geo-modal__secondary-btn" data-bs-dismiss="modal">
                            <i class="bi bi-arrow-left-circle"></i>
                            <span>Keep segment</span>
                        </button>
                        <button type="submit" class="btn btn-outline-danger geo-modal__danger-outline-btn">
                            <i class="bi bi-trash3"></i>
                            <span>Delete segment</span>
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
    <style>
        .geo-manage-workspace .geo-card--map .geo-map-shell {
            height: 100%;
            min-height: 0;
        }

        .geo-manage-workspace .geo-card--map .geo-map-canvas {
            flex: 1 1 auto;
            min-height: 0;
            height: 100% !important;
        }

        .geo-manage-workspace .geo-segment-list__body {
            max-height: calc(100vh - 340px);
        }
    </style>
@endpush

@section('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
@endsection

@push('scripts')
    <script>
        window.roadSegmentManagePage = {
            existingSegments: @json($segments),
            segmentTypesWithRules: @json($segmentTypesWithRules),
            updateUrlTemplate: @json(route('officer.road-segments.update', ['roadSegment' => '__SEGMENT_ID__'])),
            destroyUrlTemplate: @json(route('officer.road-segments.destroy', ['roadSegment' => '__SEGMENT_ID__'])),
        };
    </script>
    <script src="{{ asset('js/rsrsMapPicker.js') }}"></script>
    <script src="{{ asset('js/rsrsRoadSegmentsManage.js') }}"></script>
@endpush
