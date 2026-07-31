{{-- Officer module view for index within the road safety dashboard. --}}

@extends('layouts.officerDashboardLayout')

@section('content')
    <div class="container-fluid px-3 px-lg-4 py-4">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-0">
                @forelse ($notifications as $notification)
                    <a
                        href="{{ route('officer.notifications.show', $notification->id) }}"
                        class="d-flex align-items-start justify-content-between gap-3 px-4 py-3 border-bottom text-decoration-none {{ $notification->status === 'unread' ? 'bg-light' : '' }}"
                    >
                        <div class="min-w-0">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge rounded-pill {{ $notification->status === 'unread' ? 'text-bg-primary' : 'text-bg-secondary' }}">
                                    {{ ucfirst($notification->status) }}
                                </span>
                                <span class="text-muted small">{{ $notification->created_at?->diffForHumans() }}</span>
                            </div>
                            <h2 class="h6 mb-1 text-dark">{{ $notification->title }}</h2>
                            <p class="mb-0 text-muted">{{ $notification->message }}</p>
                        </div>
                        <i class="bi bi-arrow-right-short fs-4 text-muted" aria-hidden="true"></i>
                    </a>
                @empty
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-bell fs-3 d-block mb-2"></i>
                        No notifications yet.
                    </div>
                @endforelse
            </div>

            @if (method_exists($notifications, 'links'))
                <div class="card-footer bg-white border-0 px-4 py-3">
                    {{ $notifications->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
