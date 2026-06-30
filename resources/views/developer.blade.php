{{-- Blade view for the developer page in the RSRS application. --}}

@extends('layouts.app')

@section('title', 'Project Team')

@section('content')
    @php
        $studentDetails = [
            ['icon' => 'bi-person-vcard', 'label' => 'Registration Number', 'value' => 'NIT/BIT/2023/2185'],
            ['icon' => 'bi-mortarboard', 'label' => 'Program', 'value' => 'Bachelor of Information Technology (BIT)'],
            ['icon' => 'bi-layers', 'label' => 'Level', 'value' => 'Level 8'],
            ['icon' => 'bi-building', 'label' => 'Institution', 'value' => 'National Institute of Transport'],
        ];

        $studentFocus = [
            'Designing and developing the RSRS web portal',
            'Building identified driver road safety reporting workflows',
            'Integrating geospatial mapping and dashboard features',
        ];

        $mentorFocus = [
            'Project supervision and academic guidance',
            'Reviewing system direction, scope, and documentation',
            'Mentoring the student through proposal and implementation work',
        ];
    @endphp

    <style>
        .developer-section {
            --team-navy: #232c3a;
            --team-navy-dark: #1b2230;
            --team-gold: #f3b74a;
            --team-blue-soft: #eaf1ff;
            --team-muted: #6f7c90;
            --team-border: rgba(35, 44, 58, 0.1);
            --team-shadow: 0 22px 60px rgba(27, 35, 48, 0.12);
            position: relative;
            padding: clamp(2rem, 5vw, 4.5rem) 0 clamp(2.4rem, 5vw, 5rem);
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.95), rgba(243, 245, 249, 0.92) 38%, rgba(236, 239, 244, 1) 100%);
            color: #273244;
        }

        .developer-section > .container {
            position: relative;
            z-index: 1;
        }

        .developer-hero {
            max-width: 900px;
            margin: 0 auto 2rem;
            text-align: center;
        }

        .developer-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            margin-bottom: 1rem;
            padding: 0.45rem 0.78rem;
            border-radius: 999px;
            background: var(--team-blue-soft);
            color: var(--team-navy);
            font-size: 0.78rem;
            font-weight: 800;
        }

        .developer-kicker i,
        .developer-card-role i,
        .developer-focus-item i,
        .developer-summary-card h3 i {
            color: var(--team-gold);
        }

        .developer-title {
            margin: 0;
            color: var(--team-navy);
            font-size: clamp(2rem, 4vw, 3.6rem);
            line-height: 1.06;
            font-weight: 800;
            letter-spacing: 0;
        }

        .developer-intro {
            max-width: 780px;
            margin: 1rem auto 0;
            color: var(--team-muted);
            line-height: 1.78;
        }

        .developer-project-card {
            margin-bottom: 1.25rem;
            padding: clamp(1.4rem, 3vw, 2rem);
            border-radius: 22px;
            border: 1px solid rgba(26, 35, 51, 0.06);
            background: #ffffff;
            box-shadow: var(--team-shadow);
        }

        .developer-project-card h2 {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin: 0 0 0.65rem;
            color: var(--team-navy);
            font-size: clamp(1.25rem, 2.5vw, 1.8rem);
            font-weight: 800;
        }

        .developer-project-card h2 i {
            color: var(--team-gold);
        }

        .developer-project-card p {
            margin: 0;
            color: var(--team-muted);
            line-height: 1.72;
        }

        .developer-card {
            height: 100%;
            padding: clamp(1.35rem, 3vw, 2rem);
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid var(--team-border);
            box-shadow: 0 18px 38px rgba(27, 35, 48, 0.08);
        }

        .developer-card.is-mentor {
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.04), transparent 22%),
                linear-gradient(180deg, var(--team-navy) 0%, #1f2835 100%);
            color: #ffffff;
        }

        .developer-card-media {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .developer-card-avatar {
            width: 74px;
            height: 74px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border-radius: 22px;
            background: var(--team-blue-soft);
            color: var(--team-navy);
            font-size: 1.9rem;
        }

        .is-mentor .developer-card-avatar {
            background: rgba(243, 183, 74, 0.14);
            color: var(--team-gold);
        }

        .developer-card-name {
            margin: 0 0 0.45rem;
            color: var(--team-navy);
            font-size: clamp(1.35rem, 2vw, 1.8rem);
            font-weight: 800;
        }

        .is-mentor .developer-card-name {
            color: #ffffff;
        }

        .developer-card-role {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.42rem 0.72rem;
            border-radius: 999px;
            background: var(--team-blue-soft);
            color: var(--team-navy);
            font-size: 0.82rem;
            font-weight: 700;
        }

        .is-mentor .developer-card-role {
            border: 1px solid rgba(255, 194, 92, 0.18);
            background: rgba(255, 255, 255, 0.08);
            color: #f8fafc;
        }

        .developer-card-copy {
            margin: 0 0 1.15rem;
            color: var(--team-muted);
            line-height: 1.72;
        }

        .is-mentor .developer-card-copy,
        .is-mentor .developer-focus-item {
            color: rgba(237, 242, 247, 0.9);
        }

        .developer-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
            margin-bottom: 1.2rem;
        }

        .developer-detail {
            padding: 0.85rem;
            border-radius: 16px;
            background: var(--team-blue-soft);
            border: 1px solid rgba(35, 44, 58, 0.06);
        }

        .developer-detail span {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            color: var(--team-muted);
            font-size: 0.78rem;
            font-weight: 700;
        }

        .developer-detail span i {
            color: var(--team-gold);
        }

        .developer-detail strong {
            display: block;
            margin-top: 0.3rem;
            color: var(--team-navy);
            font-size: 0.9rem;
            line-height: 1.45;
        }

        .developer-focus-list {
            display: grid;
            gap: 0.65rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .developer-focus-item {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            color: var(--team-muted);
            line-height: 1.65;
        }

        .developer-focus-item i {
            margin-top: 0.15rem;
        }

        .developer-summary-grid {
            margin-top: 1.25rem;
        }

        .developer-summary-card {
            height: 100%;
            padding: 1.25rem;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid var(--team-border);
            box-shadow: 0 14px 34px rgba(27, 35, 48, 0.06);
        }

        .developer-summary-card h3 {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            margin: 0 0 0.65rem;
            color: var(--team-navy);
            font-size: 1.05rem;
            font-weight: 800;
        }

        .developer-summary-card p {
            margin: 0;
            color: var(--team-muted);
            line-height: 1.7;
        }

        @media (max-width: 767.98px) {
            .developer-card-media {
                align-items: flex-start;
            }

            .developer-detail-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 575.98px) {
            .developer-project-card,
            .developer-card {
                border-radius: 18px;
            }

            .developer-card-media {
                gap: 0.8rem;
            }

            .developer-card-avatar {
                width: 62px;
                height: 62px;
                border-radius: 18px;
                font-size: 1.55rem;
            }
        }
    </style>

    <section class="developer-section" id="developer-team">
        <div class="container">
            <div class="developer-hero">
                <span class="developer-kicker">
                    <i class="bi bi-people-fill" aria-hidden="true"></i>
                    Project Team
                </span>
                <h1 class="developer-title">Student and Mentor</h1>
                <p class="developer-intro">
                    This page presents the student behind the RSRS project and the academic mentor supervising the work
                    documented in the project proposal.
                </p>
            </div>

            <div class="developer-project-card">
                <h2>
                    <i class="bi bi-journal-code" aria-hidden="true"></i>
                    Development of Confidential Web Portal for Road Safety Reporting with Geospatial Mapping
                </h2>
                <p>
                    RSRS is a software project for identified driver road safety reporting, map-based location capture, and
                    secure officer review tools for transport authorities and road officers.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <article class="developer-card">
                        <header class="developer-card-media">
                            <span class="developer-card-avatar">
                                <i class="bi bi-person-fill-gear" aria-hidden="true"></i>
                            </span>
                            <div>
                                <h2 class="developer-card-name">Hagai Harold Ngobey</h2>
                                <div class="developer-card-role">
                                    <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
                                    <span>Project Student</span>
                                </div>
                            </div>
                        </header>

                        <p class="developer-card-copy">
                            Hagai Harold Ngobey is the student developing the Road Safety Reporting System as a BIT
                            software project at the National Institute of Transport.
                        </p>

                        <div class="developer-detail-grid">
                            @foreach ($studentDetails as $detail)
                                <div class="developer-detail">
                                    <span><i class="bi {{ $detail['icon'] }}" aria-hidden="true"></i> {{ $detail['label'] }}</span>
                                    <strong>{{ $detail['value'] }}</strong>
                                </div>
                            @endforeach
                        </div>

                        <ul class="developer-focus-list">
                            @foreach ($studentFocus as $focus)
                                <li class="developer-focus-item">
                                    <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                    <span>{{ $focus }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </article>
                </div>

                <div class="col-lg-5">
                    <article class="developer-card is-mentor">
                        <header class="developer-card-media">
                            <span class="developer-card-avatar">
                                <i class="bi bi-person-workspace" aria-hidden="true"></i>
                            </span>
                            <div>
                                <h2 class="developer-card-name">Mr. Rodrick Mero</h2>
                                <div class="developer-card-role">
                                    <i class="bi bi-compass-fill" aria-hidden="true"></i>
                                    <span>Project Mentor / Supervisor</span>
                                </div>
                            </div>
                        </header>

                        <p class="developer-card-copy">
                            Mr. Rodrick Mero is the supervisor and mentor named in the project proposal, guiding the
                            student through the road safety reporting portal project.
                        </p>

                        <ul class="developer-focus-list">
                            @foreach ($mentorFocus as $focus)
                                <li class="developer-focus-item">
                                    <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                    <span>{{ $focus }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </article>
                </div>
            </div>

            <div class="row g-4 developer-summary-grid">
                <div class="col-lg-6">
                    <div class="developer-summary-card">
                        <h3>
                            <i class="bi bi-bullseye" aria-hidden="true"></i>
                            <span>Project Aim</span>
                        </h3>
                        <p>
                            To design a secure web portal that helps registered drivers report traffic violations in
                            real time with driver and vehicle identification.
                        </p>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="developer-summary-card">
                        <h3>
                            <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                            <span>Geospatial Focus</span>
                        </h3>
                        <p>
                            The system uses mapping and coordinates to improve incident location accuracy, report
                            verification, road rules, and hotspot analysis.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
