{{-- Admin all-user management view. --}}

@extends('layouts.officerDashboardLayout')

@php
    $roleLabels = [
        \App\Models\User::ROLE_ADMIN => 'Admin',
        \App\Models\User::ROLE_ROAD_OFFICER => 'Road Officer',
        \App\Models\User::ROLE_DRIVER => 'Driver',
        \App\Models\User::ROLE_PASSENGER => 'Passenger',
    ];
@endphp

@section('page_header_actions')
    <button type="button" class="btn geo-header-btn" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="bi bi-person-plus"></i>
        <span>New User</span>
    </button>
@endsection

@section('content')
    <div class="container-fluid px-2 px-lg-3 py-2">
        @if ($errors->any())
            <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
                <div>
                    <strong>Unable to save user.</strong>
                    <ul class="mb-0 mt-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <section class="violation-shell admin-users-shell">
            <div class="violation-shell__header driver-admin-shell__header">
                <div>
                    <h2 class="violation-shell__title">All users</h2>
                    <p class="violation-shell__subtitle">Create accounts, assign roles, reset passwords, and control access.</p>
                </div>
                <div class="violation-shell__stats">
                    <div class="violation-stat">
                        <span class="violation-stat__label">Total</span>
                        <span class="violation-stat__value">{{ $totalUsers }}</span>
                    </div>
                    <div class="violation-stat">
                        <span class="violation-stat__label">Active</span>
                        <span class="violation-stat__value">{{ $activeUsers }}</span>
                    </div>
                    <div class="violation-stat">
                        <span class="violation-stat__label">Inactive</span>
                        <span class="violation-stat__value">{{ $inactiveUsers }}</span>
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('admin.users.index') }}" class="driver-admin-filter">
                <div class="driver-admin-filter__field">
                    <label for="user_search">Search</label>
                    <input id="user_search" type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name, email, plate, vehicle, organization">
                </div>
                <div class="driver-admin-filter__field">
                    <label for="user_role">Role</label>
                    <select id="user_role" name="role" class="form-select">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected(request('role') === $role)>{{ $roleLabels[$role] ?? $role }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="driver-admin-filter__field">
                    <label for="user_status">Status</label>
                    <select id="user_status" name="status" class="form-select">
                        <option value="">All users</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="driver-admin-filter__actions">
                    <button type="submit" class="btn violation-action-btn">
                        <i class="bi bi-search"></i>
                        <span>Filter</span>
                    </button>
                    @if (request()->hasAny(['search', 'role', 'status']))
                        <a href="{{ route('admin.users.index') }}" class="btn violation-action-btn">
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
                            <th>User</th>
                            <th>Role</th>
                            <th>Vehicle</th>
                            <th>Activity</th>
                            <th>Status</th>
                            <th>Last login</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $managedUser)
                            <tr>
                                <td>
                                    <div class="violation-name">{{ $managedUser->name }}</div>
                                    <div class="driver-admin-muted">{{ $managedUser->email }}</div>
                                </td>
                                <td>
                                    <span class="admin-role-pill admin-role-pill--{{ str_replace('_', '-', $managedUser->role) }}">
                                        {{ $roleLabels[$managedUser->role] ?? $managedUser->role }}
                                    </span>
                                </td>
                                <td>
                                    <div class="violation-name">{{ $managedUser->vehicle_name ?: 'N/A' }}</div>
                                    <div class="driver-admin-muted">{{ $managedUser->plate_number ?: $managedUser->organization ?: 'No vehicle details' }}</div>
                                </td>
                                <td>
                                    <div class="driver-admin-muted">Driver reports: {{ $managedUser->reports_count }}</div>
                                    <div class="driver-admin-muted">Submitted: {{ $managedUser->submitted_reports_count }}</div>
                                    <div class="driver-admin-muted">Reviewed: {{ $managedUser->reviewed_reports_count }}</div>
                                </td>
                                <td>
                                    <span class="violation-status {{ $managedUser->is_active ? 'is-active' : 'is-inactive' }}">
                                        <i class="bi {{ $managedUser->is_active ? 'bi-check2-circle' : 'bi-pause-circle' }}"></i>
                                        <span>{{ $managedUser->is_active ? 'Active' : 'Inactive' }}</span>
                                    </span>
                                </td>
                                <td>{{ optional($managedUser->last_login_at)->format('d M Y, H:i') ?: 'Never' }}</td>
                                <td class="text-end">
                                    <div class="violation-actions">
                                        <button type="button" class="btn violation-action-btn" data-bs-toggle="modal" data-bs-target="#editUserModal{{ $managedUser->id }}">
                                            <i class="bi bi-pencil-square"></i>
                                            <span>Edit</span>
                                        </button>
                                        <button type="button" class="btn violation-action-btn" data-bs-toggle="modal" data-bs-target="#resetUserPasswordModal{{ $managedUser->id }}">
                                            <i class="bi bi-key"></i>
                                            <span>Password</span>
                                        </button>
                                        <button type="button" class="btn violation-action-btn {{ $managedUser->is_active ? 'violation-action-btn--danger' : '' }}" data-bs-toggle="modal" data-bs-target="#statusUserModal{{ $managedUser->id }}" @disabled(auth()->id() === $managedUser->id)>
                                            <i class="bi {{ $managedUser->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                                            <span>{{ $managedUser->is_active ? 'Inactivate' : 'Activate' }}</span>
                                        </button>
                                        <button type="button" class="btn violation-action-btn violation-action-btn--danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal{{ $managedUser->id }}" @disabled(auth()->id() === $managedUser->id)>
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
                                        <i class="bi bi-people"></i>
                                        <span>No user accounts found.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="driver-admin-pagination">
                    {{ $users->links() }}
                </div>
            @endif
        </section>
    </div>

    <div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content geo-modal">
                <div class="modal-header geo-modal__header">
                    <div class="geo-modal__title-wrap">
                        <span class="geo-modal__icon"><i class="bi bi-person-plus"></i></span>
                        <div>
                            <h5 class="modal-title geo-modal__title" id="createUserModalLabel">New user</h5>
                            <div class="geo-modal__subtitle">Create a system account and assign its role.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form method="POST" action="{{ route('admin.users.store') }}">
                    @csrf
                    <div class="modal-body geo-modal__body">
                        @include('admin.users.partials.fields', ['managedUser' => null, 'roles' => $roles, 'roleLabels' => $roleLabels, 'mode' => 'create'])
                    </div>
                    <div class="modal-footer geo-modal__footer">
                        <button type="button" class="btn geo-modal__secondary-btn" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i>
                            <span>Cancel</span>
                        </button>
                        <button type="submit" class="btn geo-modal__primary-btn">
                            <i class="bi bi-check2-circle"></i>
                            <span>Save user</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($users as $managedUser)
        <div class="modal fade" id="editUserModal{{ $managedUser->id }}" tabindex="-1" aria-labelledby="editUserModalLabel{{ $managedUser->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content geo-modal">
                    <div class="modal-header geo-modal__header">
                        <div class="geo-modal__title-wrap">
                            <span class="geo-modal__icon"><i class="bi bi-pencil-square"></i></span>
                            <div>
                                <h5 class="modal-title geo-modal__title" id="editUserModalLabel{{ $managedUser->id }}">Edit user</h5>
                                <div class="geo-modal__subtitle">Update account details for {{ $managedUser->name }}.</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form method="POST" action="{{ route('admin.users.update', $managedUser) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-body geo-modal__body">
                            @include('admin.users.partials.fields', ['managedUser' => $managedUser, 'roles' => $roles, 'roleLabels' => $roleLabels, 'mode' => 'edit'])
                        </div>
                        <div class="modal-footer geo-modal__footer">
                            <button type="button" class="btn geo-modal__secondary-btn" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i>
                                <span>Cancel</span>
                            </button>
                            <button type="submit" class="btn geo-modal__primary-btn">
                                <i class="bi bi-check2-circle"></i>
                                <span>Update user</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="resetUserPasswordModal{{ $managedUser->id }}" tabindex="-1" aria-labelledby="resetUserPasswordModalLabel{{ $managedUser->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content geo-modal">
                    <div class="modal-header geo-modal__header">
                        <div class="geo-modal__title-wrap">
                            <span class="geo-modal__icon"><i class="bi bi-key"></i></span>
                            <div>
                                <h5 class="modal-title geo-modal__title" id="resetUserPasswordModalLabel{{ $managedUser->id }}">Reset password</h5>
                                <div class="geo-modal__subtitle">Set a new password for {{ $managedUser->name }}.</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form method="POST" action="{{ route('admin.users.password', $managedUser) }}">
                        @csrf
                        @method('PATCH')
                        <div class="modal-body geo-modal__body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="user_password_{{ $managedUser->id }}" class="form-label">New password</label>
                                    <input id="user_password_{{ $managedUser->id }}" type="password" name="password" class="form-control" required autocomplete="new-password">
                                </div>
                                <div class="col-12">
                                    <label for="user_password_confirmation_{{ $managedUser->id }}" class="form-label">Confirm new password</label>
                                    <input id="user_password_confirmation_{{ $managedUser->id }}" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
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

        <div class="modal fade" id="statusUserModal{{ $managedUser->id }}" tabindex="-1" aria-labelledby="statusUserModalLabel{{ $managedUser->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content geo-modal">
                    <div class="modal-header geo-modal__header">
                        <div class="geo-modal__title-wrap">
                            <span class="geo-modal__icon">
                                <i class="bi {{ $managedUser->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                            </span>
                            <div>
                                <h5 class="modal-title geo-modal__title" id="statusUserModalLabel{{ $managedUser->id }}">
                                    {{ $managedUser->is_active ? 'Inactivate user' : 'Activate user' }}
                                </h5>
                                <div class="geo-modal__subtitle">Change login access for this account.</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form method="POST" action="{{ route('admin.users.status', $managedUser) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="is_active" value="{{ $managedUser->is_active ? 0 : 1 }}">
                        <div class="modal-body geo-modal__body">
                            <p class="mb-0 text-muted">
                                Continue to {{ $managedUser->is_active ? 'inactivate' : 'activate' }} <strong>{{ $managedUser->name }}</strong>?
                            </p>
                        </div>
                        <div class="modal-footer geo-modal__footer">
                            <button type="button" class="btn geo-modal__secondary-btn" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i>
                                <span>Cancel</span>
                            </button>
                            <button type="submit" class="btn geo-modal__primary-btn {{ $managedUser->is_active ? 'violation-delete-btn' : '' }}">
                                <i class="bi {{ $managedUser->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                                <span>{{ $managedUser->is_active ? 'Inactivate user' : 'Activate user' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteUserModal{{ $managedUser->id }}" tabindex="-1" aria-labelledby="deleteUserModalLabel{{ $managedUser->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content geo-modal">
                    <div class="modal-header geo-modal__header">
                        <div class="geo-modal__title-wrap">
                            <span class="geo-modal__icon"><i class="bi bi-trash3"></i></span>
                            <div>
                                <h5 class="modal-title geo-modal__title" id="deleteUserModalLabel{{ $managedUser->id }}">Delete user</h5>
                                <div class="geo-modal__subtitle">This removes the login account.</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form method="POST" action="{{ route('admin.users.destroy', $managedUser) }}">
                        @csrf
                        @method('DELETE')
                        <div class="modal-body geo-modal__body">
                            <p class="mb-0 text-muted">
                                You are about to delete <strong>{{ $managedUser->name }}</strong>. Use inactivate if you only need to block login.
                            </p>
                        </div>
                        <div class="modal-footer geo-modal__footer">
                            <button type="button" class="btn geo-modal__secondary-btn" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i>
                                <span>Cancel</span>
                            </button>
                            <button type="submit" class="btn geo-modal__primary-btn violation-delete-btn">
                                <i class="bi bi-trash3"></i>
                                <span>Delete user</span>
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
    <style>
        .admin-role-pill {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0.28rem 0.6rem;
            border-radius: 8px;
            background: #eef8fc;
            color: #0d6f9b;
            font-size: 0.78rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .admin-role-pill--admin {
            background: #fff1f3;
            color: #b42318;
        }

        .admin-role-pill--road-officer {
            background: #eef4ff;
            color: #1849a9;
        }

        .admin-role-pill--driver {
            background: #ecfdf3;
            color: #027a48;
        }
    </style>
@endpush
