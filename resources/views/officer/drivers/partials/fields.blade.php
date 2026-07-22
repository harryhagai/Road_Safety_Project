@php
    $prefix = $mode === 'create' ? 'create_driver' : 'edit_driver_'.$driver->id;
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="{{ $prefix }}_name" class="form-label">Driver full name</label>
        <input id="{{ $prefix }}_name" type="text" name="name" class="form-control" value="{{ old('name', $driver?->name) }}" required>
    </div>
    <div class="col-md-6">
        <label for="{{ $prefix }}_email" class="form-label">Email address</label>
        <input id="{{ $prefix }}_email" type="email" name="email" class="form-control" value="{{ old('email', $driver?->email) }}" required>
    </div>
    <div class="col-md-6">
        <label for="{{ $prefix }}_vehicle_name" class="form-label">Vehicle name</label>
        <input id="{{ $prefix }}_vehicle_name" type="text" name="vehicle_name" class="form-control" value="{{ old('vehicle_name', $driver?->vehicle_name) }}" required>
    </div>
    <div class="col-md-6">
        <label for="{{ $prefix }}_plate_number" class="form-label">Plate number</label>
        <input id="{{ $prefix }}_plate_number" type="text" name="plate_number" class="form-control" value="{{ old('plate_number', $driver?->plate_number) }}" placeholder="T 123 ABC" required>
    </div>
    <div class="col-12">
        <label for="{{ $prefix }}_organization" class="form-label">Organization</label>
        <input id="{{ $prefix }}_organization" type="text" name="organization" class="form-control" value="{{ old('organization', $driver?->organization) }}" required>
    </div>

    @if ($mode === 'create')
        <div class="col-md-6">
            <label for="{{ $prefix }}_password" class="form-label">Password</label>
            <input id="{{ $prefix }}_password" type="password" name="password" class="form-control" required autocomplete="new-password">
        </div>
        <div class="col-md-6">
            <label for="{{ $prefix }}_password_confirmation" class="form-label">Confirm password</label>
            <input id="{{ $prefix }}_password_confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
        </div>
    @endif

    <div class="col-12">
        <div class="form-check form-switch mt-2">
            <input
                id="{{ $prefix }}_is_active"
                class="form-check-input"
                type="checkbox"
                role="switch"
                name="is_active"
                value="1"
                @checked(old('is_active', $driver?->is_active ?? true))
            >
            <label class="form-check-label" for="{{ $prefix }}_is_active">Driver account is active</label>
        </div>
    </div>
</div>
