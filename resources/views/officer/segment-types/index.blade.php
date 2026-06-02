{{-- Officer module view for index within the road safety dashboard. --}}

@extends('layouts.officerDashboardLayout')

@section('page_header_actions')
    <button type="button" class="btn geo-header-btn" data-bs-toggle="modal" data-bs-target="#createSegmentTypeModal">
        <i class="bi bi-plus-circle"></i>
        <span>New Segment Types</span>
    </button>
@endsection

@section('content')
    <div class="container-fluid px-2 px-lg-3 py-2">
        <section class="violation-shell">
            <div class="violation-shell__header">
                <div>
                    <h2 class="violation-shell__title">Segment type registry</h2>
                    <p class="violation-shell__subtitle">Manage the road segment categories officers can assign while mapping roads.</p>
                </div>
                <div class="violation-shell__stats">
                    <div class="violation-stat">
                        <span class="violation-stat__label">Total</span>
                        <span class="violation-stat__value">{{ $segmentTypes->count() }}</span>
                    </div>
                    <div class="violation-stat">
                        <span class="violation-stat__label">Active</span>
                        <span class="violation-stat__value">{{ $segmentTypes->where('is_active', true)->count() }}</span>
                    </div>
                    <div class="violation-stat">
                        <span class="violation-stat__label">In Use</span>
                        <span class="violation-stat__value">{{ $segmentTypes->where('road_segments_count', '>', 0)->count() }}</span>
                    </div>
                </div>
            </div>

            <div class="violation-table-wrap">
                <table class="table violation-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Segments</th>
                            <th>Default Rules</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($segmentTypes as $segmentType)
                            <tr>
                                <td><div class="violation-name">{{ $segmentType->name }}</div></td>
                                <td><div class="violation-description">{{ $segmentType->description ?: 'No description provided.' }}</div></td>
                                <td>{{ number_format($segmentType->road_segments_count) }}</td>
                                <td>{{ number_format($segmentType->default_rules_count ?? 0) }}</td>
                                <td>
                                    <span class="violation-status {{ $segmentType->is_active ? 'is-active' : 'is-inactive' }}">
                                        <i class="bi {{ $segmentType->is_active ? 'bi-check2-circle' : 'bi-pause-circle' }}"></i>
                                        <span>{{ $segmentType->is_active ? 'Active' : 'Inactive' }}</span>
                                    </span>
                                </td>
                                <td>{{ optional($segmentType->created_at)->format('d M Y') }}</td>
                                <td class="text-end">
                                    <div class="violation-actions">
                                        <button type="button" class="btn violation-action-btn" data-bs-toggle="modal" data-bs-target="#editSegmentTypeModal{{ $segmentType->id }}">
                                            <i class="bi bi-pencil-square"></i>
                                            <span>Edit</span>
                                        </button>
                                        <button type="button" class="btn violation-action-btn violation-action-btn--danger" data-bs-toggle="modal" data-bs-target="#deleteSegmentTypeModal{{ $segmentType->id }}">
                                            <i class="bi bi-trash3"></i>
                                            <span>Delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="violation-empty">
                                        <i class="bi bi-inboxes"></i>
                                        <span>No segment types created yet.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="modal fade" id="createSegmentTypeModal" tabindex="-1" aria-labelledby="createSegmentTypeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content geo-modal">
                <div class="modal-header geo-modal__header">
                    <div class="geo-modal__title-wrap">
                        <span class="geo-modal__icon">
                            <i class="bi bi-diagram-3"></i>
                        </span>
                        <div>
                            <h5 class="modal-title geo-modal__title" id="createSegmentTypeModalLabel">New segment type</h5>
                            <div class="geo-modal__subtitle">Create a reusable road segment category for officers.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form method="POST" action="{{ route('officer.segment-types.store') }}">
                    @csrf
                    <div class="modal-body geo-modal__body">
                        <div class="row g-3">
                            <div class="col-12 col-md-8">
                                <label for="segment_type_name" class="form-label">Segment type name</label>
                                <input type="text" class="form-control" id="segment_type_name" name="name" value="{{ old('name') }}" placeholder="e.g. Residential street" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="speed_limit_kmh" class="form-label">Speed limit (km/h)</label>
                                <input type="number" class="form-control" id="speed_limit_kmh" name="speed_limit_kmh" min="1" max="320" step="1" placeholder="e.g. 30">
                            </div>
                            <div class="col-12">
                                <label for="segment_type_description" class="form-label">Description</label>
                                <textarea class="form-control" id="segment_type_description" name="description" rows="4" placeholder="Describe what this segment type covers">{{ old('description') }}</textarea>
                            </div>
                            <div class="col-12">
                                <label for="other_rules" class="form-label">Other rules (one per line)</label>
                                <textarea class="form-control" id="other_rules" name="other_rules" rows="3"
                                    placeholder="No stopping&#10;No heavy trucks 6am-9pm"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer geo-modal__footer">
                        <button type="button" class="btn btn-outline-secondary geo-modal__cancel-btn" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i>
                            <span>Cancel</span>
                        </button>
                        <button type="submit" class="btn geo-modal__primary-btn">
                            <i class="bi bi-check2-circle"></i>
                            <span>Save segment type</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($segmentTypes as $segmentType)
        <div class="modal fade" id="editSegmentTypeModal{{ $segmentType->id }}" tabindex="-1" aria-labelledby="editSegmentTypeModalLabel{{ $segmentType->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content geo-modal">
                    <div class="modal-header geo-modal__header">
                        <div class="geo-modal__title-wrap">
                            <span class="geo-modal__icon">
                                <i class="bi bi-pencil-square"></i>
                            </span>
                            <div>
                                <h5 class="modal-title geo-modal__title" id="editSegmentTypeModalLabel{{ $segmentType->id }}">Edit segment type</h5>
                                <div class="geo-modal__subtitle">Update this category without breaking existing mapped segments.</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form method="POST" action="{{ route('officer.segment-types.update', $segmentType) }}">
                        @csrf
                        @method('PUT')
                        @php
                            $speedRule = $segmentType->defaultRules->firstWhere('rule_type', 'speed_limit');
                            $speedValue = $speedRule && preg_match('/\d+(?:\.\d+)?/', (string) $speedRule->rule_value, $m) ? $m[0] : '';
                            $otherRules = $segmentType->defaultRules
                                ->where('rule_type', 'other')
                                ->pluck('rule_name')
                                ->filter()
                                ->implode("\n");
                        @endphp
                        <div class="modal-body geo-modal__body">
                            <div class="row g-3">
                                <div class="col-12 col-md-8">
                                    <label for="edit_segment_type_name_{{ $segmentType->id }}" class="form-label">Segment type name</label>
                                    <input type="text" class="form-control" id="edit_segment_type_name_{{ $segmentType->id }}" name="name" value="{{ $segmentType->name }}" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="edit_speed_limit_kmh_{{ $segmentType->id }}" class="form-label">Speed limit (km/h)</label>
                                    <input type="number" class="form-control" id="edit_speed_limit_kmh_{{ $segmentType->id }}" name="speed_limit_kmh" min="1" max="320" step="1" value="{{ $speedValue }}">
                                </div>
                                <div class="col-12">
                                    <label for="edit_segment_type_description_{{ $segmentType->id }}" class="form-label">Description</label>
                                    <textarea class="form-control" id="edit_segment_type_description_{{ $segmentType->id }}" name="description" rows="4">{{ $segmentType->description }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label for="edit_other_rules_{{ $segmentType->id }}" class="form-label">Other rules (one per line)</label>
                                    <textarea class="form-control" id="edit_other_rules_{{ $segmentType->id }}" name="other_rules" rows="3">{{ $otherRules }}</textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer geo-modal__footer">
                            <button type="button" class="btn btn-outline-secondary geo-modal__cancel-btn" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i>
                                <span>Cancel</span>
                            </button>
                            <button type="submit" class="btn geo-modal__primary-btn">
                                <i class="bi bi-check2-circle"></i>
                                <span>Update segment type</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteSegmentTypeModal{{ $segmentType->id }}" tabindex="-1" aria-labelledby="deleteSegmentTypeModalLabel{{ $segmentType->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content geo-modal">
                    <div class="modal-header geo-modal__header">
                        <div class="geo-modal__title-wrap">
                            <span class="geo-modal__icon">
                                <i class="bi bi-trash3"></i>
                            </span>
                            <div>
                                <h5 class="modal-title geo-modal__title" id="deleteSegmentTypeModalLabel{{ $segmentType->id }}">Delete segment type</h5>
                                <div class="geo-modal__subtitle">Delete this category only if it is no longer used by saved road segments.</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form method="POST" action="{{ route('officer.segment-types.destroy', $segmentType) }}">
                        @csrf
                        @method('DELETE')
                        <div class="modal-body geo-modal__body">
                            <p class="mb-0 text-muted">
                                You are about to delete <strong>{{ $segmentType->name }}</strong>.
                                @if ($segmentType->road_segments_count > 0)
                                    This type is currently linked to {{ number_format($segmentType->road_segments_count) }} road segment(s), so deletion will be blocked.
                                @endif
                            </p>
                        </div>
                        <div class="modal-footer geo-modal__footer">
                            <button type="button" class="btn btn-outline-secondary geo-modal__cancel-btn" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i>
                                <span>Cancel</span>
                            </button>
                            <button type="submit" class="btn geo-modal__primary-btn violation-delete-btn" @disabled($segmentType->road_segments_count > 0)>
                                <i class="bi bi-trash3"></i>
                                <span>Delete segment type</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rsrsMap.css') }}">
    <link rel="stylesheet" href="{{ asset('css/rsrsViolationTypes.css') }}">
    <style>
        .geo-modal .form-control,
        .geo-modal .form-select {
            border-color: #1f314f;
        }
        .geo-modal .form-control:focus,
        .geo-modal .form-select:focus {
            border-color: #1f314f;
            box-shadow: 0 0 0 0.2rem rgba(31, 49, 79, 0.2);
        }
        .geo-modal__cancel-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border-radius: 14px;
            padding: 0.75rem 1rem;
            font-weight: 400;
        }
    </style>
@endpush
