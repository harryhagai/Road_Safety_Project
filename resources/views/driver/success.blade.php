@extends('layouts.app')

@section('title', 'Driver Report Submitted - RSRS')

@section('content')
    <div class="passenger-success driver-success container-xl px-3 py-5">
        <section>
            <i class="bi bi-check-circle-fill"></i>
            <span>{{ $duplicate ? 'Driver report already submitted' : 'Driver report submitted' }}</span>
            <h1>{{ $duplicate ? 'This violation was already sent to road officers.' : 'The violation has been sent to road officers.' }}</h1>
            <p>Your reference number is:</p>
            <strong>{{ $reference }}</strong>
            <a href="{{ route('home') }}" class="btn btn-dark success-home-link" data-success-home-link>
                <span class="spinner-border spinner-border-sm success-home-spinner" aria-hidden="true"></span>
                <i class="bi bi-house-door success-home-icon"></i>
                <span data-success-home-label>Return home</span>
            </a>
        </section>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rsrsPassengerReport.css') }}?v={{ filemtime(public_path('css/rsrsPassengerReport.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/rsrsDriverReport.css') }}?v={{ filemtime(public_path('css/rsrsDriverReport.css')) }}">
@endpush

@push('scripts')
    <script>
        const homeLink = document.querySelector('[data-success-home-link]');
        const homeLinkLabel = homeLink?.querySelector('[data-success-home-label]');

        if (homeLink) {
            homeLink.classList.add('is-loading');
            homeLink.setAttribute('aria-busy', 'true');
        }

        if (homeLinkLabel) {
            homeLinkLabel.textContent = 'Returning home...';
        }

        window.setTimeout(() => {
            window.location.assign(@json(route('home')));
        }, 3000);
    </script>
@endpush
