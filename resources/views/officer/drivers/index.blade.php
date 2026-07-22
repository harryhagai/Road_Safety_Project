{{-- Officer driver management view. --}}

@extends('layouts.officerDashboardLayout')

@section('page_header_actions')
    <button type="button" class="btn geo-header-btn" data-bs-toggle="modal" data-bs-target="#createDriverModal">
        <i class="bi bi-plus-circle"></i>
        <span>New Driver</span>
    </button>
@endsection

@section('content')
    <div class="container-fluid px-2 px-lg-3 py-2">
        @if ($errors->any())
            <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
                <div>
                    <strong>Unable to save driver.</strong>
                    <ul class="mb-0 mt-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <section class="violation-shell driver-admin-shell">
            <div class="violation-shell__header driver-admin-shell__header">
                <div>
                    <h2 class="violation-shell__title">Driver accounts</h2>
                    <p class="violation-shell__subtitle">Create, update, reset passwords, and control driver access.</p>
                </div>
                <div class="violation-shell__stats">
                    <div class="violation-stat">
                        <span class="violation-stat__label">Total</span>
                        <span class="violation-stat__value">{{ $totalDrivers }}</span>
                    </div>
                    <div class="violation-stat">
                        <span class="violation-stat__label">Active</span>
                        <span class="violation-stat__value">{{ $activeDrivers }}</span>
                    </div>
                    <div class="violation-stat">
                        <span class="violation-stat__label">Inactive</span>
                        <span class="violation-stat__value">{{ $inactiveDrivers }}</span>
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('officer.drivers.index') }}" class="driver-admin-filter">
                <div class="driver-admin-filter__field">
                    <label for="driver_search">Search</label>
                    <input id="driver_search" type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name, email, plate, vehicle, organization">
                </div>
                <div class="driver-admin-filter__field driver-admin-filter__status">
                    <label for="driver_status">Status</label>
                    <select id="driver_status" name="status" class="form-select">
                        <option value="">All drivers</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="driver-admin-filter__actions">
                    <button type="submit" class="btn violation-action-btn">
                        <i class="bi bi-search"></i>
                        <span>Filter</span>
                    </button>
                    @if (request()->hasAny(['search', 'status']))
                        <a href="{{ route('officer.drivers.index') }}" class="btn violation-action-btn">
                            <i class="bi bi-x-circle"></i>
                            <span>Clear</span>
                        </a>
                    @endif
                </div>
            </form>

            <div class="violation-table-wrap">
                <table class="table violation-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>Vehicle</th>
                            <th>Organization</th>
                            <th>Reports</th>
                            <th>Status</th>
                            <th>Last login</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($drivers as $driver)
                            <tr>
                                <td>
                                    <div class="violation-name">{{ $driver->name }}</div>
                                    <div class="driver-admin-muted">{{ $driver->email }}</div>
                                </td>
                                <td>
                                    <div class="violation-name">{{ $driver->vehicle_name ?: 'Not provided' }}</div>
                                    <div class="driver-admin-muted">{{ $driver->plate_number ?: 'No plate number' }}</div>
                                </td>
                                <td>{{ $driver->organization ?: 'Not provided' }}</td>
                                <td>{{ $driver->reports_count }}</td>
                                <td>
                                    <span class="violation-status {{ $driver->is_active ? 'is-active' : 'is-inactive' }}">
                                        <i class="bi {{ $driver->is_active ? 'bi-check2-circle' : 'bi-pause-circle' }}"></i>
                                        <span>{{ $driver->is_active ? 'Active' : 'Inactive' }}</span>
                                    </span>
                                </td>
                                <td>{{ optional($driver->last_login_at)->format('d M Y, H:i') ?: 'Never' }}</td>
                                <td class="text-end">
                                    <div class="violation-actions">
                                        <button type="button" class="btn violation-action-btn" data-bs-toggle="modal" data-bs-target="#editDriverModal{{ $driver->id }}">
                                            <i class="bi bi-pencil-square"></i>
                                            <span>Edit</span>
                                        </button>
                                        <button type="button" class="btn violation-action-btn" data-bs-toggle="modal" data-bs-target="#resetDriverPasswordModal{{ $driver->id }}">
                                            <i class="bi bi-key"></i>
                                            <span>Password</span>
                                        </button>
                                        <button type="button" class="btn violation-action-btn {{ $driver->is_active ? 'violation-action-btn--danger' : '' }}" data-bs-toggle="modal" data-bs-target="#statusDriverModal{{ $driver->id }}">
                                            <i class="bi {{ $driver->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                                            <span>{{ $driver->is_active ? 'Inactivate' : 'Activate' }}</span>
                                        </button>
                                        <button type="button" class="btn violation-action-btn violation-action-btn--danger" data-bs-toggle="modal" data-bs-target="#deleteDriverModal{{ $driver->id }}">
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
                                        <i class="bi bi-person-vcard"></i>
                                        <span>No driver accounts found.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($drivers->hasPages())
                <div class="driver-admin-pagination">
                    {{ $drivers->links() }}
                </div>
            @endif
        </section>
    </div>

    <div class="modal fade" id="createDriverModal" tabindex="-1" aria-labelledby="createDriverModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content geo-modal">
                <div class="modal-header geo-modal__header">
                    <div class="geo-modal__title-wrap">
                        <span class="geo-modal__icon">
                            <i class="bi bi-bus-front"></i>
                        </span>
                        <div>
                            <h5 class="modal-title geo-modal__title" id="createDriverModalLabel">New driver</h5>
                            <div class="geo-modal__subtitle">Create a driver login with vehicle identity details.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form method="POST" action="{{ route('officer.drivers.store') }}">
                    @csrf
                    <div class="modal-body geo-modal__body">
                        @include('officer.drivers.partials.fields', ['driver' => null, 'mode' => 'create'])
                    </div>
                    <div class="modal-footer geo-modal__footer">
                        <button type="button" class="btn geo-modal__secondary-btn" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i>
                            <span>Cancel</span>
                        </button>
                        <button type="submit" class="btn geo-modal__primary-btn">
                            <i class="bi bi-check2-circle"></i>
                            <span>Save driver</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($drivers as $driver)
        <div class="modal fade" id="editDriverModal{{ $driver->id }}" tabindex="-1" aria-labelledby="editDriverModalLabel{{ $driver->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content geo-modal">
                    <div class="modal-header geo-modal__header">
                        <div class="geo-modal__title-wrap">
                            <span class="geo-modal__icon">
                                <i class="bi bi-pencil-square"></i>
                            </span>
                            <div>
                                <h5 class="modal-title geo-modal__title" id="editDriverModalLabel{{ $driver->id }}">Edit driver</h5>
                                <div class="geo-modal__subtitle">Update account and vehicle details for {{ $driver->name }}.</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form method="POST" action="{{ route('officer.drivers.update', $driver) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-body geo-modal__body">
                            @include('officer.drivers.partials.fields', ['driver' => $driver, 'mode' => 'edit'])
                        </div>
                        <div class="modal-footer geo-modal__footer">
                            <button type="button" class="btn geo-modal__secondary-btn" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i>
                                <span>Cancel</span>
                            </button>
                            <button type="submit" class="btn geo-modal__primary-btn">
                                <i class="bi bi-check2-circle"></i>
                                <span>Update driver</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="resetDriverPasswordModal{{ $driver->id }}" tabindex="-1" aria-labelledby="resetDriverPasswordModalLabel{{ $driver->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content geo-modal">
                    <div class="modal-header geo-modal__header">
                        <div class="geo-modal__title-wrap">
                            <span class="geo-modal__icon">
                                <i class="bi bi-key"></i>
                            </span>
                            <div>
                                <h5 class="modal-title geo-modal__title" id="resetDriverPasswordModalLabel{{ $driver->id }}">Reset password</h5>
                                <div class="geo-modal__subtitle">Set a new password for {{ $driver->name }}.</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form method="POST" action="{{ route('officer.drivers.password', $driver) }}">
                        @csrf
                        @method('PATCH')
                        <div class="modal-body geo-modal__body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="driver_password_{{ $driver->id }}" class="form-label">New password</label>
                                    <input id="driver_password_{{ $driver->id }}" type="password" name="password" class="form-control" required autocomplete="new-password">
                                </div>
                                <div class="col-12">
                                    <label for="driver_password_confirmation_{{ $driver->id }}" class="form-label">Confirm new password</label>
                                    <input id="driver_password_confirmation_{{ $driver->id }}" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
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
                                <span>Reset password</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="statusDriverModal{{ $driver->id }}" tabindex="-1" aria-labelledby="statusDriverModalLabel{{ $driver->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content geo-modal">
                    <div class="modal-header geo-modal__header">
                        <div class="geo-modal__title-wrap">
                            <span class="geo-modal__icon">
                                <i class="bi {{ $driver->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                            </span>
                            <div>
                                <h5 class="modal-title geo-modal__title" id="statusDriverModalLabel{{ $driver->id }}">
                                    {{ $driver->is_active ? 'Inactivate driver' : 'Activate driver' }}
                                </h5>
                                <div class="geo-modal__subtitle">
                                    {{ $driver->is_active ? 'Inactive drivers cannot login until reactivated.' : 'Active drivers can login and submit identified reports.' }}
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form method="POST" action="{{ route('officer.drivers.status', $driver) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="is_active" value="{{ $driver->is_active ? 0 : 1 }}">
                        <div class="modal-body geo-modal__body">
                            <p class="mb-0 text-muted">
                                Continue to {{ $driver->is_active ? 'inactivate' : 'activate' }} <strong>{{ $driver->name }}</strong>?
                            </p>
                        </div>
                        <div class="modal-footer geo-modal__footer">
                            <button type="button" class="btn geo-modal__secondary-btn" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i>
                                <span>Cancel</span>
                            </button>
                            <button type="submit" class="btn geo-modal__primary-btn {{ $driver->is_active ? 'violation-delete-btn' : '' }}">
                                <i class="bi {{ $driver->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                                <span>{{ $driver->is_active ? 'Inactivate driver' : 'Activate driver' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteDriverModal{{ $driver->id }}" tabindex="-1" aria-labelledby="deleteDriverModalLabel{{ $driver->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content geo-modal">
                    <div class="modal-header geo-modal__header">
                        <div class="geo-modal__title-wrap">
                            <span class="geo-modal__icon">
                                <i class="bi bi-trash3"></i>
                            </span>
                            <div>
                                <h5 class="modal-title geo-modal__title" id="deleteDriverModalLabel{{ $driver->id }}">Delete driver</h5>
                                <div class="geo-modal__subtitle">This removes the login account. Existing reports will remain in history.</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form method="POST" action="{{ route('officer.drivers.destroy', $driver) }}">
                        @csrf
                        @method('DELETE')
                        <div class="modal-body geo-modal__body">
                            <p class="mb-0 text-muted">
                                You are about to delete <strong>{{ $driver->name }}</strong>. Inactivate the account instead if you only need to block login.
                            </p>
                        </div>
                        <div class="modal-footer geo-modal__footer">
                            <button type="button" class="btn geo-modal__secondary-btn" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i>
                                <span>Cancel</span>
                            </button>
                            <button type="submit" class="btn geo-modal__primary-btn violation-delete-btn">
                                <i class="bi bi-trash3"></i>
                                <span>Delete driver</span>
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
    <link rel="stylesheet" href="{{ asset('css/rsrsOfficerDrivers.css') }}?v={{ filemtime(public_path('css/rsrsOfficerDrivers.css')) }}">
@endpush
