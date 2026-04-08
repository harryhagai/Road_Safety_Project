@extends('layouts.officerDashboardLayout')

@section('page_header_actions')
    <button type="button" class="btn geo-header-btn" data-bs-toggle="modal" data-bs-target="#createViolationTypeModal">
        <i class="bi bi-plus-circle"></i>
        <span>New Violation Type</span>
    </button>
@endsection

@section('content')
    <div class="container-fluid px-2 px-lg-3 py-2">
        <section class="violation-shell">
            <div class="violation-shell__header">
                <div>
                    <h2 class="violation-shell__title">Violation type registry</h2>
                    <p class="violation-shell__subtitle">Manage the categories officers and reporters will use across the system.</p>
                </div>
                <div class="violation-shell__stats">
                    <div class="violation-stat">
                        <span class="violation-stat__label">Total</span>
                        <span class="violation-stat__value">{{ $violationTypes->count() }}</span>
                    </div>
                    <div class="violation-stat">
                        <span class="violation-stat__label">Active</span>
                        <span class="violation-stat__value">{{ $violationTypes->where('is_active', true)->count() }}</span>
                    </div>
                </div>
            </div>

            <div class="violation-table-wrap">
                <table class="table violation-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($violationTypes as $violationType)
                            <tr>
                                <td>
                                    <div class="violation-name">{{ $violationType->name }}</div>
                                </td>
                                <td>
                                    <div class="violation-description">
                                        {{ $violationType->description ?: 'No description provided.' }}
                                    </div>
                                </td>
                                <td>
                                    <span class="violation-status {{ $violationType->is_active ? 'is-active' : 'is-inactive' }}">
                                        <i class="bi {{ $violationType->is_active ? 'bi-check2-circle' : 'bi-pause-circle' }}"></i>
                                        <span>{{ $violationType->is_active ? 'Active' : 'Inactive' }}</span>
                                    </span>
                                </td>
                                <td>{{ optional($violationType->created_at)->format('d M Y') }}</td>
                                <td class="text-end">
                                    <div class="violation-actions">
                                        <button
                                            type="button"
                                            class="btn violation-action-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editViolationTypeModal{{ $violationType->id }}"
                                        >
                                            <i class="bi bi-pencil-square"></i>
                                            <span>Edit</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="btn violation-action-btn violation-action-btn--danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteViolationTypeModal{{ $violationType->id }}"
                                        >
                                            <i class="bi bi-trash3"></i>
                                            <span>Delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="violation-empty">
                                        <i class="bi bi-inboxes"></i>
                                        <span>No violation types created yet.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="modal fade" id="createViolationTypeModal" tabindex="-1" aria-labelledby="createViolationTypeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content geo-modal">
                <div class="modal-header geo-modal__header">
                    <div class="geo-modal__title-wrap">
                        <span class="geo-modal__icon">
                            <i class="bi bi-exclamation-triangle"></i>
                        </span>
                        <div>
                            <h5 class="modal-title geo-modal__title" id="createViolationTypeModalLabel">New violation type</h5>
                            <div class="geo-modal__subtitle">Create a reusable report category for incidents and violations.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form method="POST" action="{{ route('officer.violation-types.store') }}">
                    @csrf
                    <div class="modal-body geo-modal__body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="violation_type_name" class="form-label">Violation type name</label>
                                <input type="text" class="form-control" id="violation_type_name" name="name" value="{{ old('name') }}" placeholder="e.g. Reckless driving" required>
                            </div>
                            <div class="col-12">
                                <label for="violation_type_description" class="form-label">Description</label>
                                <textarea class="form-control" id="violation_type_description" name="description" rows="4" placeholder="Describe what this violation type covers">{{ old('description') }}</textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="violation_type_is_active" name="is_active" value="1" {{ old('is_active') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="violation_type_is_active">Violation type is active</label>
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
                            <span>Save violation type</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($violationTypes as $violationType)
        <div class="modal fade" id="editViolationTypeModal{{ $violationType->id }}" tabindex="-1" aria-labelledby="editViolationTypeModalLabel{{ $violationType->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content geo-modal">
                    <div class="modal-header geo-modal__header">
                        <div class="geo-modal__title-wrap">
                            <span class="geo-modal__icon">
                                <i class="bi bi-pencil-square"></i>
                            </span>
                            <div>
                                <h5 class="modal-title geo-modal__title" id="editViolationTypeModalLabel{{ $violationType->id }}">Edit violation type</h5>
                                <div class="geo-modal__subtitle">Update this category without affecting the visual flow of the page.</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form method="POST" action="{{ route('officer.violation-types.update', $violationType) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-body geo-modal__body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="edit_violation_type_name_{{ $violationType->id }}" class="form-label">Violation type name</label>
                                    <input type="text" class="form-control" id="edit_violation_type_name_{{ $violationType->id }}" name="name" value="{{ $violationType->name }}" required>
                                </div>
                                <div class="col-12">
                                    <label for="edit_violation_type_description_{{ $violationType->id }}" class="form-label">Description</label>
                                    <textarea class="form-control" id="edit_violation_type_description_{{ $violationType->id }}" name="description" rows="4">{{ $violationType->description }}</textarea>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" role="switch" id="edit_violation_type_is_active_{{ $violationType->id }}" name="is_active" value="1" {{ $violationType->is_active ? 'checked' : '' }}>
                                        <label class="form-check-label" for="edit_violation_type_is_active_{{ $violationType->id }}">Violation type is active</label>
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
                                <span>Update violation type</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteViolationTypeModal{{ $violationType->id }}" tabindex="-1" aria-labelledby="deleteViolationTypeModalLabel{{ $violationType->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content geo-modal">
                    <div class="modal-header geo-modal__header">
                        <div class="geo-modal__title-wrap">
                            <span class="geo-modal__icon">
                                <i class="bi bi-trash3"></i>
                            </span>
                            <div>
                                <h5 class="modal-title geo-modal__title" id="deleteViolationTypeModalLabel{{ $violationType->id }}">Delete violation type</h5>
                                <div class="geo-modal__subtitle">This action will remove the selected category from the registry.</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form method="POST" action="{{ route('officer.violation-types.destroy', $violationType) }}">
                        @csrf
                        @method('DELETE')
                        <div class="modal-body geo-modal__body">
                            <p class="mb-0 text-muted">
                                You are about to delete <strong>{{ $violationType->name }}</strong>. Continue only if you are sure.
                            </p>
                        </div>
                        <div class="modal-footer geo-modal__footer">
                            <button type="button" class="btn geo-modal__secondary-btn" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i>
                                <span>Cancel</span>
                            </button>
                            <button type="submit" class="btn geo-modal__primary-btn violation-delete-btn">
                                <i class="bi bi-trash3"></i>
                                <span>Delete violation type</span>
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
@endpush
