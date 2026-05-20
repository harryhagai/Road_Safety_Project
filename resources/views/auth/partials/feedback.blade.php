{{-- Authentication view for the feedback step of the access flow. --}}

<div class="auth-feedback">
    @if (session('status'))
        <div class="auth-alert auth-alert-info">{{ session('status') }}</div>
    @endif

    @if (session('success'))
        <div class="auth-alert auth-alert-success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="auth-alert auth-alert-error">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="auth-alert auth-alert-error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
