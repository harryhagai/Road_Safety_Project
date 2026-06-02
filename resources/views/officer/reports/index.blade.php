{{-- Officer module view for index within the road safety dashboard. --}}

@extends('layouts.officerDashboardLayout')

@php
    $statusLabel = fn (?string $status) => str($status ?: 'unknown')->replace('_', ' ')->title();
@endphp

@section('page_header_actions')
    <a href="{{ route('officer.dashboard') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
        <i class="bi bi-speedometer2" aria-hidden="true"></i>
        <span>Dashboard</span>
    </a>
@endsection

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4 officer-reports-page">
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <article class="report-stat">
                <i class="bi bi-clipboard-data report-stat__watermark" aria-hidden="true"></i>
                <span>Total reports</span>
                <strong>{{ number_format($summary['total']) }}</strong>
            </article>
        </div>
        <div class="col-6 col-xl-3">
            <article class="report-stat">
                <i class="bi bi-cpu report-stat__watermark" aria-hidden="true"></i>
                <span>Automatic</span>
                <strong>{{ number_format($summary['automatic']) }}</strong>
            </article>
        </div>
        <div class="col-6 col-xl-3">
            <article class="report-stat">
                <i class="bi bi-send-check report-stat__watermark" aria-hidden="true"></i>
                <span>Submitted</span>
                <strong>{{ number_format($summary['submitted']) }}</strong>
            </article>
        </div>
        <div class="col-6 col-xl-3">
            <article class="report-stat">
                <i class="bi bi-shield-check report-stat__watermark" aria-hidden="true"></i>
                <span>Verified</span>
                <strong>{{ number_format($summary['verified']) }}</strong>
            </article>
        </div>
    </div>

    <section class="report-panel mb-4">
        <form method="GET" action="{{ route('officer.reports.index') }}" class="row g-3 align-items-end">
            <div class="col-12 col-lg-4">
                <label class="form-label" for="search">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Reference, segment, location">
                </div>
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $statusLabel($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label" for="violation_type_id">Violation</label>
                <select class="form-select" id="violation_type_id" name="violation_type_id">
                    <option value="">All types</option>
                    @foreach ($violationTypes as $type)
                        <option value="{{ $type->id }}" @selected((string) ($filters['violation_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label" for="source">Source</label>
                <select class="form-select" id="source" name="source">
                    <option value="">All sources</option>
                    <option value="automatic" @selected(($filters['source'] ?? '') === 'automatic')>Automatic</option>
                    <option value="manual" @selected(($filters['source'] ?? '') === 'manual')>Manual</option>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill d-inline-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-funnel" aria-hidden="true"></i>
                    <span>Filter</span>
                </button>
                <a href="{{ route('officer.reports.index') }}" class="btn btn-outline-secondary" title="Clear filters" aria-label="Clear filters">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </a>
            </div>
        </form>
    </section>

    <section class="report-panel">
        <div class="report-panel__header">
            <div>
                <span class="report-panel__eyebrow">Officer Review</span>
                <h3>Submitted reports</h3>
            </div>
            <span class="report-count">{{ number_format($reports->total()) }} result{{ $reports->total() === 1 ? '' : 's' }}</span>
        </div>

        <div class="table-responsive">
            <table class="table report-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Violation</th>
                        <th>Segment / Location</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Reported</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody data-reports-table-body>
                    @include('officer.reports.partials.rows', ['reports' => $reports, 'showEmptyState' => true])
                </tbody>
            </table>
        </div>

        <div class="report-lazy-load mt-3">
            <div class="report-lazy-load__status text-muted small" data-reports-lazy-status>
                {{ $reports->hasMorePages() ? 'Scroll to load more reports...' : 'All reports loaded.' }}
            </div>
            <div class="report-lazy-load__spinner" data-reports-lazy-loader hidden>
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                <span>Loading more reports...</span>
            </div>
            <div
                class="report-lazy-load__sentinel"
                data-reports-lazy-sentinel
                data-next-page-url="{{ $reports->nextPageUrl() ?? '' }}"
                aria-hidden="true"
            ></div>
        </div>
    </section>
</div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rsrsOfficerReports.css') }}">
@endpush

@push('scripts')
<script>
    (() => {
        const sentinel = document.querySelector('[data-reports-lazy-sentinel]');
        const tableBody = document.querySelector('[data-reports-table-body]');
        const statusNode = document.querySelector('[data-reports-lazy-status]');
        const loaderNode = document.querySelector('[data-reports-lazy-loader]');

        if (!sentinel || !tableBody) {
            return;
        }

        let nextPageUrl = String(sentinel.dataset.nextPageUrl || '');
        let isLoading = false;
        let hasMore = nextPageUrl !== '';

        const setLoading = (loading) => {
            if (!loaderNode) return;
            loaderNode.hidden = !loading;
        };

        const setStatus = (message) => {
            if (!statusNode) return;
            statusNode.textContent = message;
        };

        const observer = new IntersectionObserver((entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) {
                return;
            }

            loadMore();
        }, {
            root: null,
            rootMargin: '360px 0px',
            threshold: 0,
        });

        const loadMore = async () => {
            if (!hasMore || isLoading || !nextPageUrl) {
                if (!hasMore) {
                    observer.disconnect();
                }
                return;
            }

            isLoading = true;
            setLoading(true);

            try {
                const requestUrl = new URL(nextPageUrl, window.location.origin);
                requestUrl.searchParams.set('lazy', '1');

                const response = await fetch(requestUrl.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Could not load additional reports.');
                }

                const payload = await response.json();
                const rowsHtml = String(payload.rows_html || '');

                if (rowsHtml.trim() !== '') {
                    const emptyRow = tableBody.querySelector('[data-empty-row="true"]');
                    if (emptyRow) {
                        emptyRow.remove();
                    }

                    tableBody.insertAdjacentHTML('beforeend', rowsHtml);
                }

                nextPageUrl = String(payload.next_page_url || '');
                sentinel.dataset.nextPageUrl = nextPageUrl;
                hasMore = nextPageUrl !== '';
                setStatus(hasMore ? 'Scroll to load more reports...' : 'All reports loaded.');

                if (!hasMore) {
                    observer.disconnect();
                }
            } catch (error) {
                setStatus('Failed to load more reports. Scroll again to retry.');
            } finally {
                isLoading = false;
                setLoading(false);
            }
        };

        observer.observe(sentinel);
    })();
</script>
@endpush
