<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Road Officer Dashboard | Road Safety Reporting System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/road-safety-favicon.svg') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/aHeader.css') }}" rel="stylesheet">
    <link href="{{ asset('css/aSidebar.css') }}" rel="stylesheet">
    <link href="{{ asset('css/aDashboardLayout.css') }}" rel="stylesheet">
    @stack('styles')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body data-disable-navigation-overlay="1">
    @php
        $academicPageHeader = match (true) {
            request()->is('road-officer/dashboard') || request()->is('academic/dashboard') => [
                'title' => 'Road Officer Dashboard',
                'subtitle' => 'Monitor reports, violations, rules, and hotspot activity from one workspace.',
            ],
            request()->is('road-officer/reports*') || request()->is('academic/results-overview') => [
                'title' => 'Incident Reports',
                'subtitle' => 'Review submitted cases, progress updates, and field reporting activity.',
            ],
            request()->is('road-officer/road-rules*') || request()->is('academic/examinations*') => [
                'title' => 'Road Rules',
                'subtitle' => 'Manage active road rules, enforcement details, and effective periods.',
            ],
            request()->is('road-officer/road-segments*') || request()->is('academic/classes*') => [
                'title' => 'Road Segments',
                'subtitle' => 'Maintain mapped road segments, boundary details, and segment descriptions.',
            ],
            request()->is('road-officer/violation-types*') || request()->is('academic/olevel-subject-management*') || request()->is('academic/alevel-subjects*') => [
                'title' => 'Violation Types',
                'subtitle' => 'Manage report categories used for road incidents and traffic violations.',
            ],
            request()->is('road-officer/evidence-files*') || request()->is('student-reports/class*') => [
                'title' => 'Evidence Files',
                'subtitle' => 'Browse attachments, media records, and supporting files linked to reports.',
            ],
            request()->is('road-officer/rule-violations*') || request()->is('student-reports/single-exam*') => [
                'title' => 'Rule Violations',
                'subtitle' => 'Match reports to rules, review verification status, and confirm enforcement actions.',
            ],
            request()->is('road-officer/hotspots*') || request()->is('academic/consolidated/results*') => [
                'title' => 'Hotspots',
                'subtitle' => 'Track dangerous areas, severity trends, and recurring incident locations.',
            ],
            request()->is('road-officer/officers*') || request()->is('academic/teachers*') => [
                'title' => 'Officers',
                'subtitle' => 'Manage officer accounts, roles, and operational access across the system.',
            ],
            request()->is('road-officer/notifications*') || request()->is('academic/notifications*') => [
                'title' => 'Notifications',
                'subtitle' => 'Review alerts, assignment updates, and workflow messages for road officers.',
            ],
            request()->is('road-officer/settings*') || request()->is('academic/settings*') => [
                'title' => 'Officer Settings',
                'subtitle' => 'Adjust road safety workflow preferences, alert behavior, and dashboard settings.',
            ],
            request()->is('road-officer/profile*') || request()->is('academic/profile*') => [
                'title' => 'Officer Profile',
                'subtitle' => 'Manage your account information and profile details.',
            ],
            default => [
                'title' => 'Road Officer Workspace',
                'subtitle' => 'Manage road safety reporting operations from one place.',
            ],
        };
    @endphp

    @include('components.academicHeader')
    @include('components.officerSidebar')

    <main class="officer-page-content" @if($academicPageHeader) data-officer-page-header="true" @endif>
        @if ($academicPageHeader)
            <div class="px-3 px-lg-4 pt-4">
                <x-officer-page-header :title="$academicPageHeader['title']" :subtitle="$academicPageHeader['subtitle']" />
            </div>
        @endif

        <div class="officer-page-body">
            @yield('content')
        </div>
    </main>

    <style>
        .officer-shared-page-header {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 1rem;
            padding: 0.25rem 0 1.1rem;
            margin-bottom: 1.2rem;
            border-bottom: 1px solid rgba(35, 44, 58, 0.14);
        }
        .officer-shared-page-header__title {
            margin: 0;
            font-size: clamp(1.35rem, 1.5vw, 1.8rem);
            line-height: 1.2;
            font-weight: 700;
            color: #232c3a;
        }
        .officer-shared-page-header__subtitle {
            max-width: 720px;
            margin: 0.35rem 0 0;
            color: #4f5a6b;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .officer-page-content[data-officer-page-header="true"] .officer-page-body > .container:first-child > :is(h1, h2, h3, h4):first-child,
        .officer-page-content[data-officer-page-header="true"] .officer-page-body > .container-fluid:first-child > :is(h1, h2, h3, h4):first-child {
            display: none !important;
        }
        .officer-page-content[data-officer-page-header="true"] .officer-page-body > .container:first-child > .d-flex:first-child > :is(h1, h2, h3, h4):first-child,
        .officer-page-content[data-officer-page-header="true"] .officer-page-body > .container-fluid:first-child > .d-flex:first-child > :is(h1, h2, h3, h4):first-child {
            display: none !important;
        }
        @media (max-width: 768px) {
            .officer-shared-page-header {
                padding-bottom: 1rem;
            }
            .officer-shared-page-header__subtitle {
                font-size: 0.92rem;
            }
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/officerSidebarToggler.js') }}"></script>
    <script src="{{ asset('js/buttonSpinner.js') }}"></script>
    <script src="{{ asset('js/academicAlertTheme.js') }}"></script>
    @yield('scripts')

    @if (session('success') && ! View::hasSection('disable_success_swal'))
        <script>
            showAcademicUiAlert({
                theme: 'success',
                title: 'Action completed',
                text: @js(session('success')),
                timer: 2600,
                showConfirmButton: false
            });
        </script>
    @endif

    @if (session('error'))
        <script>
            showAcademicUiAlert({
                theme: 'danger',
                title: 'Something went wrong',
                text: @js(session('error')),
                showConfirmButton: true,
                confirmButtonText: '<i class="bi bi-arrow-repeat me-1"></i> OK'
            });
        </script>
    @endif

    @stack('scripts')
    <script src="/teacherClassAssignment.js"></script>
</body>
</html>
