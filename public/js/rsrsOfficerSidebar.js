// Frontend helper for rsrsOfficerSidebar interactions in the RSRS interface.

document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const toggleIcon = document.getElementById('sidebarToggleIcon');
    const activePageTitle = document.getElementById('activePageTitle');
    const body = document.body;
    const desktopCollapseKey = 'officerSidebarCollapsed';

    if (!sidebar || !toggleBtn || !toggleIcon || !activePageTitle) {
        return;
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function cleanLabel(text) {
        return (text || '')
            .replace(/\u00a0/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function getLinkLabel(link) {
        if (!link) {
            return '';
        }

        const clone = link.cloneNode(true);
        clone.querySelectorAll('i').forEach(function (icon) {
            icon.remove();
        });

        return cleanLabel(clone.textContent);
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function getActivePageLabel() {
        const activeLinks = Array.from(sidebar.querySelectorAll('.nav-link.active'));
        const activeLink = activeLinks[activeLinks.length - 1];

        if (!activeLink) {
            return activePageTitle.dataset.defaultTitle || 'Officer Panel';
        }

        const childLabel = getLinkLabel(activeLink);
        const parentCollapse = activeLink.closest('.collapse');

        if (parentCollapse) {
            const parentLink = parentCollapse.previousElementSibling;
            const parentLabel = getLinkLabel(parentLink);

            if (parentLabel && childLabel && parentLabel !== childLabel) {
                return parentLabel + ' / ' + childLabel;
            }
        }

        return childLabel || activePageTitle.dataset.defaultTitle || 'Officer Panel';
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function updateActivePageTitle() {
        const labelElement = activePageTitle.querySelector('.header-page-label');
        const currentLabel = getActivePageLabel();

        if (labelElement) {
            labelElement.textContent = currentLabel;
        } else {
            activePageTitle.textContent = currentLabel;
        }

        activePageTitle.setAttribute('title', currentLabel);
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function applyNavTooltips() {
        sidebar.querySelectorAll('.nav-link').forEach(function (link) {
            const label = getLinkLabel(link);

            if (label) {
                link.setAttribute('title', label);
            }
        });
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function readDesktopCollapsedState() {
        try {
            return window.localStorage.getItem(desktopCollapseKey) === 'true';
        } catch (error) {
            return false;
        }
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function writeDesktopCollapsedState(isCollapsed) {
        try {
            window.localStorage.setItem(desktopCollapseKey, String(isCollapsed));
        } catch (error) {
            // Ignore storage failures and keep the UI responsive.
        }
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function isMobileView() {
        return window.innerWidth <= 768;
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function updateToggleButtonState() {
        const sidebarVisible = isMobileView()
            ? sidebar.classList.contains('active')
            : !body.classList.contains('sidebar-collapsed');

        toggleBtn.setAttribute('aria-expanded', String(sidebarVisible));
        toggleBtn.setAttribute(
            'aria-label',
            sidebarVisible ? 'Collapse sidebar' : 'Expand sidebar'
        );
        toggleBtn.setAttribute(
            'title',
            sidebarVisible ? 'Collapse sidebar' : 'Expand sidebar'
        );

        if (isMobileView()) {
            toggleIcon.className = sidebarVisible ? 'bi bi-x-lg fs-5' : 'bi bi-list fs-5';
        } else {
            toggleIcon.className = body.classList.contains('sidebar-collapsed')
                ? 'bi bi-layout-sidebar-inset-reverse fs-5'
                : 'bi bi-layout-sidebar-inset fs-5';
        }
    }

    // Encapsulate one UI behavior so the page stays easier to maintain.

    function applyLayoutState() {
        if (isMobileView()) {
            body.classList.remove('sidebar-collapsed');
        } else {
            body.classList.toggle('sidebar-collapsed', readDesktopCollapsedState());
            sidebar.classList.remove('active');
        }

        updateToggleButtonState();
    }

    toggleBtn.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        if (isMobileView()) {
            sidebar.classList.toggle('active');
        } else {
            const isCollapsed = !body.classList.contains('sidebar-collapsed');
            body.classList.toggle('sidebar-collapsed', isCollapsed);
            writeDesktopCollapsedState(isCollapsed);
        }

        updateToggleButtonState();
    });

    document.addEventListener('click', function (event) {
        if (
            isMobileView() &&
            sidebar.classList.contains('active') &&
            !sidebar.contains(event.target) &&
            !toggleBtn.contains(event.target)
        ) {
            sidebar.classList.remove('active');
            updateToggleButtonState();
        }
    });

    sidebar.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            if (!isMobileView() && body.classList.contains('sidebar-collapsed')) {
                event.preventDefault();
                body.classList.remove('sidebar-collapsed');
                writeDesktopCollapsedState(false);
                updateToggleButtonState();

                const targetSelector = link.getAttribute('href');
                const targetCollapse = targetSelector
                    ? sidebar.querySelector(targetSelector)
                    : null;

                if (targetCollapse && window.bootstrap && window.bootstrap.Collapse) {
                    window.bootstrap.Collapse.getOrCreateInstance(targetCollapse, {
                        toggle: false
                    }).show();
                }
            }
        });
    });

    sidebar.addEventListener('click', function (event) {
        event.stopPropagation();
    });

    window.addEventListener('resize', applyLayoutState);

    applyNavTooltips();
    updateActivePageTitle();
    applyLayoutState();
});
