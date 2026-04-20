<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title', 'Road Safety Reporting System')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/road-safety-favicon.svg') }}">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&family=Roboto:wght@400;500;700;900&display=swap"
        rel="stylesheet">


    <!-- Custom CSS -->
    <link href="{{ asset('css/rsrsPublicHeader.css') }}" rel="stylesheet">
    <link href="{{ asset('css/rsrsPublicFooter.css') }}" rel="stylesheet">
    <link href="{{ asset('css/rsrsHome.css') }}" rel="stylesheet">
    <link href="{{ asset('css/rsrsAuth.css') }}" rel="stylesheet">
    @stack('critical-head')
    @stack('styles')
    <style>
        :root {
            --font-heading: "Segoe UI", "Trebuchet MS", Verdana, sans-serif;
            --font-body: "Segoe UI", "Trebuchet MS", Verdana, sans-serif;
        }

        body {
            font-family: var(--font-body);
        }

        h1,h2,h3,h4,h5, h6,
        .section-title,
        .hero-title,
        .header-name {
            font-family: var(--font-heading);
        }

        .btn {
            font-family: var(--font-heading);
        }

        p, li,a, span, input, select,textarea, label,
        small {
            font-family: var(--font-body);
        }
    </style>

    <style>
        body.has-fixed-footer {
            padding-bottom: 56px; /* Space for the footer */
        }
        @media (max-width: 768px) {
            body.has-fixed-footer {
                padding-bottom: 80px; /* Taller footer on mobile */
            }
        }
    </style>
</head>

<body class="has-fixed-footer d-flex flex-column min-vh-100" data-disable-navigation-overlay="1" data-inline-spinner-links="1" data-inline-spinner-theme="blue">
    @stack('page_loader')
    @include('components.header')

    <main class="flex-grow-1">
        @yield('content')
    </main>

    @include('components.footer')

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JS -->
    <script src="{{ asset('js/rsrsPublicHeader.js') }}"></script>
    <script src="{{ asset('js/rsrsButtonSpinner.js') }}"></script>
    @yield('scripts')
    @stack('scripts')

</body>

</html>
