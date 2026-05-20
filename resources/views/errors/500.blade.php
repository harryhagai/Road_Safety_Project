{{-- Error page template rendered when this HTTP status is triggered. --}}

@extends('layouts.auth')

@section('title', '500 Server Error')

@section('content')
    @php
        $goBackUrl = url()->previous() !== url()->current() ? url()->previous() : route('home');
    @endphp

    @include('errors.partials.error-shell', [
        'code' => '500',
        'badgeText' => 'Server Issue',
        'badgeIcon' => 'bi-tools',
        'title' => 'Something went wrong on our side.',
        'summary' => 'The system hit an unexpected error while trying to complete your request.',
        'icon' => 'bi-exclamation-octagon',
        'heading' => 'Internal server error',
        'detail' => 'Please try again after a short moment. If the same problem keeps happening, report the issue with the page you were trying to open.',
        'tips' => [
            'Refresh the page or try the same action again after a few moments.',
            'Return to the homepage if you want to continue browsing safely.',
            'If the problem keeps repeating, share the page name and time of the error with support.',
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
