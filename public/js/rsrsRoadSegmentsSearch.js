// Location search behavior for the road-segment builder.

(function () {
    const namespace = window.RsrsRoadSegments = window.RsrsRoadSegments || {};

    async function fetchDirectNominatim(query) {
        const url = `https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(query)}&limit=6&addressdetails=0&countrycodes=tz`;
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
            },
        });
        const payload = await response.json().catch(() => []);
        if (!response.ok || !Array.isArray(payload)) {
            return [];
        }

        return payload
            .map((item) => ({
                label: String(item.display_name || 'Unknown location').split(',')[0] || 'Unknown location',
                subtitle: String(item.display_name || ''),
                lat: Number(item.lat),
                lng: Number(item.lon),
                provider: 'nominatim-direct',
            }))
            .filter((item) => Number.isFinite(item.lat) && Number.isFinite(item.lng));
    }

    function createLocationSearchController(options) {
        const {
            mapRoot,
            input,
            resultsEl,
            statusEl,
            clearButton,
            minChars = namespace.SEARCH_MIN_CHARS,
            onSelect,
        } = options;

        let searchController = null;
        let searchDebounce = null;
        let activeResults = [];
        let activeResultIndex = -1;
        const searchCache = new Map();

        function setSearchStatus(message) {
            if (statusEl) {
                statusEl.textContent = message;
            }
        }

        function renderSearchResults(results) {
            if (!resultsEl) return;

            activeResults = Array.isArray(results) ? results : [];
            activeResultIndex = -1;
            if (activeResults.length === 0) {
                resultsEl.hidden = true;
                resultsEl.innerHTML = '';
                mapRoot.mapApi.clearPreviewLocation?.();
                return;
            }

            resultsEl.hidden = false;
            resultsEl.innerHTML = activeResults
                .map((result, index) => {
                    const label = String(result.label || result.display_name || 'Unknown location');
                    const subtitle = String(result.subtitle || result.display_name || '');
                    return `
                        <button type="button" class="geo-map-search__result" data-location-search-result-index="${index}">
                            <span class="geo-map-search__result-title">${namespace.escapeHtml(label)}</span>
                            <span class="geo-map-search__result-meta">${namespace.escapeHtml(subtitle)}</span>
                        </button>
                    `;
                })
                .join('');
        }

        function focusResultByIndex(index, shouldPreview = true) {
            if (!resultsEl || activeResults.length === 0) {
                return;
            }

            activeResultIndex = Math.max(0, Math.min(index, activeResults.length - 1));
            resultsEl
                .querySelectorAll('[data-location-search-result-index]')
                .forEach((el, idx) => el.classList.toggle('is-active', idx === activeResultIndex));

            const result = activeResults[activeResultIndex];
            const lat = Number(result?.lat);
            const lng = Number(result?.lng);
            if (shouldPreview && Number.isFinite(lat) && Number.isFinite(lng)) {
                mapRoot.mapApi.previewLocation?.(lat, lng, { zoom: 16, animate: true });
            }
        }

        async function runLocationSearch(query) {
            if (!mapRoot.mapApi?.config?.searchUrl) {
                setSearchStatus('Search service unavailable.');
                return;
            }

            if (searchController) {
                searchController.abort();
            }
            const cacheKey = query.toLowerCase();
            if (searchCache.has(cacheKey)) {
                const cached = searchCache.get(cacheKey);
                renderSearchResults(cached);
                setSearchStatus(cached.length > 0 ? `Found ${cached.length} location(s).` : 'No matching locations found.');
                return;
            }

            searchController = new AbortController();
            setSearchStatus('Searching locations...');

            try {
                const response = await fetch(
                    `${mapRoot.mapApi.config.searchUrl}?query=${encodeURIComponent(query)}`,
                    { headers: { Accept: 'application/json' }, signal: searchController.signal }
                );
                const payload = await response.json().catch(() => ({}));
                const items = Array.isArray(payload?.results) ? payload.results : (Array.isArray(payload) ? payload : []);
                searchCache.set(cacheKey, items);

                if (items.length > 0) {
                    renderSearchResults(items);
                    setSearchStatus(`Found ${items.length} location(s).`);
                    return;
                }

                const fallbackItems = await fetchDirectNominatim(query);
                if (fallbackItems.length > 0) {
                    searchCache.set(cacheKey, fallbackItems);
                    renderSearchResults(fallbackItems);
                    setSearchStatus(`Found ${fallbackItems.length} location(s) via browser fallback.`);
                    return;
                }

                renderSearchResults([]);
                setSearchStatus(payload?.message || 'No matching locations found.');
            } catch (error) {
                if (error.name === 'AbortError') return;
                const fallbackItems = await fetchDirectNominatim(query).catch(() => []);
                if (fallbackItems.length > 0) {
                    searchCache.set(query.toLowerCase(), fallbackItems);
                    renderSearchResults(fallbackItems);
                    setSearchStatus(`Found ${fallbackItems.length} location(s) via browser fallback.`);
                    return;
                }

                renderSearchResults([]);
                setSearchStatus('Search failed. Network from server is blocked, and browser fallback also failed.');
            }
        }

        function bind() {
            if (input) {
                input.addEventListener('input', function () {
                    const query = String(input.value || '').trim();
                    if (clearButton) {
                        clearButton.hidden = query.length === 0;
                    }

                    if (searchDebounce) {
                        clearTimeout(searchDebounce);
                    }

                    if (query.length < minChars) {
                        renderSearchResults([]);
                        setSearchStatus('Start typing to find a location and jump the map there.');
                        return;
                    }

                    searchDebounce = setTimeout(() => runLocationSearch(query), 280);
                });

                input.addEventListener('keydown', function (event) {
                    if (!activeResults.length) return;

                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        focusResultByIndex(activeResultIndex + 1);
                        return;
                    }

                    if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        focusResultByIndex(activeResultIndex <= 0 ? activeResults.length - 1 : activeResultIndex - 1);
                        return;
                    }

                    if (event.key === 'Enter') {
                        event.preventDefault();
                        const index = activeResultIndex >= 0 ? activeResultIndex : 0;
                        const target = resultsEl?.querySelector(`[data-location-search-result-index="${index}"]`);
                        target?.click();
                        return;
                    }

                    if (event.key === 'Escape') {
                        renderSearchResults([]);
                        setSearchStatus('Search closed.');
                    }
                });
            }

            if (clearButton) {
                clearButton.addEventListener('click', function () {
                    if (input) {
                        input.value = '';
                        input.focus();
                    }
                    clearButton.hidden = true;
                    renderSearchResults([]);
                    setSearchStatus('Start typing to find a location and jump the map there.');
                    mapRoot.mapApi.clearPreviewLocation?.();
                });
            }

            if (resultsEl) {
                resultsEl.addEventListener('mousemove', function (event) {
                    const button = event.target.closest('[data-location-search-result-index]');
                    if (!button) return;
                    const index = Number(button.getAttribute('data-location-search-result-index'));
                    focusResultByIndex(index, true);
                });

                resultsEl.addEventListener('click', function (event) {
                    const button = event.target.closest('[data-location-search-result-index]');
                    if (!button) return;

                    const index = Number(button.getAttribute('data-location-search-result-index'));
                    const result = activeResults[index];
                    const lat = Number(result?.lat);
                    const lng = Number(result?.lng);

                    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                        return;
                    }

                    onSelect?.({ lat, lng, result });
                    renderSearchResults([]);
                    setSearchStatus('Location selected. You can continue adding points.');
                    mapRoot.mapApi.clearPreviewLocation?.();
                });
            }
        }

        return {
            bind,
            renderSearchResults,
            setSearchStatus,
            runLocationSearch,
        };
    }

    Object.assign(namespace, {
        createLocationSearchController,
        fetchDirectNominatim,
    });
})();
