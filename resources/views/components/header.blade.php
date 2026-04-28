<header class="header-wrapper">
    <div class="header-container">
        <div class="header-branding">
            <div class="header-logo">
                <img src="{{ asset('img/road-safety-favicon.svg') }}" alt="RSRS Logo"
                    style="height: 50px; border-radius: 12px;" />
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
            @endphp
            <ul>
                <li><a href="/" class="{{ $currentPath === '' ? 'active' : '' }}"><i class="bi bi-house-door"></i> Home</a></li>
                <li><a href="/about" class="{{ $currentPath === 'about' ? 'active' : '' }}"><i class="bi bi-info-circle"></i> About us</a></li>
                <li><a href="{{ route('contact') }}" class="{{ $currentPath === 'contact' ? 'active' : '' }}"><i class="bi bi-envelope-paper"></i> Contact</a></li>
                <li><a href="{{ route('hotspots.index') }}" class="{{ $currentPath === 'hotspots' ? 'active' : '' }}"><i class="bi bi-geo-alt"></i> Hotspots</a></li>
                <li><a href="{{ route('developer') }}" class="{{ $currentPath === 'developers' ? 'active' : '' }}"><i class="bi bi-code-slash"></i> Developers</a></li>
                <li><a href="/login" class="{{ $currentPath === 'login' ? 'active' : '' }}"><i class="bi bi-person-circle"></i> Login</a></li>
            </ul>
        </nav>
    </div>
</header>
