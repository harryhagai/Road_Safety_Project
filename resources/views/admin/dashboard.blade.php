{{-- Admin dashboard view for RSRS system-level management. --}}

@extends('layouts.officerDashboardLayout')

@section('page_header_actions')
    <a href="{{ route('admin.users.index') }}" class="btn admin-quick-access-btn">
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

        <section class="admin-audit-panel mt-3">
            <div class="admin-audit-panel__header">
                <div>
                    <span>System activity</span>
                    <h3>Audit logs overview</h3>
                </div>
                <a href="{{ route('admin.audit-logs.index') }}" class="admin-audit-panel__link">
                    <i class="bi bi-activity" aria-hidden="true"></i>
                    <span>View logs</span>
                </a>
            </div>

            <div class="admin-audit-layout">
                <div class="admin-audit-chart-wrap">
                    @if ($auditTrend->sum('value') > 0)
                        <canvas id="adminAuditTrendChart"></canvas>
                    @else
                        <div class="admin-audit-empty">
                            <i class="bi bi-activity" aria-hidden="true"></i>
                            <span>No audit activity yet.</span>
                        </div>
                    @endif
                </div>

                <div class="admin-audit-side">
                    <div class="admin-audit-side__block">
                        <h4>Latest events</h4>
                        @forelse ($recentAuditLogs as $log)
                            <div class="admin-audit-event">
                                <strong>{{ str($log->action ?: 'activity')->replace(['_', '-'], ' ')->title() }}</strong>
                                <span>{{ $log->actor_name ?: 'System' }} | {{ optional($log->created_at)->format('d M, H:i') }}</span>
                            </div>
                        @empty
                            <p>No recent audit logs.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rsrsViolationTypes.css') }}">
    <style>
        .admin-quick-access-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 40px;
            padding: 0.55rem 0.9rem;
            border: 1px solid #232c3a;
            border-radius: 8px;
            background: #232c3a;
            color: #ffffff;
            font-size: 0.9rem;
            font-weight: 500;
            line-height: 1;
            text-decoration: none;
            box-shadow: none;
        }

        .admin-quick-access-btn:hover,
        .admin-quick-access-btn:focus {
            border-color: #1b2230;
            background: #1b2230;
            color: #ffffff;
        }

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
            font-weight: 500;
            text-transform: uppercase;
        }

        .admin-dashboard-card strong {
            display: block;
            margin-top: 0.5rem;
            color: #232c3a;
            font-size: 2rem;
            font-weight: 500;
            line-height: 1;
        }

        .admin-dashboard-card i {
            position: absolute;
            right: 1rem;
            bottom: 0.8rem;
            color: rgba(13, 111, 155, 0.16);
            font-size: 2.6rem;
        }

        .admin-audit-panel {
            overflow: hidden;
            border: 1px solid rgba(35, 44, 58, 0.1);
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 14px 28px rgba(35, 44, 58, 0.08);
        }

        .admin-audit-panel__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.95rem 1rem;
            border-bottom: 1px solid rgba(35, 44, 58, 0.1);
            background: linear-gradient(180deg, rgba(13, 111, 155, 0.05), #ffffff);
        }

        .admin-audit-panel__header span {
            display: block;
            color: #667389;
            font-size: 0.72rem;
            font-weight: 500;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .admin-audit-panel__header h3 {
            margin: 0.15rem 0 0;
            color: #232c3a;
            font-size: 1rem;
            font-weight: 500;
        }

        .admin-audit-panel__link {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.5rem 0.75rem;
            border: 1px solid rgba(35, 44, 58, 0.18);
            border-radius: 999px;
            color: var(--theme-navy, #232c3a);
            font-size: 0.84rem;
            font-weight: 500;
            text-decoration: none;
            white-space: nowrap;
        }

        .admin-audit-panel__link:hover,
        .admin-audit-panel__link:focus {
            border-color: var(--theme-navy, #232c3a);
            background: var(--theme-navy, #232c3a);
            color: #ffffff;
        }

        .admin-audit-panel__link:hover span,
        .admin-audit-panel__link:focus span,
        .admin-audit-panel__link:hover i,
        .admin-audit-panel__link:focus i {
            color: #ffffff;
        }

        .admin-audit-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 1rem;
            padding: 1rem;
        }

        .admin-audit-chart-wrap {
            height: 270px;
            min-width: 0;
            padding: 0.75rem;
            border: 1px solid rgba(35, 44, 58, 0.08);
            border-radius: 8px;
            background: #f9fbff;
        }

        .admin-audit-side {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
            min-width: 0;
        }

        .admin-audit-side__block {
            min-width: 0;
            padding: 0.75rem;
            border: 1px solid rgba(35, 44, 58, 0.08);
            border-radius: 8px;
            background: #ffffff;
        }

        .admin-audit-side__block h4 {
            margin: 0 0 0.55rem;
            color: #232c3a;
            font-size: 0.88rem;
            font-weight: 500;
        }

        .admin-audit-side__block p {
            margin: 0;
            color: #667389;
            font-size: 0.84rem;
        }

        .admin-audit-event {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.8rem;
            padding: 0.42rem 0;
            border-bottom: 1px solid rgba(35, 44, 58, 0.08);
        }

        .admin-audit-event:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .admin-audit-event span {
            min-width: 0;
            overflow: hidden;
            color: #667389;
            font-size: 0.82rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .admin-audit-event strong {
            color: #232c3a;
            font-size: 0.84rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .admin-audit-event {
            align-items: flex-start;
            flex-direction: column;
            gap: 0.1rem;
        }

        .admin-audit-empty {
            height: 100%;
            display: grid;
            place-items: center;
            gap: 0.5rem;
            color: #667389;
            text-align: center;
        }

        .admin-audit-empty i {
            color: rgba(13, 111, 155, 0.5);
            font-size: 1.8rem;
        }

        @media (max-width: 1199.98px) {
            .admin-audit-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .admin-audit-panel__header {
                align-items: flex-start;
                flex-direction: column;
            }

            .admin-audit-side {
                grid-template-columns: 1fr;
            }

            .admin-audit-chart-wrap {
                height: 240px;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        window.rsrsAdminAuditDashboard = {
            trend: @json($auditTrend),
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        (() => {
            const canvas = document.getElementById('adminAuditTrendChart');
            const payload = window.rsrsAdminAuditDashboard || {};

            if (!canvas || !window.Chart) {
                return;
            }

            const trend = Array.isArray(payload.trend) ? payload.trend : [];

            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: trend.map((item) => item.label),
                    datasets: [{
                        label: 'Audit Logs',
                        data: trend.map((item) => Number(item.value || 0)),
                        borderColor: '#0d6f9b',
                        backgroundColor: 'rgba(13, 111, 155, 0.12)',
                        fill: true,
                        tension: 0.32,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            title: { display: true, text: 'Events' },
                        },
                        x: {
                            title: { display: true, text: 'Date' },
                        },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => `${context.parsed.y} audit event${context.parsed.y === 1 ? '' : 's'}`,
                            },
                        },
                    },
                },
            });
        })();
    </script>
@endpush
