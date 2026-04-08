(function () {
    function ensureAcademicAlertStyles() {
        if (document.getElementById('academic-alert-theme-styles')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'academic-alert-theme-styles';
        style.textContent = `
            .academic-ui-alert-popup {
                border-radius: 24px;
                padding: 0 !important;
                overflow: hidden;
                width: 28rem !important;
            }

            .academic-ui-alert-html {
                margin: 0 !important;
                padding: 0 !important;
            }

            .academic-ui-alert {
                padding: 1.2rem 1rem 0.8rem;
                text-align: center;
                background: linear-gradient(180deg, var(--academic-alert-bg-top, #f7fbff) 0%, #ffffff 100%);
            }

            .academic-ui-alert__icon-wrap {
                display: flex;
                justify-content: center;
                margin-bottom: 0.7rem;
            }

            .academic-ui-alert__icon {
                width: 56px;
                height: 56px;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: var(--academic-alert-icon-bg, #edf4ff);
                color: var(--academic-alert-accent, #0d6efd);
                font-size: 1.4rem;
                border: 1px solid var(--academic-alert-icon-border, #d8e6ff);
            }

            .academic-ui-alert__kicker {
                font-size: 0.74rem;
                font-weight: 600;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: var(--academic-alert-kicker, #3d6dcc);
                margin-bottom: 0.35rem;
            }

            .academic-ui-alert__title {
                font-size: 1.15rem;
                line-height: 1.2;
                color: var(--academic-alert-title, #173b7a);
                margin: 0 0 0.4rem;
                font-weight: 600;
            }

            .academic-ui-alert__copy {
                margin: 0;
                color: #6b7280;
                font-size: 0.86rem;
                line-height: 1.45;
            }

            .academic-ui-alert-confirm {
                border: 1px solid var(--academic-alert-accent, #0d6efd);
                border-radius: 999px;
                padding: 0.62rem 0.95rem;
                font-weight: 500;
                min-width: 122px;
                margin: 0 0.25rem 0.95rem;
                background: transparent;
                color: var(--academic-alert-accent, #0d6efd);
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
        `;

        document.head.appendChild(style);
    }

    window.showAcademicUiAlert = function showAcademicUiAlert(options) {
        if (!window.Swal) {
            return;
        }

        ensureAcademicAlertStyles();

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
                    class="academic-ui-alert"
                    style="
                        --academic-alert-accent:${themeVars.accent};
                        --academic-alert-bg-top:${themeVars.bgTop};
                        --academic-alert-icon-bg:${themeVars.iconBg};
                        --academic-alert-icon-border:${themeVars.iconBorder};
                        --academic-alert-kicker:${themeVars.kicker};
                        --academic-alert-title:${themeVars.title};
                    "
                >
                    <div class="academic-ui-alert__icon-wrap">
                        <span class="academic-ui-alert__icon">
                            <i class="bi ${options.icon || themeVars.icon}"></i>
                        </span>
                    </div>
                    <div class="academic-ui-alert__kicker">${themeVars.kickerText}</div>
                    <h2 class="academic-ui-alert__title">${options.title || ''}</h2>
                    <p class="academic-ui-alert__copy">${options.text || ''}</p>
                </div>
            `,
            timer: options.timer,
            showConfirmButton: options.showConfirmButton ?? true,
            confirmButtonText: options.confirmButtonText || '<i class="bi bi-check2 me-1"></i> OK',
            customClass: {
                popup: 'academic-ui-alert-popup',
                htmlContainer: 'academic-ui-alert-html',
                confirmButton: 'academic-ui-alert-confirm',
            },
            buttonsStyling: false,
        });
    };
})();
