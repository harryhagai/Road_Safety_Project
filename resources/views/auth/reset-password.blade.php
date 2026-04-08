@extends('layouts.auth')

@section('title', 'Reset Password')

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
                        <h1>Set a new officer password</h1>
                        <p>Create a fresh password for your road safety officer account and continue securely.</p>
                    </div>

                    <div class="auth-login-guide">
                        <h3>How to complete this step</h3>
                        <ul>
                            <li>Use a password with at least 8 characters.</li>
                            <li>Include letters and numbers for stronger officer account security.</li>
                            <li>Confirm the same password before saving your update.</li>
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
                                <i class="bi bi-shield-lock"></i>
                                <span>Reset Officer Password</span>
                            </h2>
                        </div>

                        <p class="auth-login-subtitle">Update your password below to regain secure access to the road safety system.</p>

                        @include('auth.partials.feedback')

                        <form method="POST" action="{{ route('password.update') }}" class="auth-form auth-login-form" data-auth-form>
                            @csrf
                            <input type="hidden" name="token" value="{{ $token }}">
                            <input type="hidden" name="email" value="{{ $email }}">

                            <div class="auth-input-group">
                                <label for="reset_email">Email Address</label>
                                <div class="auth-input-wrap auth-login-input is-readonly">
                                    <i class="bi bi-envelope auth-input-icon"></i>
                                    <input id="reset_email" type="email" value="{{ $email }}" readonly>
                                </div>
                            </div>

                            <div class="auth-input-group">
                                <label for="password">New Password</label>
                                <div class="auth-input-wrap auth-login-input @error('password') is-invalid @enderror">
                                    <i class="bi bi-lock auth-input-icon"></i>
                                    <input id="password" type="password" name="password" placeholder="Create a strong password" required autocomplete="new-password" data-password-rules-target>
                                    <button type="button" class="auth-password-toggle" data-password-toggle="password" aria-label="Toggle new password visibility">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                @error('password')
                                    <div class="auth-field-error">{{ $message }}</div>
                                @enderror
                            </div>

                            <ul class="auth-rules">
                                <li data-password-rule="length">Minimum 8 characters</li>
                                <li data-password-rule="letter">Contains letters</li>
                                <li data-password-rule="number">Contains numbers</li>
                            </ul>

                            <div class="auth-input-group">
                                <label for="password_confirmation">Confirm Password</label>
                                <div class="auth-input-wrap auth-login-input @error('password_confirmation') is-invalid @enderror">
                                    <i class="bi bi-lock auth-input-icon"></i>
                                    <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Repeat the new password" required autocomplete="new-password">
                                    <button type="button" class="auth-password-toggle" data-password-toggle="password_confirmation" aria-label="Toggle confirm password visibility">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                @error('password_confirmation')
                                    <div class="auth-field-error">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn-brand auth-login-button auth-forgot-button" data-auth-submit data-loading-text="Updating password...">
                                <i class="bi bi-check-circle"></i>
                                <span data-auth-submit-label>Save New Password</span>
                            </button>

                            <div class="auth-login-back-wrap">
                                <a href="{{ route('login') }}" class="auth-login-back-link">
                                    <i class="bi bi-arrow-left"></i>
                                    <span>Return to Officer Login</span>
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
