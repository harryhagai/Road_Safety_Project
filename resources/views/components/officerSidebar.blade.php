@php
    $isOperationsMenuOpen = request()->is('road-officer/road-rules*') ||
        request()->is('road-officer/road-segments*') ||
        request()->is('road-officer/segment-types*') ||
        request()->is('road-officer/violation-types*');
    $isReportsMenuOpen = request()->is('road-officer/reports*') ||
        request()->is('road-officer/evidence-files*') ||
        request()->is('road-officer/rule-violations*');
    $isMonitoringMenuOpen = request()->is('road-officer/hotspots*') ||
        request()->is('road-officer/notifications*');
    $isAdministrationMenuOpen = request()->is('road-officer/officers*') ||
        request()->is('road-officer/settings*') ||
        request()->is('road-officer/profile*');
@endphp

<aside id="sidebar">
    <div class="p-3 officer-sidebar-inner">
        <div class="officer-sidebar-brand mb-3">
            <div class="officer-sidebar-logo" aria-hidden="true">
                <i class="bi bi-cone-striped officer-sidebar-logo-icon"></i>
            </div>
            <div class="officer-sidebar-brand-text">
                <div class="officer-sidebar-brand-title">RSRS</div>
                <div class="officer-sidebar-brand-subtitle">Officer Panel</div>
            </div>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="{{ url('/road-officer/dashboard') }}"
                    class="nav-link {{ request()->is('road-officer/dashboard') ? 'active' : '' }}">
                    <i class="bi bi-signpost-2-fill"></i> Dashboard
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link officer-sidebar-toggle d-flex justify-content-between align-items-center {{ $isOperationsMenuOpen ? 'active' : '' }}"
                    data-bs-toggle="collapse" href="#operationsMenu" role="button"
                    aria-expanded="{{ $isOperationsMenuOpen ? 'true' : 'false' }}"
                    aria-controls="operationsMenu">
                    <span class="officer-sidebar-link-content">
                        <i class="bi bi-cone-striped"></i>
                        <span class="officer-sidebar-link-label">Operations</span>
                    </span>
                    <i class="bi bi-chevron-right officer-sidebar-caret"></i>
                </a>
                <div class="collapse ps-3 {{ $isOperationsMenuOpen ? 'show' : '' }}" id="operationsMenu">
                    <ul class="nav flex-column">
                        <li class="nav-item mb-1">
                            <a href="{{ url('/road-officer/road-rules') }}"
                                class="nav-link officer-sidebar-sub-link {{ request()->is('road-officer/road-rules*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                <i class="bi bi-sign-turn-right"></i> Road Rules
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a href="{{ route('officer.road-segments.index') }}"
                                class="nav-link officer-sidebar-sub-link {{ request()->is('road-officer/road-segments*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                <i class="bi bi-signpost-split"></i> Road Segments
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a href="{{ route('officer.segment-types.index') }}"
                                class="nav-link officer-sidebar-sub-link {{ request()->is('road-officer/segment-types*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                <i class="bi bi-diagram-3"></i> Segment Types
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a href="{{ url('/road-officer/violation-types') }}"
                                class="nav-link officer-sidebar-sub-link {{ request()->is('road-officer/violation-types*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                <i class="bi bi-exclamation-triangle"></i> Violation Types
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="nav-item">
                <a class="nav-link officer-sidebar-toggle d-flex justify-content-between align-items-center {{ $isReportsMenuOpen ? 'active' : '' }}"
                    data-bs-toggle="collapse" href="#reportsMenu" role="button"
                    aria-expanded="{{ $isReportsMenuOpen ? 'true' : 'false' }}"
                    aria-controls="reportsMenu">
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
                        <li class="nav-item mb-1">
                            <a href="{{ url('/road-officer/evidence-files') }}"
                                class="nav-link officer-sidebar-sub-link {{ request()->is('road-officer/evidence-files*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                <i class="bi bi-camera-video"></i> Evidence Files
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a href="{{ url('/road-officer/rule-violations') }}"
                                class="nav-link officer-sidebar-sub-link {{ request()->is('road-officer/rule-violations*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                <i class="bi bi-shield-exclamation"></i> Rule Violations
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="nav-item">
                <a class="nav-link officer-sidebar-toggle d-flex justify-content-between align-items-center {{ $isMonitoringMenuOpen ? 'active' : '' }}"
                    data-bs-toggle="collapse" href="#monitoringMenu" role="button"
                    aria-expanded="{{ $isMonitoringMenuOpen ? 'true' : 'false' }}"
                    aria-controls="monitoringMenu">
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
                            <a href="{{ url('/road-officer/officers') }}"
                                class="nav-link officer-sidebar-sub-link {{ request()->is('road-officer/officers*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                <i class="bi bi-person-vcard"></i> Officers
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a href="{{ url('/road-officer/settings') }}"
                                class="nav-link officer-sidebar-sub-link {{ request()->is('road-officer/settings*') ? 'active is-current fw-bold shadow-sm' : '' }}">
                                <i class="bi bi-gear"></i> Settings
                            </a>
                        </li>
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
