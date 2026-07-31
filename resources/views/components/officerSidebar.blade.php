{{-- Reusable Blade component used across multiple RSRS pages. --}}

@php
    $dashboardUrl = auth()->user()?->isAdmin() ? route('admin.dashboard') : url('/road-officer/dashboard');
    $dashboardActive = request()->is('admin/dashboard') || request()->is('road-officer/dashboard');
    $isOperationsMenuOpen =
        request()->is('road-officer/road-segments*') ||
        request()->is('road-officer/segment-types*') ||
        request()->is('road-officer/violation-types*');
    $isReportsMenuOpen = request()->is('road-officer/reports*');
    $isMonitoringMenuOpen =
        request()->is('road-officer/hotspots*') ||
        request()->is('road-officer/contact-messages*') ||
        request()->is('road-officer/notifications*');
    $isAdministrationMenuOpen =
        request()->is('admin/users*') ||
        request()->is('road-officer/drivers*') ||
        request()->is('road-officer/profile*') ||
        request()->is('admin/mail-settings*') ||
        request()->is('admin/audit-logs*');
@endphp

<aside id="sidebar">
    <div class="p-3 officer-sidebar-inner">
        <div class="officer-sidebar-brand mb-3">
            <div class="officer-sidebar-logo" aria-hidden="true">
                <i class="bi bi-cone-striped officer-sidebar-logo-icon"></i>
            </div>
            <div class="officer-sidebar-brand-text">
                <div class="officer-sidebar-brand-title">RSRS</div>
                <div class="officer-sidebar-brand-subtitle">{{ auth()->user()?->isAdmin() ? 'Admin Panel' : 'Officer Panel' }}</div>
            </div>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="{{ $dashboardUrl }}"
                    class="nav-link {{ $dashboardActive ? 'active' : '' }}">
                    <i class="bi bi-signpost-2-fill"></i> Dashboard
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link officer-sidebar-toggle d-flex justify-content-between align-items-center {{ $isOperationsMenuOpen ? 'active' : '' }}"
                    data-bs-toggle="collapse" href="#operationsMenu" role="button"
                    aria-expanded="{{ $isOperationsMenuOpen ? 'true' : 'false' }}" aria-controls="operationsMenu">
                    <span class="officer-sidebar-link-content">
                        <i class="bi bi-cone-striped"></i>
                        <span class="officer-sidebar-link-label">Operations</span>
                    </span>
                    <i class="bi bi-chevron-right officer-sidebar-caret"></i>
                </a>
                <div class="collapse ps-3 {{ $isOperationsMenuOpen ? 'show' : '' }}" id="operationsMenu">
                    <ul class="nav flex-column">
                        <li class="nav-item mb-1">
                            <a href="{{ url('/road-officer/violation-types') }}"
                                class="nav-link officer-sidebar-sub-link {{ request()->is('road-officer/violation-types*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                <i class="bi bi-exclamation-triangle"></i> Violations
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a href="{{ route('officer.segment-types.index') }}"
                                class="nav-link officer-sidebar-sub-link {{ request()->is('road-officer/segment-types*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                <i class="bi bi-diagram-3"></i> Rules
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a href="{{ route('officer.road-segments.index') }}"
                                class="nav-link officer-sidebar-sub-link {{ request()->is('road-officer/road-segments') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                <i class="bi bi-signpost-split"></i> Map Segment
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a href="{{ route('officer.road-segments.manage') }}"
                                class="nav-link officer-sidebar-sub-link {{ request()->is('road-officer/road-segments/manage*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                <i class="bi bi-map"></i> Manage Segments
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="nav-item">
                <a class="nav-link officer-sidebar-toggle d-flex justify-content-between align-items-center {{ $isReportsMenuOpen ? 'active' : '' }}"
                    data-bs-toggle="collapse" href="#reportsMenu" role="button"
                    aria-expanded="{{ $isReportsMenuOpen ? 'true' : 'false' }}" aria-controls="reportsMenu">
                    <span class="officer-sidebar-link-content">
                        <i class="bi bi-file-earmark-text"></i>
                        <span class="officer-sidebar-link-label">Reports</span>
                    </span>
                    <i class="bi bi-chevron-right officer-sidebar-caret"></i>
                </a>
                <div class="collapse ps-3 {{ $isReportsMenuOpen ? 'show' : '' }}" id="reportsMenu">
                    <ul class="nav flex-column">
                        <li class="nav-item mb-1">
                            <a href="{{ url('/road-officer/reports') }}"
                                class="nav-link officer-sidebar-sub-link {{ request()->is('road-officer/reports*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                <i class="bi bi-clipboard2-pulse"></i> Incident Reports
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="nav-item">
                <a class="nav-link officer-sidebar-toggle d-flex justify-content-between align-items-center {{ $isMonitoringMenuOpen ? 'active' : '' }}"
                    data-bs-toggle="collapse" href="#monitoringMenu" role="button"
                    aria-expanded="{{ $isMonitoringMenuOpen ? 'true' : 'false' }}" aria-controls="monitoringMenu">
                    <span class="officer-sidebar-link-content">
                        <i class="bi bi-geo-alt-fill"></i>
                        <span class="officer-sidebar-link-label">Monitoring</span>
                    </span>
                    <i class="bi bi-chevron-right officer-sidebar-caret"></i>
                </a>
                <div class="collapse ps-3 {{ $isMonitoringMenuOpen ? 'show' : '' }}" id="monitoringMenu">
                    <ul class="nav flex-column">
                        <li class="nav-item mb-1">
                            <a href="{{ url('/road-officer/hotspots') }}"
                                class="nav-link officer-sidebar-sub-link {{ request()->is('road-officer/hotspots*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                <i class="bi bi-pin-map"></i> Hotspots
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a href="{{ route('officer.contact-messages.index') }}"
                                class="nav-link officer-sidebar-sub-link {{ request()->is('road-officer/contact-messages*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                <i class="bi bi-envelope-paper"></i> Contact Messages
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a href="{{ url('/road-officer/notifications') }}"
                                class="nav-link {{ request()->is('road-officer/notifications*') ? 'active' : '' }}">
                                <i class="bi bi-bell"></i> Notifications
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="nav-item">
                <a class="nav-link officer-sidebar-toggle d-flex justify-content-between align-items-center {{ $isAdministrationMenuOpen ? 'active' : '' }}"
                    data-bs-toggle="collapse" href="#administrationMenu" role="button"
                    aria-expanded="{{ $isAdministrationMenuOpen ? 'true' : 'false' }}"
                    aria-controls="administrationMenu">
                    <span class="officer-sidebar-link-content">
                        <i class="bi bi-person-badge"></i>
                        <span class="officer-sidebar-link-label">Administration</span>
                    </span>
                    <i class="bi bi-chevron-right officer-sidebar-caret"></i>
                </a>
                <div class="collapse ps-3 {{ $isAdministrationMenuOpen ? 'show' : '' }}" id="administrationMenu">
                    <ul class="nav flex-column">
                        <li class="nav-item mb-1">
                            <a href="{{ route('officer.drivers.index') }}"
                                class="nav-link officer-sidebar-sub-link {{ request()->is('road-officer/drivers*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                <i class="bi bi-bus-front"></i> Drivers
                            </a>
                        </li>
                        @if (auth()->user()?->isAdmin())
                            <li class="nav-item mb-1">
                                <a href="{{ route('admin.users.index') }}"
                                    class="nav-link officer-sidebar-sub-link {{ request()->is('admin/users*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                    <i class="bi bi-people-fill"></i> All Users
                                </a>
                            </li>
                            <li class="nav-item mb-1">
                                <a href="{{ route('admin.mail_settings.content') }}"
                                    class="nav-link officer-sidebar-sub-link {{ request()->is('admin/mail-settings*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                    <i class="bi bi-envelope-fill"></i> Mail Settings
                                </a>
                            </li>
                            <li class="nav-item mb-1">
                                <a href="{{ route('admin.audit-logs.index') }}"
                                    class="nav-link officer-sidebar-sub-link {{ request()->is('admin/audit-logs*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                    <i class="bi bi-activity"></i> Audit Logs
                                </a>
                            </li>
                        @endif
                        <li class="nav-item mb-1">
                            <a href="{{ url('/road-officer/profile') }}"
                                class="nav-link officer-sidebar-sub-link {{ request()->is('road-officer/profile*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                <i class="bi bi-person-circle"></i> My Profile
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>

        <div class="officer-sidebar-footer">
            <div class="officer-sidebar-footer__copy">&copy; 2025 Road Safety System</div>
            <div class="officer-sidebar-footer__collapsed-copy">@2025</div>
        </div>
    </div>
</aside>
