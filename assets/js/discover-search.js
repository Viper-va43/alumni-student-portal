(function () {
    const pageData = window.where2goSearchData || {};
    const body = document.body;
    const themeToggle = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const themeLabel = document.getElementById('theme-label');
    const profileMenus = Array.from(document.querySelectorAll('[data-profile-menu]'));
    const resultsGrid = document.getElementById('results-grid');
    const resultsEmpty = document.getElementById('results-empty');
    const resultsStatus = document.getElementById('results-status');
    const mapStatus = document.getElementById('map-status');
    const loadMoreButton = document.getElementById('load-more-button');
    const searchMapNode = document.getElementById('search-map');
    const searchQuery = (pageData.query || '').trim();
    const mapsApiKey = pageData.mapsApiKey || '';
    const isLoggedIn = Boolean(pageData.isLoggedIn);
    const savedLookup = new Set();
    const storedSavedKey = 'where2go-saved-places';
    const cairoCenter = { lat: 30.0444, lng: 31.2357 };
    let searchMap = null;
    let placesService = null;
    let markers = [];
    let bounds = null;
    let loadNextPage = null;
    let firstBatch = true;

    function applyTheme(theme) {
        const isDark = theme === 'dark';
        body.classList.toggle('dark-mode', isDark);
        body.classList.toggle('light-mode', !isDark);
        themeIcon.setAttribute('data-lucide', isDark ? 'moon-star' : 'sun-medium');
        themeLabel.textContent = isDark ? 'Dark mode' : 'Light mode';
        lucide.createIcons();
    }

    function closeProfileMenus() {
        profileMenus.forEach((menu) => {
            const dropdown = menu.querySelector('[data-profile-dropdown]');

            if (dropdown) {
                dropdown.classList.remove('is-open');
            }
        });
    }

    function setupProfileMenus() {
        profileMenus.forEach((menu) => {
            const toggle = menu.querySelector('[data-profile-toggle]');
            const dropdown = menu.querySelector('[data-profile-dropdown]');

            if (!toggle || !dropdown) {
                return;
            }

            toggle.addEventListener('click', (event) => {
                event.stopPropagation();
                const isOpen = dropdown.classList.contains('is-open');
                closeProfileMenus();

                if (!isOpen) {
                    dropdown.classList.add('is-open');
                }
            });
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('[data-profile-menu]')) {
                closeProfileMenus();
            }
        });
    }

    function readStoredSavedIds() {
        if (!isLoggedIn) {
            return [];
        }

        try {
            const rawValue = sessionStorage.getItem(storedSavedKey);
            const parsed = rawValue ? JSON.parse(rawValue) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function writeStoredSavedIds() {
        if (!isLoggedIn) {
            sessionStorage.removeItem(storedSavedKey);
            return;
        }

        sessionStorage.setItem(storedSavedKey, JSON.stringify(Array.from(savedLookup)));
    }

    function hydrateSavedLookup() {
        savedLookup.clear();

        (pageData.visitedPlaceIds || []).forEach((placeId) => {
            if (typeof placeId === 'string' && placeId.trim() !== '') {
                savedLookup.add(placeId);
            }
        });

        readStoredSavedIds().forEach((placeId) => {
            if (typeof placeId === 'string' && placeId.trim() !== '') {
                savedLookup.add(placeId);
            }
        });

        writeStoredSavedIds();
    }

    function syncSavedLookup(nextVisitedIds) {
        savedLookup.clear();

        (Array.isArray(nextVisitedIds) ? nextVisitedIds : []).forEach((placeId) => {
            if (typeof placeId === 'string' && placeId.trim() !== '') {
                savedLookup.add(placeId);
            }
        });

        writeStoredSavedIds();
    }

    function loadGoogleMapsApi(apiKey) {
        if (window.google && window.google.maps) {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            const existingScript = document.querySelector('[data-google-maps-loader]');

            if (existingScript) {
                existingScript.addEventListener('load', resolve, { once: true });
                existingScript.addEventListener('error', reject, { once: true });
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(apiKey) + '&libraries=places&v=weekly';
            script.async = true;
            script.defer = true;
            script.setAttribute('data-google-maps-loader', 'true');
            script.addEventListener('load', resolve, { once: true });
            script.addEventListener('error', reject, { once: true });
            document.head.appendChild(script);
        });
    }

    function resolveSearchQuery(query) {
        const trimmed = query.trim();

        if (trimmed === '') {
            return 'nightlife in Cairo Egypt';
        }

        const lower = trimmed.toLowerCase();

        if (lower.includes('cairo') || lower.includes('egypt')) {
            return trimmed;
        }

        return trimmed + ' in Cairo Egypt';
    }

    function formatPriceLevel(level) {
        if (typeof level === 'number' && level > 0) {
            return '$'.repeat(level);
        }

        return '$$';
    }

    function iconForTypes(types) {
        const lookup = Array.isArray(types) ? types : [];

        if (lookup.includes('restaurant') || lookup.includes('cafe')) {
            return 'utensils-crossed';
        }

        if (lookup.includes('night_club') || lookup.includes('bar')) {
            return 'music-4';
        }

        if (lookup.includes('movie_theater') || lookup.includes('tourist_attraction')) {
            return 'star';
        }

        if (lookup.includes('amusement_park') || lookup.includes('bowling_alley')) {
            return 'gamepad-2';
        }

        return 'map-pinned';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildGooglePayload(place) {
        const photo = place.photos && place.photos[0] ? place.photos[0] : null;

        return {
            name: place.name || 'Google place',
            category: (place.types && place.types[0]) ? place.types[0].replace(/_/g, ' ') : 'google place',
            address: place.formatted_address || place.vicinity || 'Cairo, Egypt',
            description: 'Live Google Maps result for ' + (place.name || 'this place') + '.',
            price_range: formatPriceLevel(place.price_level),
            rating: place.rating ? place.rating.toFixed(1) : '',
            reviews: place.user_ratings_total || 0,
            icon: iconForTypes(place.types),
            photo_url: photo && typeof photo.getUrl === 'function' ? photo.getUrl({ maxWidth: 540, maxHeight: 320 }) : '',
            photo_attribution: photo && Array.isArray(photo.html_attributions) ? photo.html_attributions.join(' ') : '',
            google_maps_url: place.url || '',
            website_url: place.website || '',
        };
    }

    async function saveGooglePlace(place, button) {
        if (!isLoggedIn) {
            window.location.href = 'login.php';
            return;
        }

        const isSaved = savedLookup.has(place.place_id);
        button.disabled = true;

        try {
            const response = await fetch('pages/track_visit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: new URLSearchParams({
                    action: isSaved ? 'remove' : 'save',
                    place_id: place.place_id,
                    source: 'google',
                    payload: JSON.stringify(buildGooglePayload(place)),
                }).toString(),
            });

            const payload = await response.json();

            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || 'The place could not be updated.');
            }

            syncSavedLookup(payload.visited || []);
            button.classList.toggle('is-saved', !isSaved);
            button.innerHTML = !isSaved
                ? '<i data-lucide="bookmark-check"></i>Remove from profile'
                : '<i data-lucide="bookmark-plus"></i>Save to profile';
            lucide.createIcons();
        } catch (error) {
            window.alert(error.message || 'The place could not be updated right now.');
        } finally {
            button.disabled = false;
        }
    }

    function clearMarkers() {
        markers.forEach((marker) => marker.setMap(null));
        markers = [];
        bounds = new window.google.maps.LatLngBounds();
    }

    function focusMarker(index) {
        const marker = markers[index];

        if (!marker || !searchMap) {
            return;
        }

        searchMap.panTo(marker.getPosition());
        searchMap.setZoom(14);
        window.google.maps.event.trigger(marker, 'click');
    }

    function renderResultCard(place, index) {
        const photo = place.photos && place.photos[0] ? place.photos[0] : null;
        const photoUrl = photo && typeof photo.getUrl === 'function' ? photo.getUrl({ maxWidth: 680, maxHeight: 420 }) : '';
        const attribution = photo && Array.isArray(photo.html_attributions) ? photo.html_attributions.join(' ') : '';
        const isSaved = savedLookup.has(place.place_id);
        const card = document.createElement('article');
        const detailHref = 'place.php?place_id=' + encodeURIComponent(place.place_id) + '&q=' + encodeURIComponent(searchQuery);

        card.className = 'result-card';
        card.innerHTML = [
            '<div class="result-media"' + (photoUrl ? ' style="background-image:url(\'' + photoUrl.replace(/'/g, "\\'") + '\')"' : '') + '>',
            photoUrl ? '' : '<i data-lucide="' + escapeHtml(iconForTypes(place.types)) + '" style="width:54px;height:54px;"></i>',
            '</div>',
            attribution ? '<div class="photo-attribution">' + attribution + '</div>' : '',
            '<div>',
            '<h3 class="result-title">' + escapeHtml(place.name || 'Untitled place') + '</h3>',
            '<div class="result-subtitle">' + escapeHtml(place.formatted_address || place.vicinity || 'Cairo, Egypt') + '</div>',
            '</div>',
            '<div class="result-tags">',
            '<span class="tag"><i data-lucide="star" style="width:14px;height:14px;"></i>' + escapeHtml(place.rating ? place.rating.toFixed(1) : 'N/A') + ' (' + escapeHtml(place.user_ratings_total || 0) + ')</span>',
            '<span class="tag"><i data-lucide="wallet" style="width:14px;height:14px;"></i>' + escapeHtml(formatPriceLevel(place.price_level)) + '</span>',
            '<span class="tag"><i data-lucide="layers-3" style="width:14px;height:14px;"></i>' + escapeHtml((place.types && place.types[0]) ? place.types[0].replace(/_/g, ' ') : 'Place') + '</span>',
            '</div>',
            '<div class="result-actions">',
            '<a class="secondary-btn" href="' + detailHref + '"><i data-lucide="arrow-up-right"></i>Open details</a>',
            '<button class="primary-btn' + (isSaved ? ' is-saved' : '') + '" type="button" data-save-place="' + escapeHtml(place.place_id) + '"><i data-lucide="' + (isSaved ? 'bookmark-check' : 'bookmark-plus') + '"></i>' + (isLoggedIn ? (isSaved ? 'Remove from profile' : 'Save to profile') : 'Login to save') + '</button>',
            '</div>',
        ].join('');

        const saveButton = card.querySelector('[data-save-place]');

        if (saveButton) {
            saveButton.addEventListener('click', () => saveGooglePlace(place, saveButton));
        }

        card.addEventListener('click', (event) => {
            if (event.target.closest('a') || event.target.closest('button')) {
                return;
            }

            window.location.href = detailHref;
        });

        card.addEventListener('mouseenter', () => focusMarker(index));
        resultsGrid.appendChild(card);
    }

    function appendMarker(place, index) {
        const marker = new window.google.maps.Marker({
            map: searchMap,
            position: place.geometry.location,
            title: place.name,
        });

        const infoWindow = new window.google.maps.InfoWindow({
            content: [
                '<div style="max-width:220px;font-family:Plus Jakarta Sans,sans-serif;">',
                '<strong style="display:block;margin-bottom:6px;font-family:Sora,sans-serif;">' + escapeHtml(place.name || 'Place') + '</strong>',
                '<div style="margin-bottom:6px;color:#6f6156;">' + escapeHtml(place.formatted_address || place.vicinity || 'Cairo, Egypt') + '</div>',
                '<div style="display:flex;gap:8px;flex-wrap:wrap;">',
                '<span style="padding:6px 10px;border-radius:999px;background:#fff3e8;color:#c85108;font-size:12px;font-weight:700;">' + escapeHtml(place.rating ? place.rating.toFixed(1) : 'N/A') + '</span>',
                '<span style="padding:6px 10px;border-radius:999px;background:#fff3e8;color:#c85108;font-size:12px;font-weight:700;">' + escapeHtml(formatPriceLevel(place.price_level)) + '</span>',
                '</div></div>',
            ].join(''),
        });

        marker.addListener('click', () => {
            infoWindow.open({
                anchor: marker,
                map: searchMap,
            });
        });

        markers.push(marker);
        bounds.extend(marker.getPosition());
    }

    function renderBatch(results, clearExisting) {
        if (clearExisting) {
            resultsGrid.innerHTML = '';
            clearMarkers();
        }

        results.forEach((place, index) => {
            appendMarker(place, markers.length);
            renderResultCard(place, markers.length - 1);
        });

        if (markers.length > 0) {
            searchMap.fitBounds(bounds);
        }

        lucide.createIcons();
    }

    function handleTextSearch(results, status, pagination, isInitialRequest) {
        if (status !== window.google.maps.places.PlacesServiceStatus.OK || !results || results.length === 0) {
            if (isInitialRequest) {
                resultsEmpty.classList.remove('hidden');
                if (status === window.google.maps.places.PlacesServiceStatus.REQUEST_DENIED) {
                    resultsStatus.innerHTML = '<i data-lucide="alert-circle"></i>Google blocked live results';
                    resultsEmpty.innerHTML = '<h3 style="margin-top:0;">Live Google results are blocked right now</h3><p>Enable billing for your Google Maps project, then reload this page to see live search results, ratings, and photos.</p>';
                    mapStatus.textContent = 'Google Maps is rejecting search requests for this API key right now.';
                } else {
                    resultsStatus.innerHTML = '<i data-lucide="search-x"></i>No results yet';
                }
                lucide.createIcons();
            }

            loadMoreButton.classList.add('hidden');
            mapStatus.textContent = 'No map markers were returned for that search.';
            return;
        }

        resultsEmpty.classList.add('hidden');
        renderBatch(results, isInitialRequest);
        resultsStatus.innerHTML = '<i data-lucide="map-pinned"></i>' + resultsGrid.children.length + ' places loaded';
        mapStatus.textContent = 'Showing Google Maps results around Cairo for ' + searchQuery + '.';

        if (pagination && pagination.hasNextPage) {
            loadNextPage = function () {
                loadMoreButton.disabled = true;
                loadMoreButton.textContent = 'Loading more...';
                window.setTimeout(() => {
                    pagination.nextPage();
                }, 1200);
            };
            loadMoreButton.classList.remove('hidden');
            loadMoreButton.disabled = false;
            loadMoreButton.innerHTML = '<i data-lucide="plus"></i>Load more results';
        } else {
            loadNextPage = null;
            loadMoreButton.classList.add('hidden');
        }

        lucide.createIcons();
    }

    async function initSearch() {
        if (!mapsApiKey) {
            resultsEmpty.classList.remove('hidden');
            resultsStatus.innerHTML = '<i data-lucide="alert-circle"></i>Missing Google key';
            mapStatus.textContent = 'Add a Google Maps key to load live search results.';
            lucide.createIcons();
            return;
        }

        try {
            await loadGoogleMapsApi(mapsApiKey);

            searchMap = new window.google.maps.Map(searchMapNode, {
                center: cairoCenter,
                zoom: 12,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
            });

            placesService = new window.google.maps.places.PlacesService(searchMap);
            bounds = new window.google.maps.LatLngBounds();
            mapStatus.textContent = 'Searching Google Maps around Cairo.';

            placesService.textSearch(
                {
                    query: resolveSearchQuery(searchQuery),
                    location: cairoCenter,
                    radius: 35000,
                },
                (results, status, pagination) => {
                    handleTextSearch(results, status, pagination, firstBatch);
                    firstBatch = false;
                }
            );
        } catch (error) {
            resultsEmpty.classList.remove('hidden');
            resultsStatus.innerHTML = '<i data-lucide="alert-circle"></i>Search unavailable';
            mapStatus.textContent = 'Google Maps could not load. Check billing, referrer rules, and that Places is enabled.';
            lucide.createIcons();
        }
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const nextTheme = body.classList.contains('dark-mode') ? 'light' : 'dark';
            localStorage.setItem('where2go-theme', nextTheme);
            applyTheme(nextTheme);
        });
    }

    if (loadMoreButton) {
        loadMoreButton.addEventListener('click', () => {
            if (typeof loadNextPage === 'function') {
                loadNextPage();
            }
        });
    }

    hydrateSavedLookup();
    applyTheme(localStorage.getItem('where2go-theme') || 'light');
    setupProfileMenus();
    lucide.createIcons();
    initSearch();
})();
