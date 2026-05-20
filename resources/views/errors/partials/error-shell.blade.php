{{-- Error page template rendered when this HTTP status is triggered. --}}

@php
    $tips = $tips ?? [];
    $primaryAction = $primaryAction ?? null;
    $secondaryAction = $secondaryAction ?? null;
    $actions = $actions ?? array_values(array_filter([$primaryAction, $secondaryAction]));
@endphp

<section class="auth-page auth-error-page">
    <div class="auth-error-shell">
        <div class="auth-error-panel auth-error-panel-soft">
            <div class="auth-error-code">{{ $code }}</div>
            <div class="auth-error-badge">
                <i class="bi {{ $badgeIcon ?? 'bi-shield-exclamation' }}"></i>
                <span>{{ $badgeText ?? 'Request Issue' }}</span>
            </div>
            <h1 class="auth-error-title">{{ $title }}</h1>
            <p class="auth-error-summary">{{ $summary }}</p>

            @if (!empty($tips))
                <div class="auth-error-tips">
                    <h2>What you can do next</h2>
                    <ul>
                        @foreach ($tips as $tip)
                            <li>{{ $tip }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="auth-error-panel auth-error-panel-main">
            <div class="auth-error-icon">
                <i class="bi {{ $icon ?? 'bi-exclamation-circle' }}"></i>
            </div>
            <h2 class="auth-error-heading">{{ $heading ?? $title }}</h2>
            <p class="auth-error-copy">{{ $detail }}</p>

            @if (!empty($actions))
                <div class="auth-error-actions">
                    @foreach ($actions as $action)
                        @php
                            $variant = $action['variant'] ?? ($loop->first ? 'primary' : 'secondary');
                            $buttonClass = $variant === 'secondary'
                                ? 'auth-error-btn auth-error-btn-outline'
                                : 'btn-brand auth-error-btn';
                        @endphp
                        <a href="{{ $action['href'] }}" class="{{ $buttonClass }}">
                            <i class="bi {{ $action['icon'] ?? 'bi-arrow-right-circle' }}"></i>
                            <span>{{ $action['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
