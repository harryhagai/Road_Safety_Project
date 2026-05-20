{{-- Authentication view for the side-panel step of the access flow. --}}

@php
    $panelItems = $items ?? [];
    $roles = $roles ?? [];
    $steps = $steps ?? [];
@endphp

<aside class="auth-brand-panel">
    <a href="{{ route('home') }}" class="auth-brand-mark">
        <img src="{{ asset('img/road-safety-favicon.svg') }}" alt="RSRS Logo">
        <span class="auth-brand-copy">
            <strong>RSRS-DASHBOARD</strong>
            <span>Road Safety Reporting System</span>
        </span>
    </a>

    @if (!empty($eyebrow))
        <div class="auth-kicker">
            <i class="bi {{ $eyebrowIcon ?? 'bi-shield-check' }}"></i>
            <span>{{ $eyebrow }}</span>
        </div>
    @endif

    <div class="auth-hero">
        <h1 class="auth-hero-title">{{ $title }}</h1>
        @if (!empty($description))
            <p class="auth-hero-copy">{{ $description }}</p>
        @endif
    </div>

    @if (!empty($roles))
        <div class="auth-role-row">
            @foreach ($roles as $role)
                <div class="auth-role-chip">
                    <i class="bi {{ $role['icon'] ?? 'bi-check-circle' }}"></i>
                    <span>{{ $role['label'] }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if (!empty($panelItems))
        <div class="auth-feature-list">
            @foreach ($panelItems as $item)
                <div class="auth-feature-card">
                    <div class="auth-feature-icon">
                        <i class="bi {{ $item['icon'] ?? 'bi-stars' }}"></i>
                    </div>
                    <div>
                        <strong>{{ $item['title'] }}</strong>
                        <span>{{ $item['text'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if (!empty($steps))
        <div class="auth-steps">
            <div class="auth-steps-title">Login Steps</div>
            @foreach ($steps as $index => $step)
                <div class="auth-step-card">
                    <div class="auth-step-number">{{ $index + 1 }}</div>
                    <div>
                        <strong>{{ $step['title'] }}</strong>
                        <span>{{ $step['text'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</aside>
