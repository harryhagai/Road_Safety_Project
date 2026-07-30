(function () {
    const form = document.querySelector('[data-passenger-report-form]');
    const sessionCountdown = document.querySelector('[data-session-countdown]');

    function pluralize(value, singular, plural = `${singular}s`) {
        return `${value} ${value === 1 ? singular : plural}`;
    }

    function formatRemaining(seconds) {
        if (seconds <= 0) {
            return 'Expired';
        }

        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainingSeconds = seconds % 60;

        if (hours > 0) {
            return `${pluralize(hours, 'hour')} ${pluralize(minutes, 'minute')} remaining`;
        }

        return `${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')} remaining`;
    }

    function startSessionCountdown() {
        if (!sessionCountdown) return;

        const expiresAt = Number.parseInt(sessionCountdown.dataset.expiresAt, 10) * 1000;
        const expiredRedirectUrl = sessionCountdown.dataset.expiredRedirect;

        if (!Number.isFinite(expiresAt)) return;

        let timer = null;
        const updateCountdown = () => {
            const secondsRemaining = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
            sessionCountdown.textContent = formatRemaining(secondsRemaining);
            sessionCountdown.classList.toggle('is-urgent', secondsRemaining > 0 && secondsRemaining <= 60);
            sessionCountdown.classList.toggle('is-expired', secondsRemaining <= 0);

            if (secondsRemaining <= 0 && timer) {
                window.clearInterval(timer);
            }

            if (secondsRemaining <= 0 && expiredRedirectUrl) {
                window.location.assign(expiredRedirectUrl);
            }
        };

        updateCountdown();

        if (!sessionCountdown.classList.contains('is-expired')) {
            timer = window.setInterval(updateCountdown, 1000);
        }
    }

    startSessionCountdown();

    function initBusSuggestions() {
        if (!form) return;

        const suggestionsUrl = form.dataset.busSuggestionsUrl;
        const operatorInput = form.querySelector('[data-bus-operator-input]');
        const plateInput = form.querySelector('[data-bus-plate-input]');
        const menus = Array.from(form.querySelectorAll('[data-bus-suggestions]'));
        let controller = null;
        let activeInput = null;
        let debounceTimer = null;

        if (!suggestionsUrl || !operatorInput || !plateInput || menus.length === 0) return;

        function hideMenus() {
            menus.forEach((menu) => {
                menu.hidden = true;
                menu.innerHTML = '';
            });
        }

        function suggestionText(item) {
            return [item.operator, item.vehicle, item.plate_number].filter(Boolean).join(' · ');
        }

        function applySuggestion(item) {
            operatorInput.value = item.operator || '';
            plateInput.value = item.plate_number || '';
            hideMenus();
        }

        function renderSuggestions(items) {
            hideMenus();

            if (!activeInput || items.length === 0) return;

            const autocomplete = activeInput.closest('[data-bus-autocomplete]');
            const menu = autocomplete?.querySelector('[data-bus-suggestions]');
            if (!menu) return;

            const fragment = document.createDocumentFragment();

            items.forEach((item) => {
                const option = document.createElement('button');
                const title = document.createElement('strong');
                const meta = document.createElement('span');
                option.type = 'button';
                option.className = 'passenger-autocomplete__option';
                option.setAttribute('role', 'option');
                title.textContent = item.label || item.operator || 'Registered bus';
                meta.textContent = suggestionText(item);
                option.append(title, meta);
                option.addEventListener('mousedown', (event) => event.preventDefault());
                option.addEventListener('click', () => applySuggestion(item));
                fragment.appendChild(option);
            });

            menu.appendChild(fragment);
            menu.hidden = false;
        }

        async function fetchSuggestions(query) {
            if (controller) {
                controller.abort();
            }

            controller = new AbortController();
            const url = new URL(suggestionsUrl, window.location.origin);
            url.searchParams.set('q', query);

            try {
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });

                if (!response.ok) return;

                const payload = await response.json();
                renderSuggestions(Array.isArray(payload.data) ? payload.data : []);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    hideMenus();
                }
            }
        }

        function queueSuggestions(input) {
            activeInput = input;
            const query = input.value.trim();
            window.clearTimeout(debounceTimer);

            if (query.length < 2) {
                hideMenus();
                return;
            }

            debounceTimer = window.setTimeout(() => fetchSuggestions(query), 220);
        }

        [operatorInput, plateInput].forEach((input) => {
            input.addEventListener('input', () => queueSuggestions(input));
            input.addEventListener('focus', () => queueSuggestions(input));
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    hideMenus();
                }
            });
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('[data-bus-autocomplete]')) {
                hideMenus();
            }
        });
    }

    initBusSuggestions();

})();
