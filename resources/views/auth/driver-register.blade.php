@extends('layouts.auth')

@section('title', 'Driver Registration')

@section('content')
    <section class="auth-page driver-register-page">
        <div class="auth-shell">
            <aside class="auth-brand-panel driver-register-brand-panel">
                <a href="{{ route('home') }}" class="auth-brand-mark">
                    <i class="bi bi-car-front-fill fs-2" aria-hidden="true"></i>
                    <span class="auth-brand-copy">
                        <strong>Road Safety Reporting System</strong>
                        <span>Driver registration</span>
                    </span>
                </a>

                <span class="auth-kicker driver-register-kicker">
                    <i class="bi bi-person-vcard"></i> Identified reporting
                </span>

                <div class="driver-register-left-fields">
                    <div class="auth-input-group">
                        <label for="name">Driver full name</label>
                        <div class="auth-input-wrap @error('name') is-invalid @enderror">
                            <i class="bi bi-person auth-input-icon"></i>
                            <input id="name" form="driverRegistrationForm" type="text" name="name" value="{{ old('name') }}" placeholder="Full name" required autofocus autocomplete="name">
                        </div>
                        @error('name')<div class="auth-field-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="auth-input-group">
                        <label for="vehicle_name">Vehicle name</label>
                        <div class="auth-input-wrap @error('vehicle_name') is-invalid @enderror">
                            <i class="bi bi-car-front auth-input-icon"></i>
                            <input id="vehicle_name" form="driverRegistrationForm" type="text" name="vehicle_name" value="{{ old('vehicle_name') }}" placeholder="Example: Toyota Hiace" required>
                        </div>
                        @error('vehicle_name')<div class="auth-field-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="auth-input-group">
                        <label for="plate_number">Plate number</label>
                        <div class="auth-input-wrap @error('plate_number') is-invalid @enderror">
                            <i class="bi bi-credit-card-2-front auth-input-icon"></i>
                            <input id="plate_number" form="driverRegistrationForm" type="text" name="plate_number" value="{{ old('plate_number') }}" placeholder="Example: T 123 ABC" required>
                        </div>
                        @error('plate_number')<div class="auth-field-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="auth-input-group">
                        <label for="organization">Organization</label>
                        <div class="auth-input-wrap @error('organization') is-invalid @enderror">
                            <i class="bi bi-building auth-input-icon"></i>
                            <input id="organization" form="driverRegistrationForm" type="text" name="organization" value="{{ old('organization') }}" placeholder="Company or organization" required>
                        </div>
                        @error('organization')<div class="auth-field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            </aside>

            <div class="auth-form-panel">
                <div class="auth-form-card">
                  

                    <span class="auth-panel-kicker"><i class="bi bi-person-plus"></i> New driver</span>
                    <h2 class="auth-panel-title">Create your account</h2>
                    <p class="auth-panel-copy">Enter the driver's personal and security details.</p>

                    @include('auth.partials.feedback')

                    <form id="driverRegistrationForm" method="POST" action="{{ route('driver.register.submit') }}" class="auth-form" data-auth-form>
                        @csrf

                        <div class="auth-input-group">
                            <label for="email">Email address</label>
                            <div class="auth-input-wrap @error('email') is-invalid @enderror">
                                <i class="bi bi-envelope auth-input-icon"></i>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="driver@example.com" required autocomplete="email">
                            </div>
                            @error('email')<div class="auth-field-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="auth-input-group">
                            <label for="password">Password</label>
                            <div class="auth-input-wrap @error('password') is-invalid @enderror">
                                <i class="bi bi-lock auth-input-icon"></i>
                                <input id="password" type="password" name="password" placeholder="At least 8 characters" required autocomplete="new-password">
                                <button type="button" class="auth-password-toggle" data-password-toggle="password" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            @error('password')<div class="auth-field-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="auth-input-group">
                            <label for="password_confirmation">Confirm password</label>
                            <div class="auth-input-wrap">
                                <i class="bi bi-lock-fill auth-input-icon"></i>
                                <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Repeat the password" required autocomplete="new-password">
                                <button type="button" class="auth-password-toggle" data-password-toggle="password_confirmation" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn-brand driver-register-submit" data-auth-submit data-loading-text="Creating account...">
                            <i class="bi bi-person-plus-fill" aria-hidden="true"></i>
                            <span data-auth-submit-label>Create Driver Account</span>
                        </button>

                        <div class="auth-form-links">
                            <p>Already registered? <a href="{{ route('driver.login') }}" class="auth-text-link">Login</a></p>
                        </div>

                        {{-- back to homepage link  --}}
                         <div class="auth-login-back-wrap">
                                <a href="{{ route('home') }}" class="auth-login-back-link">
                                    <i class="bi bi-arrow-left"></i>
                                    <span>Back to Home</span>
                                </a>
                            </div>

                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script src="{{ asset('js/rsrsAuth.js') }}"></script>
@endsection
