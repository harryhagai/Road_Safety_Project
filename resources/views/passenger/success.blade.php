@extends('layouts.app')

@section('title', 'Report Submitted - RSRS')

@section('content')
    <div class="passenger-success container-xl px-3 py-5">
        <section>
            <i class="bi bi-check-circle-fill"></i>
            <span>Passenger report submitted</span>
            <h1>Thank you for helping improve road safety.</h1>
            <p>Your reference number is:</p>
            <strong>{{ $reference }}</strong>
            <a href="{{ route('home') }}" class="btn btn-dark"><i class="bi bi-house-door"></i> Return home</a>
        </section>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rsrsPassengerReport.css') }}?v={{ filemtime(public_path('css/rsrsPassengerReport.css')) }}">
@endpush
