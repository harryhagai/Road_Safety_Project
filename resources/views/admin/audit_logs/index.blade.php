{{-- Admin module view for monitoring audit logs in RSRS. --}}

@extends('layouts.officerDashboardLayout')

@section('content')
    <div class="container-fluid px-3 px-lg-4 py-4">
        <section class="violation-shell">
            <div class="violation-shell__header">
                <div>
                    <h2>Audit Logs</h2>
                    <p>Monitor authentication, model changes, and sensitive system activity.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="row g-3 mb-4">
                <div class="col-md-5">
                    <label for="audit-search" class="form-label">Search</label>
                    <input id="audit-search" type="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Actor, subject, route, IP, description">
                </div>
                <div class="col-md-4">
                    <label for="audit-action" class="form-label">Action</label>
                    <select id="audit-action" name="action" class="form-select">
                        <option value="">All actions</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn geo-header-btn">
                        <i class="bi bi-search"></i>
                        <span>Filter</span>
                    </button>
                    <a href="{{ route('admin.audit-logs.index') }}" class="btn violation-action-btn">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Actor</th>
                            <th>Action</th>
                            <th>Subject</th>
                            <th>Route</th>
                            <th>IP</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="text-muted small">{{ $log->created_at?->format('M d, Y H:i') }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $log->actor_name ?: 'System' }}</div>
                                    <div class="text-muted small">{{ class_basename($log->actor_type ?: '') }} {{ $log->actor_id ? '#'.$log->actor_id : '' }}</div>
                                </td>
                                <td>
                                    <span class="badge text-bg-light border">{{ $log->action }}</span>
                                    @if ($log->description)
                                        <div class="text-muted small mt-1">{{ $log->description }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $log->subject_name ?: 'None' }}</div>
                                    <div class="text-muted small">{{ class_basename($log->subject_type ?: '') }} {{ $log->subject_id ? '#'.$log->subject_id : '' }}</div>
                                </td>
                                <td class="text-muted small">{{ $log->route_name ?: '-' }}</td>
                                <td class="text-muted small">{{ $log->ip_address ?: '-' }}</td>
                                <td>
                                    @if ($log->status_code)
                                        <span class="badge {{ $log->status_code >= 400 ? 'text-bg-danger' : 'text-bg-success' }}">{{ $log->status_code }}</span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-5 text-center text-muted">No audit logs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $logs->links() }}
            </div>
        </section>
    </div>
@endsection
