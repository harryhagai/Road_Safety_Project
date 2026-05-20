{{-- Error page template rendered when this HTTP status is triggered. --}}

@extends('layouts.auth')

@section('title', '403 Forbidden')

@section('content')
    @php
        $goBackUrl = url()->previous() !== url()->current() ? url()->previous() : route('home');
    @endphp

    @include('errors.partials.error-shell', [
        'code' => '403',
        'badgeText' => 'Access Restricted',
        'badgeIcon' => 'bi-shield-lock',
        'title' => 'You do not have permission to open this page.',
        'summary' => 'The page exists, but your current account or session is not allowed to access it.',
        'icon' => 'bi-person-lock',
        'heading' => 'Access denied',
        'detail' => 'If you expected to see this page, sign in with the correct account or return to a page that matches your role and permissions.',
        'tips' => [
            'Return to the previous page and confirm you opened the correct link.',
            'Make sure you are signed in with the correct school account.',
            'If this page should be available to you, contact the school administrator or system support.',
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
