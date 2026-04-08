@php
    $currentOfficer = Auth::user();
    $notificationSource = $currentOfficer && method_exists($currentOfficer, 'systemNotifications')
        ? $currentOfficer->systemNotifications()
        : null;
    $headerNotifications = $notificationSource ? $notificationSource->latest()->limit(6)->get() : collect();
    $headerUnreadCount = $notificationSource ? $notificationSource->unread()->count() : 0;
    $officerDisplayName = $currentOfficer->full_name ?? $currentOfficer->name ?? 'Road Officer';
@endphp

<header id="main-header" class="d-flex align-items-center justify-content-between bg-white border-bottom shadow-sm px-3">
    <div class="d-flex align-items-center header-page-wrap">
        <button id="sidebarToggle" class="btn btn-outline-secondary me-3" type="button" aria-label="Toggle sidebar" aria-expanded="true">
            <i id="sidebarToggleIcon" class="bi bi-layout-sidebar-inset fs-5"></i>
        </button>

        <div id="activePageTitle" class="header-page-pill" data-default-title="Road Officer Panel">
            <span class="header-page-dot" aria-hidden="true"></span>
            <span class="header-page-label">Road Officer Panel</span>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">
        <div class="dropdown">
            <button
                class="btn btn-light border rounded-circle d-flex align-items-center justify-content-center shadow-sm header-action-btn"
                type="button"
                id="quickAccessDropdown"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                title="Quick Access"
            >
                <i class="bi bi-grid header-theme-icon"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow-sm p-0 overflow-hidden quick-access-menu" aria-labelledby="quickAccessDropdown">
                <div class="px-3 py-2 border-bottom bg-light">
                    <div class="fw-semibold">Quick Access</div>
                    <div class="text-muted small">Jump to commonly used pages.</div>
                </div>
                <div class="py-2">
                    <a href="{{ url('/road-officer/dashboard') }}" class="dropdown-item d-flex align-items-center gap-2 py-2">
                        <i class="bi bi-speedometer2 header-theme-icon"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="{{ url('/road-officer/reports') }}" class="dropdown-item d-flex align-items-center gap-2 py-2">
                        <i class="bi bi-flag header-theme-icon"></i>
                        <span>Reports</span>
                    </a>
                    <a href="{{ url('/road-officer/road-rules') }}" class="dropdown-item d-flex align-items-center gap-2 py-2">
                        <i class="bi bi-sign-turn-right header-theme-icon"></i>
                        <span>Road Rules</span>
                    </a>
                    <a href="{{ url('/road-officer/hotspots') }}" class="dropdown-item d-flex align-items-center gap-2 py-2">
                        <i class="bi bi-geo-alt header-theme-icon"></i>
                        <span>Hotspots</span>
                    </a>
                    <a href="{{ url('/road-officer/settings') }}" class="dropdown-item d-flex align-items-center gap-2 py-2">
                        <i class="bi bi-sliders header-theme-icon"></i>
                        <span>Settings</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="dropdown">
            <button
                class="btn btn-light border rounded-circle position-relative d-flex align-items-center justify-content-center shadow-sm header-action-btn notification-trigger"
                type="button"
                id="notificationDropdown"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >
                <i class="bi bi-bell fs-5 notification-trigger-icon"></i>
                <span id="notificationBadge" class="notification-badge badge rounded-pill bg-danger {{ $headerUnreadCount > 0 ? '' : 'd-none' }}">
                    {{ $headerUnreadCount > 9 ? '9+' : $headerUnreadCount }}
                </span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0 shadow-sm notification-menu" aria-labelledby="notificationDropdown">
                <div class="notification-menu-header d-flex align-items-center justify-content-between px-3 py-3 border-bottom">
                    <div>
                        <div class="fw-semibold">Notifications</div>
                        <div class="text-muted small"><span id="notificationUnreadText">{{ $headerUnreadCount }}</span> unread</div>
                    </div>
                    <form method="POST" action="{{ url('/road-officer/notifications/mark-all-read') }}" id="notificationMarkAllForm" class="{{ $headerUnreadCount > 0 ? '' : 'd-none' }}">
                        @csrf
                        <button type="submit" class="btn btn-link btn-sm text-decoration-none p-0">Mark all read</button>
                    </form>
                </div>
                <div id="notificationDropdownList" class="notification-menu-list">
                    @forelse ($headerNotifications as $notification)
                        <a
                            href="{{ url('/road-officer/notifications/' . $notification->id) }}"
                            class="dropdown-item notification-item px-3 py-3 border-bottom {{ $notification->status === 'unread' ? 'notification-item-unread' : '' }}"
                        >
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div class="pe-2 notification-copy">
                                    <div class="fw-semibold text-wrap notification-title">{{ $notification->title }}</div>
                                    <div class="text-muted small mb-1 notification-message">{{ $notification->message }}</div>
                                    <div class="small text-muted notification-time">{{ $notification->created_at->diffForHumans() }}</div>
                                </div>
                                <span class="badge notification-status {{ $notification->status === 'unread' ? 'notification-status-unread' : 'notification-status-read' }}">
                                    {{ ucfirst($notification->status) }}
                                </span>
                            </div>
                        </a>
                    @empty
                        <div class="px-3 py-5 text-center text-muted notification-empty-state">
                            <i class="bi bi-bell fs-4 d-block mb-2"></i>
                            No notifications yet.
                        </div>
                    @endforelse
                </div>
                <div class="notification-menu-footer px-3 py-2 border-top text-end">
                    <a href="{{ url('/road-officer/notifications') }}" class="btn btn-sm notification-view-all-btn" id="notificationViewAllLink">
                        View all
                    </a>
                </div>
            </div>
        </div>

        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                @if(!empty($currentOfficer?->passport))
                    <img src="{{ asset('storage/' . $currentOfficer->passport) }}" alt="Profile Picture" class="profile-avatar" />
                @else
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($officerDisplayName) }}&background=232c3a&color=fff&size=40" alt="Profile Picture" class="profile-avatar" />
                @endif
                <span class="ms-2 d-none d-md-inline fw-normal">{{ $officerDisplayName }}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                <li>
                    <a class="dropdown-item d-flex align-items-center" href="{{ url('/road-officer/profile') }}">
                        <i class="bi bi-person-fill header-theme-icon me-2"></i> My Profile
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item d-flex align-items-center">
                            <i class="bi bi-box-arrow-right text-danger me-2"></i> Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

