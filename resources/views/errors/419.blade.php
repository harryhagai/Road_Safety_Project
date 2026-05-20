{{-- Error page template rendered when this HTTP status is triggered. --}}

@extends('layouts.auth')

@section('title', '419 Page Expired')

@section('content')
    @php
        $goBackUrl = url()->previous() !== url()->current() ? url()->previous() : route('home');
    @endphp

    @include('errors.partials.error-shell', [
        'code' => '419',
        'badgeText' => 'Session Expired',
        'badgeIcon' => 'bi-clock-history',
        'title' => 'Your session expired before the request was completed.',
        'summary' => 'This usually happens when a form stays open for too long, or the browser refresh token is no longer valid.',
        'icon' => 'bi-hourglass-split',
        'heading' => 'Page expired',
        'detail' => 'Refresh or reopen the page, then try the action again. If you were submitting a form, you may need to sign in once more before continuing.',
        'tips' => [
            'Go back and reopen the form or page you were using.',
            'If needed, log in again and repeat your action.',
            'Avoid leaving form pages open for too long before submitting them.',
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
