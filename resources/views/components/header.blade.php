{{-- Reusable Blade component used across multiple RSRS pages. --}}

<header class="header-wrapper">
    <div class="header-container">
        <div class="header-branding">
            <div class="header-logo">
                <div class="officer-sidebar-logo" aria-hidden="true">
                    <i class="bi bi-cone-striped officer-sidebar-logo-icon"></i>
                </div>
            </div>
            <span class="header-name">
                <span class="header-title">RSRS</span>
                <span class="header-subtitle">Road Safety Reporting System</span>
            </span>

        </div>

        <button class="header-toggle" id="navToggle" aria-label="Toggle Menu" aria-expanded="false" aria-controls="mainNav">
            <span class="header-toggle-line"></span>
            <span class="header-toggle-line"></span>
            <span class="header-toggle-line"></span>
        </button>

        <nav class="header-nav" id="mainNav">
            @php
                $currentPath = trim(request()->path(), '/');
                $currentUser = auth()->user();
            @endphp
            <ul>
                <li><a href="/" class="{{ $currentPath === '' ? 'active' : '' }}"><i class="bi bi-house-door"></i> Home</a></li>
                <li><a href="/about" class="{{ $currentPath === 'about' ? 'active' : '' }}"><i class="bi bi-info-circle"></i> About us</a></li>
                <li><a href="{{ route('contact') }}" class="{{ $currentPath === 'contact' ? 'active' : '' }}"><i class="bi bi-envelope-paper"></i> Contact</a></li>
                <li><a href="{{ route('privacy') }}" class="{{ $currentPath === 'privacy' ? 'active' : '' }}"><i class="bi bi-shield-lock"></i> Privacy</a></li>
                @if ($currentUser?->isDriver())
                    <li>
                        <a href="{{ route('driver.dashboard') }}" class="{{ str_starts_with($currentPath, 'driver/dashboard') ? 'active' : '' }}">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                @elseif ($currentUser?->canAccessOfficerWorkspace())
                    <li>
                        <a href="{{ route('officer.dashboard') }}">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                @elseif ($currentUser)
                    <li>
                        <a href="{{ route('home') }}">
                            <i class="bi bi-person-check"></i> {{ str($currentUser->role)->replace('_', ' ')->title() }}
                        </a>
                    </li>
                @else
                    <li><a href="{{ route('login') }}" class="{{ $currentPath === 'login' ? 'active' : '' }}"><i class="bi bi-person-circle"></i> Login</a></li>
                @endif
            </ul>
        </nav>
    </div>
</header>
