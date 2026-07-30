{{-- Admin dashboard view for RSRS system-level management. --}}

@extends('layouts.officerDashboardLayout')

@section('page_header_actions')
    <a href="{{ route('admin.users.index') }}" class="btn geo-header-btn">
        <i class="bi bi-people-fill"></i>
        <span>Manage Users</span>
    </a>
@endsection

@section('content')
    <div class="container-fluid px-2 px-lg-3 py-2">
        <section class="violation-shell admin-dashboard-shell">
            <div class="violation-shell__header">
                <div>
                    <h2 class="violation-shell__title">Admin overview</h2>
                    <p class="violation-shell__subtitle">Manage accounts and review the main system totals from one place.</p>
                </div>
            </div>

            <div class="admin-dashboard-grid">
                <article class="admin-dashboard-card">
                    <span>Total users</span>
                    <strong>{{ number_format($totalUsers) }}</strong>
                    <i class="bi bi-people"></i>
                </article>
                <article class="admin-dashboard-card">
                    <span>Active users</span>
                    <strong>{{ number_format($activeUsers) }}</strong>
                    <i class="bi bi-person-check"></i>
                </article>
                <article class="admin-dashboard-card">
                    <span>Admins</span>
                    <strong>{{ number_format($admins) }}</strong>
                    <i class="bi bi-shield-lock"></i>
                </article>
                <article class="admin-dashboard-card">
                    <span>Road officers</span>
                    <strong>{{ number_format($roadOfficers) }}</strong>
                    <i class="bi bi-person-badge"></i>
                </article>
                <article class="admin-dashboard-card">
                    <span>Drivers</span>
                    <strong>{{ number_format($drivers) }}</strong>
                    <i class="bi bi-bus-front"></i>
                </article>
                <article class="admin-dashboard-card">
                    <span>Passengers</span>
                    <strong>{{ number_format($passengers) }}</strong>
                    <i class="bi bi-person-walking"></i>
                </article>
                <article class="admin-dashboard-card">
                    <span>Reports</span>
                    <strong>{{ number_format($reports) }}</strong>
                    <i class="bi bi-clipboard2-pulse"></i>
                </article>
            </div>
        </section>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rsrsViolationTypes.css') }}">
    <style>
        .admin-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 1rem;
        }

        .admin-dashboard-card {
            position: relative;
            min-height: 126px;
            padding: 1rem;
            overflow: hidden;
            border: 1px solid rgba(35, 44, 58, 0.1);
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 14px 28px rgba(35, 44, 58, 0.08);
        }

        .admin-dashboard-card span {
            display: block;
            color: #667389;
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .admin-dashboard-card strong {
            display: block;
            margin-top: 0.5rem;
            color: #232c3a;
            font-size: 2rem;
            line-height: 1;
        }

        .admin-dashboard-card i {
            position: absolute;
            right: 1rem;
            bottom: 0.8rem;
            color: rgba(13, 111, 155, 0.16);
            font-size: 2.6rem;
        }
    </style>
@endpush
