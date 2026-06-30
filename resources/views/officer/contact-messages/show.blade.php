{{-- Officer module view for show within the road safety dashboard. --}}

@extends('layouts.officerDashboardLayout')

@section('page_header_actions')
    <a href="{{ route('officer.contact-messages.index') }}" class="btn geo-header-btn">
        <i class="bi bi-arrow-left"></i>
        <span>Back to Messages</span>
    </a>
@endsection

@section('content')
    <div class="container-fluid px-2 px-lg-3 py-2">
        <div class="contact-message-detail-grid">
            <section class="contact-message-detail-card">
                <div class="contact-message-detail-card__header">
                    <div>
                        <h2>{{ $contactMessage->subject }}</h2>
                        <p>{{ $contactMessage->reference_no }}</p>
                    </div>
                    <span class="contact-message-status is-{{ str_replace('_', '-', $contactMessage->status) }}">
                        <i class="bi bi-circle-fill"></i>
                        <span>{{ $statuses[$contactMessage->status] ?? ucfirst($contactMessage->status) }}</span>
                    </span>
                </div>

                <div class="contact-message-sender-grid">
                    <div>
                        <span>Name</span>
                        <strong>{{ $contactMessage->name }}</strong>
                    </div>
                    <div>
                        <span>Email</span>
                        <a href="mailto:{{ $contactMessage->email }}">{{ $contactMessage->email }}</a>
                    </div>
                    <div>
                        <span>Phone</span>
                        <strong>{{ $contactMessage->phone ?: 'Not provided' }}</strong>
                    </div>
                    <div>
                        <span>Received</span>
                        <strong>{{ optional($contactMessage->created_at)->format('d M Y, H:i') }}</strong>
                    </div>
                </div>

                <div class="contact-message-body">
                    <h3>Message</h3>
                    <p>{{ $contactMessage->message }}</p>
                </div>

                @if ($contactMessage->response_notes)
                    <div class="contact-message-body contact-message-body--notes">
                        <h3>Officer Notes</h3>
                        <p>{{ $contactMessage->response_notes }}</p>
                    </div>
                @endif
            </section>

            <aside class="contact-message-detail-card">
                <div class="contact-message-panel-heading">
                    <i class="bi bi-clipboard-check"></i>
                    <div>
                        <h2>Manage response</h2>
                        <p>Update status and keep internal handling notes.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('officer.contact-messages.update', $contactMessage) }}" class="contact-message-update-form">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $contactMessage->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="response_notes" class="form-label">Response notes</label>
                        <textarea id="response_notes" name="response_notes" rows="8" class="form-control @error('response_notes') is-invalid @enderror" placeholder="Record calls, emails, or next action taken">{{ old('response_notes', $contactMessage->response_notes) }}</textarea>
                        @error('response_notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn contact-message-primary-btn">
                        <i class="bi bi-check2-circle"></i>
                        <span>Save updates</span>
                    </button>
                </form>

                <div class="contact-message-timeline">
                    <div>
                        <span>Read</span>
                        <strong>{{ optional($contactMessage->read_at)->format('d M Y, H:i') ?: 'Not yet' }}</strong>
                    </div>
                    <div>
                        <span>Responded</span>
                        <strong>{{ optional($contactMessage->responded_at)->format('d M Y, H:i') ?: 'Not yet' }}</strong>
                    </div>
                    <div>
                        <span>Resolved</span>
                        <strong>{{ optional($contactMessage->resolved_at)->format('d M Y, H:i') ?: 'Not yet' }}</strong>
                    </div>
                    <div>
                        <span>Officer</span>
                        <strong>{{ $contactMessage->officer?->name ?? 'Unassigned' }}</strong>
                    </div>
                </div>

                <button type="button" class="btn contact-message-danger-btn" data-bs-toggle="modal" data-bs-target="#deleteContactMessageModal">
                    <i class="bi bi-trash3"></i>
                    <span>Delete message</span>
                </button>
            </aside>
        </div>
    </div>

    <div class="modal fade" id="deleteContactMessageModal" tabindex="-1" aria-labelledby="deleteContactMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content geo-modal">
                <div class="modal-header geo-modal__header">
                    <div class="geo-modal__title-wrap">
                        <span class="geo-modal__icon">
                            <i class="bi bi-trash3"></i>
                        </span>
                        <div>
                            <h5 class="modal-title geo-modal__title" id="deleteContactMessageModalLabel">Delete contact message</h5>
                            <div class="geo-modal__subtitle">This removes the enquiry from officer records.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form method="POST" action="{{ route('officer.contact-messages.destroy', $contactMessage) }}">
                    @csrf
                    @method('DELETE')
                    <div class="modal-body geo-modal__body">
                        <p class="mb-0 text-muted">
                            Delete message <strong>{{ $contactMessage->reference_no }}</strong> from {{ $contactMessage->name }}?
                        </p>
                    </div>
                    <div class="modal-footer geo-modal__footer">
                        <button type="button" class="btn geo-modal__secondary-btn" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i>
                            <span>Cancel</span>
                        </button>
                        <button type="submit" class="btn geo-modal__primary-btn contact-message-delete-confirm">
                            <i class="bi bi-trash3"></i>
                            <span>Delete message</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rsrsMap.css') }}">
    <link rel="stylesheet" href="{{ asset('css/rsrsContactMessages.css') }}">
@endpush
