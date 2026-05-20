{{-- Error page template rendered when this HTTP status is triggered. --}}

@extends('layouts.auth')

@section('title', '404 Not Found')

@section('content')
    @php
        $goBackUrl = url()->previous() !== url()->current() ? url()->previous() : route('home');
    @endphp

    @include('errors.partials.error-shell', [
        'code' => '404',
        'badgeText' => 'Page Missing',
        'badgeIcon' => 'bi-search',
        'title' => 'The page you are looking for could not be found.',
        'summary' => 'The link may be broken, outdated, or the page may have been moved to a different location.',
        'icon' => 'bi-file-earmark-x',
        'heading' => 'Page not found',
        'detail' => 'Please check the address you entered, or use one of the actions below to continue browsing safely.',
        'tips' => [
            'Check the URL for typing mistakes.',
            'Return to the homepage and navigate from the main menu.',
            'If someone shared this link with you, ask them to confirm it is still correct.',
        ],
        'actions' => [
            [
                'href' => $goBackUrl,
                'label' => 'Go Back',
                'icon' => 'bi-arrow-left',
                'variant' => 'primary',
            ],
            [
                'href' => route('developer'),
                'label' => 'Contact Developer',
                'icon' => 'bi-code-slash',
                'variant' => 'secondary',
            ],
        ],
    ])
@endsection
