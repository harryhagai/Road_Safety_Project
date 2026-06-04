{{-- Blade view for the RSRS privacy page. --}}

@extends('layouts.app')

@section('title', 'Privacy - Road Safety Reporting System')

@section('content')
    <style>
        .privacy-page {
            min-height: 100vh;
            padding: 122px 0 64px;
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.95), rgba(243, 245, 249, 0.92) 38%, rgba(236, 239, 244, 1) 100%);
            color: var(--theme-text);
            font-family: var(--font-body);
        }

        .privacy-shell {
            max-width: 920px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .privacy-panel {
            border-radius: 8px;
            border: 1px solid var(--theme-border);
            background: rgba(255, 255, 255, 0.96);
            box-shadow: var(--theme-shadow-soft);
            padding: clamp(1.25rem, 4vw, 2.4rem);
        }

        .privacy-page h1,
        .privacy-page h2 {
            color: var(--theme-text-strong);
            font-family: var(--bs-body-font-family);
            letter-spacing: 0;
        }

        .privacy-page h1 {
            margin-bottom: 0.75rem;
            font-size: clamp(1.8rem, 4vw, 2.35rem);
            font-weight: 600;
        }

        .privacy-page h2 {
            margin: 1.6rem 0 0.6rem;
            font-size: 1.05rem;
            font-weight: 600;
        }

        .privacy-page p,
        .privacy-page li {
            color: var(--theme-text-muted);
            line-height: 1.72;
        }

        .privacy-page ul {
            margin-bottom: 0;
            padding-left: 1.25rem;
        }
    </style>

    <main class="privacy-page">
        <div class="privacy-shell">
            <article class="privacy-panel">
                <h1>RSRS Privacy Notice</h1>
                <p>
                    RSRS uses road safety reports to help officers identify unsafe driving patterns, risky locations,
                    and urgent violations. Reports can be submitted manually, and overspeeding reports can be created
                    automatically when the public map detects a matching speed rule violation.
                </p>

                <h2>Location Use</h2>
                <p>
                    The public map may use your browser location, current speed estimate, GPS accuracy, and heading to
                    match your position against saved road segments. This location check is used for map display and
                    automatic speed report evaluation.
                </p>

                <h2>Report Data</h2>
                <p>
                    Reports sent to the officer dashboard may include the selected violation type, description, time,
                    coordinates, resolved location name, evidence files when provided, and automatic rule-match details.
                </p>

                <h2>User Control</h2>
                <ul>
                    <li>Browser location access depends on your permission.</li>
                    <li>You can deny or revoke location access from browser settings.</li>
                    <li>Automatic reports are created only when the saved rule conditions are met.</li>
                    <li>Manual reports remain available when location access is unavailable.</li>
                </ul>
            </article>
        </div>
    </main>
@endsection
