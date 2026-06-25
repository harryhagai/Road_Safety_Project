@extends('layouts.officerDashboardLayout')

@section('disable_success_swal')
@endsection

@section('content')
<div data-page-title="Mail Settings" class="container-fluid px-3 px-lg-4 py-4 mail-settings-page">
    <div class="mail-settings-shell">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-envelope-fill fs-4 text-primary"></i>
                    <h4 class="mb-0 fw-bold">Mail Settings</h4>
                </div>
                <p class="text-muted mb-0 mt-1">Manage multiple SMTP profiles by purpose, like forgot-password and parent results.</p>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createMailSettingModal">
                <i class="bi bi-plus-circle me-1"></i> Add Setting
            </button>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Setting</th>
                            <th>Purpose</th>
                            <th>SMTP</th>
                            <th>Sender</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mailSettings as $setting)
                            @php
                                $payload = [
                                    'name' => $setting->name,
                                    'purpose' => $setting->purpose,
                                    'mailer' => $setting->mailer,
                                    'scheme' => $setting->scheme,
                                    'host' => $setting->host,
                                    'port' => $setting->port,
                                    'username' => $setting->username,
                                    'password' => $setting->password,
                                    'from_address' => $setting->from_address,
                                    'from_name' => $setting->from_name,
                                    'is_active' => $setting->is_active,
                                    'update_url' => route('admin.mail_settings.update', $setting),
                                    'delete_url' => route('admin.mail_settings.destroy', $setting),
                                ];
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $setting->name }}</div>
                                    <div class="small text-muted">{{ strtoupper($setting->mailer) }}</div>
                                </td>
                                <td>
                                    <div class="small text-muted">
                                        {{ $purposes[$setting->purpose] ?? ucfirst(str_replace('_', ' ', (string) $setting->purpose)) }}
                                    </div>
                                </td>
                                <td>
                                    <div>{{ $setting->host }}:{{ $setting->port }}</div>
                                    <div class="small text-muted">
                                        {{ $setting->scheme ? strtoupper($setting->scheme) : 'No scheme' }}
                                        @if ($setting->username)
                                            - {{ $setting->username }}
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div>{{ $setting->from_name }}</div>
                                    <a href="mailto:{{ $setting->from_address }}" class="small text-decoration-none">{{ $setting->from_address }}</a>
                                </td>
                                <td>
                                    <span class="badge text-bg-{{ $setting->is_active ? 'success' : 'secondary' }}">
                                        {{ $setting->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-end gap-2">
                                        @unless ($setting->is_active)
                                            <form method="POST" action="{{ route('admin.mail_settings.activate', $setting) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-outline-success">
                                                    <i class="bi bi-check-circle"></i> Activate
                                                </button>
                                            </form>
                                        @endunless

                                        <button type="button" class="btn btn-sm btn-outline-secondary js-mail-edit" data-mail='@json($payload)'>
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-danger js-mail-delete" data-mail='@json($payload)'>
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-envelope-fill d-block fs-3 mb-2"></i>
                                    No mail settings found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            {{ $mailSettings->links() }}
        </div>
    </div>

    <div class="modal fade" id="createMailSettingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.mail_settings.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title fs-5">Add Mail Setting</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('admin.partials.mail_setting_fields', ['mode' => 'create'])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1"></i> Save Setting
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editMailSettingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.mail_settings.content') }}" id="editMailSettingForm">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h2 class="modal-title fs-5">Edit Mail Setting</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('admin.partials.mail_setting_fields', ['mode' => 'edit'])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1"></i> Update Setting
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteMailSettingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.mail_settings.content') }}" id="deleteMailSettingForm">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h2 class="modal-title fs-5">Delete Mail Setting</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Delete <strong data-mail-delete="name"></strong>?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i> Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .mail-settings-page,
    .mail-settings-shell {
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }

    .mail-settings-shell > .card {
        max-width: 100%;
        min-width: 0;
        overflow: hidden;
    }

    .mail-settings-shell .table-responsive {
        width: 100%;
        max-width: 100%;
    }

    @media (max-width: 767.98px) {
        .mail-settings-shell .table {
            min-width: 880px;
        }
    }
</style>
@endpush

@section('scripts')
    <script src="{{ asset('js/adminMailSettings.js') }}" defer></script>
@endsection
