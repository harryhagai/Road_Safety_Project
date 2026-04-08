@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
    <section class="auth-page auth-login-page">
        <div class="auth-login-frame">
            <div class="auth-login-shell">
                <aside class="auth-login-brand-panel">
                    <a href="{{ route('home') }}" class="auth-login-brand">
                        <i class="bi bi-cone-striped auth-login-brand-icon" aria-hidden="true"></i>
                        <span>
                            <strong>Road Safety Reporting System</strong>
                            <small>Authorized officer portal</small>
                        </span>
                    </a>

                    <div class="auth-login-copy">
                        <h1>Recover officer account access</h1>
                        <p>Enter your authorized email address and we will send a secure password reset link to restore access to the road safety system.</p>
                    </div>

                    <div class="auth-login-guide">
                        <h3>How this works</h3>
                        <ul>
                            <li>Enter the officer email address linked to your account.</li>
                            <li>The system checks whether that email exists in the authorized users list.</li>
                            <li>If it exists, a reset link is sent to that same email for secure recovery.</li>
                        </ul>
                    </div>

                    <div class="auth-login-watermark" aria-hidden="true">
                        <i class="bi bi-key"></i>
                    </div>
                </aside>

                <div class="auth-login-form-panel">
                    <div class="auth-login-form-card">
                        <div class="auth-login-title-row">
                            <h2 class="auth-login-title">
                                <i class="bi bi-envelope-paper"></i>
                                <span>Request Password Reset</span>
                            </h2>
                        </div>

                        <p class="auth-login-subtitle">Use your officer email only. If the account exists, we will email a password reset link for secure access recovery.</p>

                        @include('auth.partials.feedback')

                        <form method="POST" action="{{ route('password.email') }}" class="auth-form auth-login-form" data-auth-form>
                            @csrf

                            <div class="auth-input-group">
                                <label for="email">Email Address</label>
                                <div class="auth-input-wrap auth-login-input @error('email') is-invalid @enderror">
                                    <i class="bi bi-envelope auth-input-icon"></i>
                                    <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="officer@roadsafety.test" required autocomplete="email">
                                </div>
                                @error('email')
                                    <div class="auth-field-error">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn-brand auth-login-button auth-forgot-button" data-auth-submit data-loading-text="Sending link...">
                                <i class="bi bi-send"></i>
                                <span data-auth-submit-label>Send Reset Link</span>
                            </button>

                            <div class="auth-login-back-wrap">
                                <a href="{{ route('login') }}" class="auth-login-back-link">
                                    <i class="bi bi-arrow-left"></i>
                                    <span>Back to Officer Login</span>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script src="{{ asset('js/rsrsAuth.js') }}"></script>
@endsection
