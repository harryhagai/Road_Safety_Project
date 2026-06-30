{{-- Blade view for the about page in the RSRS application. --}}

@extends('layouts.app')

@section('title', 'About RSRS')

@section('content')
    @php
        $objectives = [
            ['icon' => 'bi-phone', 'title' => 'Mobile-first reporting', 'text' => 'A Bootstrap web interface that supports registered driver tracking and evidence-backed passenger reports from phones or desktop devices.'],
            ['icon' => 'bi-geo-alt', 'title' => 'Geospatial accuracy', 'text' => 'Map-based location capture helps reports include coordinates, nearby places, and safer context for response teams.'],
            ['icon' => 'bi-shield-lock', 'title' => 'Secure officer portal', 'text' => 'Authorized road officers can log in, review reports, manage rules, and make decisions from a protected dashboard.'],
            ['icon' => 'bi-graph-up-arrow', 'title' => 'Preventive analysis', 'text' => 'Reported patterns can reveal risky roads, recurring violations, and hotspot areas before more serious crashes happen.'],
        ];

        $stakeholders = [
            ['icon' => 'bi-car-front', 'title' => 'Registered drivers', 'text' => 'Track movement and submit reports linked to a driver, vehicle, plate number, and organization.'],
            ['icon' => 'bi-person-walking', 'title' => 'Passengers', 'text' => 'Submit bus details and capture direct camera evidence without creating an account.'],
            ['icon' => 'bi-person-badge', 'title' => 'Road officers', 'text' => 'Receive structured reports, review evidence, update status, and coordinate enforcement actions.'],
            ['icon' => 'bi-building-check', 'title' => 'Transport authorities', 'text' => 'Use location data, analytics, and summaries to support planning and road safety decisions.'],
        ];

        $features = [
            'Identified driver traffic violation reporting',
            'Passenger bus reporting with direct camera evidence',
            'Map location selection and GPS coordinates',
            'Evidence upload support for photos or videos',
            'Reference number tracking for submitted reports',
            'Officer dashboard for verification and status updates',
            'Road rules, road segments, and hotspot management',
        ];

        $architecture = [
            ['label' => 'Presentation Tier', 'text' => 'Public reporting pages, map views, tracking tools, and officer dashboard screens.'],
            ['label' => 'Application Tier', 'text' => 'Laravel handles validation, authentication, report processing, notifications, and system rules.'],
            ['label' => 'Data Tier', 'text' => 'MySQL stores user accounts, roles, reports, evidence files, violation types, road rules, segments, and hotspot records.'],
        ];

        $technologies = ['HTML5', 'CSS3', 'Bootstrap 5', 'Bootstrap Icons', 'JavaScript', 'Laravel', 'PHP', 'MySQL', 'Google Maps API'];
    @endphp

    <style>
        .about-page {
            --about-navy: #232c3a;
            --about-navy-dark: #1b2230;
            --about-gold: #f3b74a;
            --about-blue-soft: #eaf1ff;
            --about-muted: #6f7c90;
            --about-border: rgba(35, 44, 58, 0.1);
            --about-shadow: 0 22px 60px rgba(27, 35, 48, 0.12);
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.95), rgba(243, 245, 249, 0.92) 38%, rgba(236, 239, 244, 1) 100%);
            color: #273244;
            font-family: var(--font-body);
        }

        .about-page h1,
        .about-page h2,
        .about-page h3,
        .about-page h4,
        .about-page h5,
        .about-page p,
        .about-page li,
        .about-page span {
            font-family: var(--font-body);
        }

        .about-hero {
            padding: clamp(2rem, 5vw, 4.25rem) 0 2rem;
        }

        .about-hero-shell {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(320px, 0.92fr);
            overflow: hidden;
            border-radius: 22px;
            border: 1px solid rgba(26, 35, 51, 0.06);
            background: #ffffff;
            box-shadow: var(--about-shadow);
        }

        .about-hero-copy {
            padding: clamp(1.8rem, 4vw, 3rem);
        }

        .about-kicker,
        .about-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border-radius: 999px;
            font-weight: 700;
        }

        .about-kicker {
            margin-bottom: 1rem;
            padding: 0.45rem 0.75rem;
            background: var(--about-blue-soft);
            color: var(--about-navy);
            font-size: 0.78rem;
        }

        .about-kicker i,
        .about-pill i,
        .about-section-title i,
        .about-list i {
            color: var(--about-gold);
        }

        .about-hero-title {
            max-width: 720px;
            margin: 0;
            color: var(--about-navy);
            font-size: clamp(2.25rem, 4.5vw, 4.35rem);
            line-height: 1.05;
            font-weight: 800;
            letter-spacing: 0;
        }

        .about-hero-lead {
            max-width: 650px;
            margin: 1rem 0 0;
            color: var(--about-muted);
            font-size: 1rem;
            line-height: 1.78;
        }

        .about-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .about-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 44px;
            padding: 0.75rem 1rem;
            border-radius: 999px;
            font-size: 0.9rem;
            font-weight: 700;
            text-decoration: none;
            transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }

        .about-btn-primary {
            border: 1px solid var(--about-navy);
            background: var(--about-navy);
            color: #ffffff;
        }

        .about-btn-primary:hover,
        .about-btn-primary:focus {
            background: var(--about-navy-dark);
            border-color: var(--about-navy-dark);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .about-btn-outline {
            border: 1px solid rgba(35, 44, 58, 0.16);
            background: #ffffff;
            color: var(--about-navy);
        }

        .about-btn-outline:hover,
        .about-btn-outline:focus {
            background: var(--about-blue-soft);
            color: var(--about-navy);
            transform: translateY(-1px);
        }

        .about-hero-panel {
            position: relative;
            display: flex;
            min-height: 100%;
            padding: clamp(1.8rem, 4vw, 2.8rem);
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.04), transparent 22%),
                linear-gradient(180deg, var(--about-navy) 0%, #1f2835 100%);
            color: #ffffff;
        }

        .about-system-card {
            width: 100%;
            align-self: stretch;
            display: grid;
            align-content: center;
            gap: 1rem;
        }

        .about-logo-mark {
            width: 72px;
            height: 72px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            background: rgba(243, 183, 74, 0.14);
            color: var(--about-gold);
            font-size: 2rem;
        }

        .about-hero-panel h2 {
            margin: 0;
            max-width: 360px;
            color: #ffffff;
            font-size: clamp(1.65rem, 3vw, 2.45rem);
            line-height: 1.12;
            font-weight: 800;
        }

        .about-hero-panel p {
            max-width: 380px;
            margin: 0;
            color: rgba(237, 242, 247, 0.9);
            line-height: 1.68;
        }

        .about-pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
        }

        .about-pill {
            padding: 0.42rem 0.7rem;
            border: 1px solid rgba(255, 194, 92, 0.18);
            background: rgba(255, 255, 255, 0.08);
            color: #f8fafc;
            font-size: 0.78rem;
        }

        .about-section {
            padding: clamp(2rem, 5vw, 4rem) 0;
        }

        .about-section-title {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin: 0 0 0.75rem;
            color: var(--about-navy);
            font-size: clamp(1.6rem, 3vw, 2.35rem);
            font-weight: 800;
        }

        .about-section-copy {
            max-width: 760px;
            margin: 0 0 1.4rem;
            color: var(--about-muted);
            line-height: 1.75;
        }

        .about-card {
            height: 100%;
            padding: 1.25rem;
            border: 1px solid var(--about-border);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 14px 34px rgba(27, 35, 48, 0.06);
        }

        .about-card-icon {
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.95rem;
            border-radius: 14px;
            background: var(--about-blue-soft);
            color: var(--about-navy);
            font-size: 1.1rem;
        }

        .about-card h3,
        .about-card h4 {
            margin: 0 0 0.55rem;
            color: var(--about-navy);
            font-size: 1.05rem;
            font-weight: 800;
        }

        .about-card p {
            margin: 0;
            color: var(--about-muted);
            line-height: 1.65;
            font-size: 0.93rem;
        }

        .about-highlight {
            border-radius: 22px;
            background: #ffffff;
            border: 1px solid rgba(26, 35, 51, 0.06);
            box-shadow: var(--about-shadow);
            overflow: hidden;
        }

        .about-highlight-dark {
            height: 100%;
            padding: clamp(1.5rem, 3vw, 2.25rem);
            background: linear-gradient(180deg, var(--about-navy) 0%, #1f2835 100%);
            color: #ffffff;
        }

        .about-highlight-dark h2,
        .about-highlight-dark p {
            color: #ffffff;
        }

        .about-highlight-dark p {
            color: rgba(237, 242, 247, 0.9);
        }

        .about-highlight-body {
            padding: clamp(1.5rem, 3vw, 2.25rem);
        }

        .about-list {
            display: grid;
            gap: 0.8rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .about-list li {
            display: flex;
            gap: 0.65rem;
            color: #3c4656;
            line-height: 1.55;
        }

        .about-tech-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
        }

        .about-tech {
            display: inline-flex;
            align-items: center;
            min-height: 36px;
            padding: 0.48rem 0.78rem;
            border-radius: 999px;
            background: var(--about-blue-soft);
            color: var(--about-navy);
            font-size: 0.84rem;
            font-weight: 700;
        }

        .about-cta {
            margin-bottom: clamp(2rem, 5vw, 4rem);
            padding: clamp(1.5rem, 3vw, 2.35rem);
            border-radius: 22px;
            background: linear-gradient(180deg, var(--about-navy) 0%, #1f2835 100%);
            color: #ffffff;
        }

        .about-cta h2 {
            margin: 0;
            color: #ffffff;
            font-size: clamp(1.5rem, 3vw, 2.25rem);
            font-weight: 800;
        }

        .about-cta p {
            margin: 0.65rem 0 0;
            color: rgba(237, 242, 247, 0.88);
            line-height: 1.65;
        }

        .about-cta .about-btn-outline {
            border-color: rgba(255, 255, 255, 0.22);
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        .about-cta .about-btn-outline:hover,
        .about-cta .about-btn-outline:focus {
            background: rgba(255, 255, 255, 0.16);
            color: #ffffff;
        }

        @media (max-width: 991.98px) {
            .about-hero-shell {
                grid-template-columns: 1fr;
            }

            .about-hero-panel {
                min-height: 340px;
            }
        }

        @media (max-width: 575.98px) {
            .about-hero {
                padding-top: 1rem;
            }

            .about-hero-shell,
            .about-highlight,
            .about-cta {
                border-radius: 18px;
            }

            .about-actions,
            .about-cta .about-actions {
                display: grid;
            }

            .about-btn {
                width: 100%;
            }
        }
    </style>

    <div class="about-page">
        <section class="about-hero">
            <div class="container">
                <div class="about-hero-shell">
                    <div class="about-hero-copy">
                        <span class="about-kicker">
                            <i class="bi bi-cone-striped" aria-hidden="true"></i>
                            Confidential Road Safety Reporting
                        </span>
                        <h1 class="about-hero-title">A web portal for safer roads through identified and evidence-backed reporting.</h1>
                        <p class="about-hero-lead">
                            RSRS is designed to help registered drivers and passengers report traffic violations, unsafe driving,
                            and risky road conditions in real time. The system combines identified driver reporting, passenger evidence,
                            map-based location capture, and officer decision tools for better
                            road safety management in Tanzania.
                        </p>
                        <div class="about-actions">
                            <a href="{{ route('home') }}" class="about-btn about-btn-primary">
                                <i class="bi bi-map" aria-hidden="true"></i>
                                View live map
                            </a>
                            <a href="{{ route('login') }}" class="about-btn about-btn-outline">
                                <i class="bi bi-person-badge" aria-hidden="true"></i>
                                Officer login
                            </a>
                        </div>
                    </div>
                    <aside class="about-hero-panel">
                        <div class="about-system-card">
                            <span class="about-logo-mark">
                                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                            </span>
                            <h2>Report, locate, verify, and prevent.</h2>
                            <p>
                                The project responds to manual reporting challenges by pairing citizen observations
                                with structured evidence, coordinates, status tracking, and hotspot analytics.
                            </p>
                            <div class="about-pill-row">
                                <span class="about-pill"><i class="bi bi-person-check" aria-hidden="true"></i> Identified</span>
                                <span class="about-pill"><i class="bi bi-crosshair" aria-hidden="true"></i> Geospatial</span>
                                <span class="about-pill"><i class="bi bi-lock" aria-hidden="true"></i> Secure</span>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>

        <section class="about-section">
            <div class="container">
                <div class="row g-4 align-items-stretch">
                    <div class="col-lg-5">
                        <div class="about-highlight-dark about-highlight">
                            <h2 class="about-section-title">
                                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                                Why RSRS matters
                            </h2>
                            <p>
                                Many road safety reports are delayed, incomplete, or never submitted because people fear
                                identification, retaliation, or the slow process of manual reporting. Without accurate
                                GPS coordinates and evidence, response and prevention become harder.
                            </p>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="about-highlight h-100">
                            <div class="about-highlight-body">
                                <h2 class="about-section-title">
                                    <i class="bi bi-bullseye" aria-hidden="true"></i>
                                    Project objective
                                </h2>
                                <p class="about-section-copy">
                                    The main objective is to design a secure, confidential, web-based portal that allows
                                    registered drivers to report traffic violations in real time using geospatial
                                    technology, while giving authorized officers the tools to review and act on reports.
                                </p>
                                <ul class="about-list">
                                    <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Every driver report includes the registered driver ID.</li>
                                    <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Location details are captured with map support.</li>
                                    <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Officers can verify, resolve, reject, and analyze reports.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="about-section pt-0">
            <div class="container">
                <h2 class="about-section-title">
                    <i class="bi bi-compass" aria-hidden="true"></i>
                    What the system is built to do
                </h2>
                <p class="about-section-copy">
                    The system is focused on preventive road safety: collecting credible reports early, showing where
                    incidents happen, and helping officers identify repeated risk areas.
                </p>
                <div class="row g-3">
                    @foreach ($objectives as $objective)
                        <div class="col-md-6 col-xl-3">
                            <article class="about-card">
                                <span class="about-card-icon"><i class="bi {{ $objective['icon'] }}" aria-hidden="true"></i></span>
                                <h3>{{ $objective['title'] }}</h3>
                                <p>{{ $objective['text'] }}</p>
                            </article>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="about-section pt-0">
            <div class="container">
                <div class="row g-4 align-items-stretch">
                    <div class="col-lg-6">
                        <div class="about-highlight h-100">
                            <div class="about-highlight-body">
                                <h2 class="about-section-title">
                                    <i class="bi bi-ui-checks-grid" aria-hidden="true"></i>
                                    Core features
                                </h2>
                                <ul class="about-list">
                                    @foreach ($features as $feature)
                                        <li><i class="bi bi-check2-circle" aria-hidden="true"></i> {{ $feature }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="about-highlight h-100">
                            <div class="about-highlight-body">
                                <h2 class="about-section-title">
                                    <i class="bi bi-diagram-3" aria-hidden="true"></i>
                                    System architecture
                                </h2>
                                <div class="row g-3">
                                    @foreach ($architecture as $tier)
                                        <div class="col-12">
                                            <article class="about-card">
                                                <h4>{{ $tier['label'] }}</h4>
                                                <p>{{ $tier['text'] }}</p>
                                            </article>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="about-section pt-0">
            <div class="container">
                <h2 class="about-section-title">
                    <i class="bi bi-people" aria-hidden="true"></i>
                    Who benefits
                </h2>
                <p class="about-section-copy">
                    RSRS connects public observations with institutional response, while keeping reporter participation
                    simple and confidential.
                </p>
                <div class="row g-3">
                    @foreach ($stakeholders as $stakeholder)
                        <div class="col-md-4">
                            <article class="about-card">
                                <span class="about-card-icon"><i class="bi {{ $stakeholder['icon'] }}" aria-hidden="true"></i></span>
                                <h3>{{ $stakeholder['title'] }}</h3>
                                <p>{{ $stakeholder['text'] }}</p>
                            </article>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="about-section pt-0">
            <div class="container">
                <div class="about-highlight">
                    <div class="about-highlight-body">
                        <h2 class="about-section-title">
                            <i class="bi bi-tools" aria-hidden="true"></i>
                            Tools and technologies
                        </h2>
                        <p class="about-section-copy">
                            The project uses a familiar web stack for fast development, responsive access, secure data
                            handling, and map-based road safety workflows.
                        </p>
                        <div class="about-tech-row">
                            @foreach ($technologies as $technology)
                                <span class="about-tech">{{ $technology }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="container">
            <div class="about-cta">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-8">
                        <h2>Built for confidential reporting and informed action.</h2>
                        <p>
                            RSRS supports safer roads by turning real-time citizen reports into mapped, reviewable, and
                            actionable information for road safety officers.
                        </p>
                    </div>
                    <div class="col-lg-4">
                        <div class="about-actions justify-content-lg-end mt-0">
                            <a href="{{ route('contact') }}" class="about-btn about-btn-outline">
                                <i class="bi bi-envelope-paper" aria-hidden="true"></i>
                                Contact team
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