<script>
    (() => {
        const badge = document.getElementById('notificationBadge');
        const unreadText = document.getElementById('notificationUnreadText');
        const dropdownList = document.getElementById('notificationDropdownList');
        const markAllForm = document.getElementById('notificationMarkAllForm');
        const viewAllLink = document.getElementById('notificationViewAllLink');
        const endpoint = @json(url('/road-officer/notifications/dropdown-data'));

        if (!badge || !unreadText || !dropdownList || !markAllForm || !viewAllLink) {
            return;
        }

        const escapeHtml = (value) => {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        };

        const renderNotifications = (payload) => {
            const unreadCount = Number(payload.unreadCount || 0);

            unreadText.textContent = unreadCount;
            badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
            badge.classList.toggle('d-none', unreadCount < 1);
            markAllForm.classList.toggle('d-none', unreadCount < 1);
            viewAllLink.setAttribute('href', payload.viewAllUrl);
            markAllForm.setAttribute('action', payload.markAllReadUrl);

            if (!payload.notifications || payload.notifications.length === 0) {
                dropdownList.innerHTML = `
                    <div class="px-3 py-5 text-center text-muted notification-empty-state">
                        <i class="bi bi-bell fs-4 d-block mb-2"></i>
                        No notifications yet.
                    </div>
                `;
                return;
            }

            dropdownList.innerHTML = payload.notifications.map((notification) => `
                <a
                    href="${notification.open_url}"
                    class="dropdown-item notification-item px-3 py-3 border-bottom ${notification.status === 'unread' ? 'notification-item-unread' : ''}"
                >
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="pe-2 notification-copy">
                            <div class="fw-semibold text-wrap notification-title">${escapeHtml(notification.title)}</div>
                            <div class="text-muted small mb-1 notification-message">${escapeHtml(notification.message)}</div>
                            <div class="small text-muted notification-time">${escapeHtml(notification.time)}</div>
                        </div>
                        <span class="badge notification-status ${notification.status === 'unread' ? 'notification-status-unread' : 'notification-status-read'}">
                            ${escapeHtml(notification.status_label)}
                        </span>
                    </div>
                </a>
            `).join('');
        };

        const refreshNotifications = async () => {
            try {
                const response = await fetch(endpoint, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                renderNotifications(payload);
            } catch (error) {
                console.debug('Notification refresh skipped.', error);
            }
        };

        window.refreshAcademicNotifications = refreshNotifications;
        window.setInterval(refreshNotifications, 15000);

        document.getElementById('notificationDropdown')?.addEventListener('show.bs.dropdown', refreshNotifications);

        markAllForm.addEventListener('submit', () => {
            window.setTimeout(refreshNotifications, 800);
        });
    })();
</script>
