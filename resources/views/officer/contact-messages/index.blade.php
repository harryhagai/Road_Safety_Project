{{-- Officer module view for index within the road safety dashboard. --}}

@extends('layouts.officerDashboardLayout')

@section('content')
    <div class="container-fluid px-2 px-lg-3 py-2">
        <section class="contact-messages-shell">
            <div class="contact-messages-shell__header">
                <div>
                    <h2 class="contact-messages-shell__title">Public enquiries</h2>
                    <p class="contact-messages-shell__subtitle">Track messages submitted from the client contact page.</p>
                </div>
                <div class="contact-messages-stats">
                    <div class="contact-message-stat">
                        <span class="contact-message-stat__label">Total</span>
                        <span class="contact-message-stat__value">{{ $messages->total() }}</span>
                    </div>
                    <div class="contact-message-stat">
                        <span class="contact-message-stat__label">New</span>
                        <span class="contact-message-stat__value">{{ $statusCounts['new'] ?? 0 }}</span>
                    </div>
                    <div class="contact-message-stat">
                        <span class="contact-message-stat__label">Resolved</span>
                        <span class="contact-message-stat__value">{{ $statusCounts['resolved'] ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('officer.contact-messages.index') }}" class="contact-message-filters">
                <div class="contact-message-search">
                    <i class="bi bi-search"></i>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search reference, name, email, or subject">
                </div>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn contact-message-filter-btn">
                    <i class="bi bi-funnel"></i>
                    <span>Filter</span>
                </button>
                @if (request()->hasAny(['search', 'status']))
                    <a href="{{ route('officer.contact-messages.index') }}" class="btn contact-message-clear-btn">
                        <i class="bi bi-x-circle"></i>
                        <span>Clear</span>
                    </a>
                @endif
            </form>

            <div class="contact-message-table-wrap">
                <table class="table contact-message-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Sender</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Assigned</th>
                            <th>Received</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($messages as $message)
                            <tr>
                                <td>
                                    <div class="contact-message-name">{{ $message->name }}</div>
                                    <div class="contact-message-meta">{{ $message->email }}</div>
                                    @if ($message->phone)
                                        <div class="contact-message-meta">{{ $message->phone }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="contact-message-subject">{{ $message->subject }}</div>
                                    <div class="contact-message-reference">{{ $message->reference_no }}</div>
                                </td>
                                <td>
                                    <span class="contact-message-status is-{{ str_replace('_', '-', $message->status) }}">
                                        <i class="bi bi-circle-fill"></i>
                                        <span>{{ $statuses[$message->status] ?? ucfirst($message->status) }}</span>
                                    </span>
                                </td>
                                <td>{{ $message->officer?->full_name ?? 'Unassigned' }}</td>
                                <td>{{ optional($message->created_at)->format('d M Y, H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('officer.contact-messages.show', $message) }}" class="btn contact-message-action-btn">
                                        <i class="bi bi-eye"></i>
                                        <span>Open</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="contact-message-empty">
                                        <i class="bi bi-inbox"></i>
                                        <span>No contact messages found.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($messages->hasPages())
                <div class="contact-message-pagination">
                    {{ $messages->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rsrsContactMessages.css') }}">
@endpush
