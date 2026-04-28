(function () {
    function ensureroadofficerAlertStyles() {
        if (document.getElementById('roadofficer-alert-theme-styles')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'roadofficer-alert-theme-styles';
        style.textContent = `
            .roadofficer-ui-alert-popup {
                border-radius: 24px;
                padding: 0 !important;
                overflow: hidden;
                width: 28rem !important;
            }

            .roadofficer-ui-alert-html {
                margin: 0 !important;
                padding: 0 !important;
            }

            .roadofficer-ui-alert {
                padding: 1.2rem 1rem 0.8rem;
                text-align: center;
                background: linear-gradient(180deg, var(--roadofficer-alert-bg-top, #f7fbff) 0%, #ffffff 100%);
            }

            .roadofficer-ui-alert__icon-wrap {
                display: flex;
                justify-content: center;
                margin-bottom: 0.7rem;
            }

            .roadofficer-ui-alert__icon {
                width: 56px;
                height: 56px;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: var(--roadofficer-alert-icon-bg, #edf4ff);
                color: var(--roadofficer-alert-accent, #0d6efd);
                font-size: 1.4rem;
                border: 1px solid var(--roadofficer-alert-icon-border, #d8e6ff);
            }

            .roadofficer-ui-alert__kicker {
                font-size: 0.74rem;
                font-weight: 600;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: var(--roadofficer-alert-kicker, #3d6dcc);
                margin-bottom: 0.35rem;
            }

            .roadofficer-ui-alert__title {
                font-size: 1.15rem;
                line-height: 1.2;
                color: var(--roadofficer-alert-title, #173b7a);
                margin: 0 0 0.4rem;
                font-weight: 600;
            }

            .roadofficer-ui-alert__copy {
                margin: 0;
                color: #6b7280;
                font-size: 0.86rem;
                line-height: 1.45;
            }

            .roadofficer-ui-alert-confirm {
                border: 1px solid var(--roadofficer-alert-accent, #0d6efd);
                border-radius: 999px;
                padding: 0.62rem 0.95rem;
                font-weight: 500;
                min-width: 122px;
                margin: 0 0.25rem 0.95rem;
                background: transparent;
                color: var(--roadofficer-alert-accent, #0d6efd);
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
        `;

        document.head.appendChild(style);
    }

    window.showroadofficerUiAlert = function showroadofficerUiAlert(options) {
        if (!window.Swal) {
            return;
        }

        ensureroadofficerAlertStyles();

        const theme = options.theme || 'success';
        const themeVars = theme === 'success'
            ? {
                accent: '#0d6efd',
                bgTop: '#f7fbff',
                iconBg: '#edf4ff',
                iconBorder: '#d8e6ff',
                kicker: '#3d6dcc',
                title: '#173b7a',
                icon: 'bi-check2-circle',
                kickerText: options.kicker || 'Success',
            }
            : {
                accent: '#dc3545',
                bgTop: '#fff8f8',
                iconBg: '#fff1f2',
                iconBorder: '#ffd5da',
                kicker: '#b54757',
                title: '#7f1d1d',
                icon: 'bi-exclamation-circle',
                kickerText: options.kicker || 'Notice',
            };

        window.Swal.fire({
            html: `
                <div
                    class="roadofficer-ui-alert"
                    style="
                        --roadofficer-alert-accent:${themeVars.accent};
                        --roadofficer-alert-bg-top:${themeVars.bgTop};
                        --roadofficer-alert-icon-bg:${themeVars.iconBg};
                        --roadofficer-alert-icon-border:${themeVars.iconBorder};
                        --roadofficer-alert-kicker:${themeVars.kicker};
                        --roadofficer-alert-title:${themeVars.title};
                    "
                >
                    <div class="roadofficer-ui-alert__icon-wrap">
                        <span class="roadofficer-ui-alert__icon">
                            <i class="bi ${options.icon || themeVars.icon}"></i>
                        </span>
                    </div>
                    <div class="roadofficer-ui-alert__kicker">${themeVars.kickerText}</div>
                    <h2 class="roadofficer-ui-alert__title">${options.title || ''}</h2>
                    <p class="roadofficer-ui-alert__copy">${options.text || ''}</p>
                </div>
            `,
            timer: options.timer,
            showConfirmButton: options.showConfirmButton ?? true,
            confirmButtonText: options.confirmButtonText || '<i class="bi bi-check2 me-1"></i> OK',
            customClass: {
                popup: 'roadofficer-ui-alert-popup',
                htmlContainer: 'roadofficer-ui-alert-html',
                confirmButton: 'roadofficer-ui-alert-confirm',
            },
            buttonsStyling: false,
        });
    };
})();
