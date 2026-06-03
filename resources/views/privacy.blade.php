{{-- Blade view for the RSRS privacy page used by the Android passenger app. --}}

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
                    RSRS uses road safety reports and trip telemetry to help officers identify unsafe driving patterns,
                    risky locations, and urgent violations. Passenger tracking starts only after the passenger chooses
                    to start a trip in the Android app.
                </p>

                <h2>Location Tracking</h2>
                <p>
                    During an active Android trip, RSRS may collect GPS coordinates, speed, accuracy, battery level, and
                    network status. Tracking stops when the passenger ends the trip or when the maximum trip duration is
                    reached.
                </p>

                <h2>Passenger Reports</h2>
                <p>
                    Violation reports submitted during a trip are sent to the officer dashboard for review. Reports may
                    include the selected violation type, description, time, and location.
                </p>

                <h2>User Control</h2>
                <ul>
                    <li>Tracking is not started silently.</li>
                    <li>A visible Android notification is shown while tracking is active.</li>
                    <li>Passengers can stop tracking from the app at any time.</li>
                    <li>Android location permissions can be changed from phone settings.</li>
                </ul>
            </article>
        </div>
    </main>
@endsection
