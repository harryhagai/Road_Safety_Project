@php
    $prefix = $mode === 'edit' ? 'edit-' : 'create-';
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="{{ $prefix }}name" class="form-label">Name</label>
        <input id="{{ $prefix }}name" type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', 'Default SMTP') }}" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="{{ $prefix }}purpose" class="form-label">Email Purpose</label>
        <select
            id="{{ $prefix }}purpose"
            name="purpose"
            class="form-select @error('purpose') is-invalid @enderror"
            data-mail-purpose-select="{{ $prefix }}"
            required
        >
            @foreach(($purposes ?? []) as $purposeKey => $purposeLabel)
                <option value="{{ $purposeKey }}" @selected(old('purpose', 'password_reset') === $purposeKey)>{{ $purposeLabel }}</option>
            @endforeach
            <option value="other" @selected(old('purpose') === 'other')>Other (Custom)</option>
        </select>
        @error('purpose')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-6 d-none" data-mail-purpose-other-wrapper="{{ $prefix }}">
        <label for="{{ $prefix }}purpose-other" class="form-label">Custom Purpose Key</label>
        <input
            id="{{ $prefix }}purpose-other"
            type="text"
            name="purpose_other"
            class="form-control @error('purpose_other') is-invalid @enderror"
            value="{{ old('purpose_other') }}"
            placeholder="example: exam_announcements"
            data-mail-purpose-other-input="{{ $prefix }}"
        >
        @error('purpose_other')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3">
        <label for="{{ $prefix }}mailer" class="form-label">Mailer</label>
        <select id="{{ $prefix }}mailer" name="mailer" class="form-select @error('mailer') is-invalid @enderror" required>
            <option value="smtp" @selected(old('mailer', 'smtp') === 'smtp')>SMTP</option>
        </select>
        @error('mailer')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3">
        <label for="{{ $prefix }}scheme" class="form-label">Scheme</label>
        <select id="{{ $prefix }}scheme" name="scheme" class="form-select @error('scheme') is-invalid @enderror">
            <option value="">None</option>
            <option value="smtp" @selected(old('scheme') === 'smtp')>SMTP / TLS</option>
            <option value="smtps" @selected(old('scheme') === 'smtps')>SMTPS / SSL</option>
        </select>
        @error('scheme')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-8">
        <label for="{{ $prefix }}host" class="form-label">Host</label>
        <input id="{{ $prefix }}host" type="text" name="host" class="form-control @error('host') is-invalid @enderror" value="{{ old('host', '127.0.0.1') }}" required>
        @error('host')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="{{ $prefix }}port" class="form-label">Port</label>
        <input id="{{ $prefix }}port" type="number" min="1" max="65535" name="port" class="form-control @error('port') is-invalid @enderror" value="{{ old('port', 2525) }}" required>
        @error('port')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="{{ $prefix }}username" class="form-label">Username</label>
        <input id="{{ $prefix }}username" type="text" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username') }}">
        @error('username')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="{{ $prefix }}password" class="form-label">Password</label>
        <div class="input-group">
            <input id="{{ $prefix }}password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" placeholder="{{ $mode === 'edit' ? 'Leave blank to keep current password' : '' }}">
            <button type="button" class="btn btn-outline-secondary" data-mail-password-toggle="{{ $prefix }}password" aria-label="Show password" data-no-spinner>
                <i class="bi bi-eye"></i>
            </button>
        </div>
        @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="{{ $prefix }}from-address" class="form-label">From Email</label>
        <input id="{{ $prefix }}from-address" type="email" name="from_address" class="form-control @error('from_address') is-invalid @enderror" value="{{ old('from_address', config('mail.from.address')) }}" required>
        @error('from_address')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="{{ $prefix }}from-name" class="form-label">From Name</label>
        <input id="{{ $prefix }}from-name" type="text" name="from_name" class="form-control @error('from_name') is-invalid @enderror" value="{{ old('from_name', config('mail.from.name')) }}" required>
        @error('from_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input id="{{ $prefix }}is-active" type="checkbox" name="is_active" value="1" class="form-check-input" @checked((bool) old('is_active', false))>
            <label for="{{ $prefix }}is-active" class="form-check-label">Use this setting for outgoing email</label>
        </div>
    </div>
</div>
