@php
    $isSelf = $managedUser && auth()->id() === $managedUser->id;
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="user_name_{{ $mode }}_{{ $managedUser?->id ?? 'new' }}" class="form-label">Full name</label>
        <input id="user_name_{{ $mode }}_{{ $managedUser?->id ?? 'new' }}" type="text" name="name" class="form-control" value="{{ old('name', $managedUser?->name) }}" required maxlength="191">
    </div>

    <div class="col-md-6">
        <label for="user_email_{{ $mode }}_{{ $managedUser?->id ?? 'new' }}" class="form-label">Email</label>
        <input id="user_email_{{ $mode }}_{{ $managedUser?->id ?? 'new' }}" type="email" name="email" class="form-control" value="{{ old('email', $managedUser?->email) }}" required maxlength="191">
    </div>

    <div class="col-md-6">
        <label for="user_role_{{ $mode }}_{{ $managedUser?->id ?? 'new' }}" class="form-label">Role</label>
        <select id="user_role_{{ $mode }}_{{ $managedUser?->id ?? 'new' }}" name="role" class="form-select" required @disabled($isSelf)>
            @foreach ($roles as $role)
                <option value="{{ $role }}" @selected(old('role', $managedUser?->role ?? \App\Models\User::ROLE_PASSENGER) === $role)>
                    {{ $roleLabels[$role] ?? $role }}
                </option>
            @endforeach
        </select>
        @if ($isSelf)
            <input type="hidden" name="role" value="{{ \App\Models\User::ROLE_ADMIN }}">
        @endif
    </div>

    <div class="col-md-6">
        <label for="user_status_{{ $mode }}_{{ $managedUser?->id ?? 'new' }}" class="form-label">Access status</label>
        <select id="user_status_{{ $mode }}_{{ $managedUser?->id ?? 'new' }}" name="is_active" class="form-select" @disabled($isSelf)>
            <option value="1" @selected(old('is_active', $managedUser?->is_active ?? true))>Active</option>
            <option value="0" @selected(! old('is_active', $managedUser?->is_active ?? true))>Inactive</option>
        </select>
        @if ($isSelf)
            <input type="hidden" name="is_active" value="1">
        @endif
    </div>

    @if ($mode === 'create')
        <div class="col-md-6">
            <label for="user_password_new" class="form-label">Password</label>
            <input id="user_password_new" type="password" name="password" class="form-control" required autocomplete="new-password">
        </div>

        <div class="col-md-6">
            <label for="user_password_confirmation_new" class="form-label">Confirm password</label>
            <input id="user_password_confirmation_new" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
        </div>
    @endif

    <div class="col-md-4">
        <label for="user_vehicle_{{ $mode }}_{{ $managedUser?->id ?? 'new' }}" class="form-label">Vehicle name</label>
        <input id="user_vehicle_{{ $mode }}_{{ $managedUser?->id ?? 'new' }}" type="text" name="vehicle_name" class="form-control" value="{{ old('vehicle_name', $managedUser?->vehicle_name) }}" maxlength="191">
    </div>

    <div class="col-md-4">
        <label for="user_plate_{{ $mode }}_{{ $managedUser?->id ?? 'new' }}" class="form-label">Plate number</label>
        <input id="user_plate_{{ $mode }}_{{ $managedUser?->id ?? 'new' }}" type="text" name="plate_number" class="form-control" value="{{ old('plate_number', $managedUser?->plate_number) }}" maxlength="50">
    </div>

    <div class="col-md-4">
        <label for="user_organization_{{ $mode }}_{{ $managedUser?->id ?? 'new' }}" class="form-label">Organization</label>
        <input id="user_organization_{{ $mode }}_{{ $managedUser?->id ?? 'new' }}" type="text" name="organization" class="form-control" value="{{ old('organization', $managedUser?->organization) }}" maxlength="191">
    </div>
</div>
