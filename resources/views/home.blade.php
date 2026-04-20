@inject('mapConfigService', 'App\Services\MapConfigService')
@extends('layouts.app')

@section('title', 'RSRS - Road Safety Reporting System')

@push('critical-head')
    <style>
        body.home-loader-active {
            overflow: hidden;
            background: linear-gradient(160deg, #f4faff, #e0eeff);
        }

        .home-page-loader {
            position: fixed;
            inset: 0;
            z-index: 2500;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            overflow: hidden;
            background: linear-gradient(160deg, rgba(244, 250, 255, 0.94), rgba(224, 238, 255, 0.92));
            backdrop-filter: blur(14px);
            transition: opacity 280ms ease, visibility 280ms ease;
        }

        .home-page-loader.is-hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .home-page-loader__panel {
            position: relative;
            z-index: 1;
            display: grid;
            justify-items: center;
            gap: 1.15rem;
            width: min(100%, 460px);
            padding: 2rem 1.6rem;
            border: 1px solid rgba(31, 79, 167, 0.12);
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.72);
            box-shadow: 0 30px 80px rgba(31, 79, 167, 0.18);
        }

        .home-page-loader__brand {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 74px;
            padding: 0.45rem 0.85rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.84);
            border: 1px solid rgba(31, 112, 255, 0.15);
            color: #1557c2;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.22em;
            text-indent: 0.22em;
        }

        .home-page-loader__visual {
            position: relative;
            width: 140px;
            height: 140px;
            display: grid;
            place-items: center;
        }

        .home-page-loader__ring,
        .home-page-loader__core {
            position: absolute;
            border-radius: 50%;
        }

        .home-page-loader__ring--outer {
            inset: 0;
            border: 2px solid rgba(31, 112, 255, 0.16);
            animation: homePageLoaderSpin 3.8s linear infinite;
        }

        .home-page-loader__ring--middle {
            inset: 16px;
            border: 3px solid transparent;
            border-top-color: #1f70ff;
            border-right-color: rgba(94, 196, 238, 0.95);
            animation: homePageLoaderSpin 1.25s linear infinite;
        }

        .home-page-loader__ring--inner {
            inset: 34px;
            border: 2px dashed rgba(21, 87, 194, 0.38);
            animation: homePageLoaderSpinReverse 2s linear infinite;
        }

        .home-page-loader__core {
            inset: 49px;
            display: block;
            background: radial-gradient(circle at 30% 30%, #ffffff, #8ed7f5 54%, #1f70ff 100%);
            box-shadow: 0 0 0 10px rgba(31, 112, 255, 0.08), 0 18px 28px rgba(31, 79, 167, 0.2);
            animation: homeLoaderPulse 1.5s ease-in-out infinite;
        }

        .home-page-loader__content {
            display: grid;
            gap: 0.45rem;
            color: #173153;
            text-align: center;
            max-width: 30rem;
        }

        .home-page-loader__eyebrow {
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #1557c2;
        }

        .home-page-loader__content strong {
            font-size: clamp(1.25rem, 2.8vw, 2rem);
            line-height: 1.15;
        }

        .home-page-loader__content span:last-child {
            color: #58708f;
            font-size: 0.98rem;
            line-height: 1.5;
        }

        @keyframes homePageLoaderSpin {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes homePageLoaderSpinReverse {
            to {
                transform: rotate(-360deg);
            }
        }

        @keyframes homeLoaderPulse {
            0%,
            100% {
                transform: scale(0.96);
            }
            50% {
                transform: scale(1.04);
            }
        }
    </style>
@endpush

@push('page_loader')
    <div class="home-page-loader" id="homePageLoader" data-home-map-loader role="status" aria-live="polite">
        <div class="home-page-loader__panel">
            <div class="home-page-loader__brand">RSRS</div>
            <div class="home-page-loader__visual" aria-hidden="true">
                <span class="home-page-loader__ring home-page-loader__ring--outer"></span>
                <span class="home-page-loader__ring home-page-loader__ring--middle"></span>
                <span class="home-page-loader__ring home-page-loader__ring--inner"></span>
                <span class="home-page-loader__core"></span>
            </div>
            <div class="home-page-loader__content">
                <span class="home-page-loader__eyebrow">Road Safety Reporting System</span>
                <strong>Loading the live map...</strong>
                <span>Preparing location, layers, and your first view.</span>
            </div>
        </div>
    </div>
@endpush

@section('content')
    @php
        $mapConfig = $mapConfigService->forFrontend();
    @endphp

    <div class="container-fluid container-xl geo-workspace px-2 px-md-3 py-2 py-md-3 home-geo-workspace">
        <div class="row g-2 g-md-3 geo-workspace__grid">
            <div class="col-12">
                <section class="geo-card geo-card--fill geo-card--map home-geo-card">
                    <div class="home-map-stage">
                        <x-map.canvas id="mainPublicMap" :config="$mapConfig" height="100%" :show-toolbar="false" mode="viewer" />
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="{{ asset('css/rsrsMap.css') }}">
    <link rel="stylesheet" href="{{ asset('css/rsrsHomeMap.css') }}?v={{ filemtime(public_path('css/rsrsHomeMap.css')) }}">
@endpush

@section('scripts')
    <script src="{{ asset('js/rsrsHomeLoader.js') }}?v={{ filemtime(public_path('js/rsrsHomeLoader.js')) }}"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="{{ asset('js/rsrsMapPicker.js') }}?v={{ filemtime(public_path('js/rsrsMapPicker.js')) }}"></script>
    <script src="{{ asset('js/rsrsHomeMap.js') }}?v={{ filemtime(public_path('js/rsrsHomeMap.js')) }}"></script>
@endsection
