(function () {
    const pageData = window.where2goPlaceData || {};
    const body = document.body;
    const themeToggle = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const themeLabel = document.getElementById('theme-label');
    const profileMenus = Array.from(document.querySelectorAll('[data-profile-menu]'));
    const detailTitle = document.getElementById('detail-title');
    const detailSummary = document.getElementById('detail-summary');
    const detailCopy = document.getElementById('detail-copy');
    const detailMeta = document.getElementById('detail-meta');
    const contactList = document.getElementById('contact-list');
    const galleryMain = document.getElementById('gallery-main');
    const galleryStrip = document.getElementById('gallery-strip');
    const photoCredit = document.getElementById('photo-credit');
    const reviewsGrid = document.getElementById('reviews-grid');
    const savePlaceButton = document.getElementById('save-place-button');
    const toggleMapButton = document.getElementById('toggle-map-button');
    const detailMapPanel = document.getElementById('detail-map-panel');
    const detailMapNode = document.getElementById('detail-map');
    const detailMapStatus = document.getElementById('detail-map-status');
    const detailLocationSummary = document.getElementById('detail-location-summary');
    const detailLocationLinks = document.getElementById('detail-location-links');
    const mapsApiKey = pageData.mapsApiKey || '';
    const catalogPlace = pageData.catalogPlace || null;
    const catalogId = pageData.catalogId || '';
    const incomingPlaceId = pageData.placeId || '';
    const isLoggedIn = Boolean(pageData.isLoggedIn);
    const storedSavedKey = 'where2go-saved-places';
    const savedLookup = new Set();
    let resolvedPlace = null;
    let detailMap = null;
    let detailMarker = null;

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
        updateSaveButton();
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
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

    function buildMapsQuery() {
        if (catalogPlace && catalogPlace.query) {
            return catalogPlace.query;
        }

        const placeName = (resolvedPlace && resolvedPlace.name) || (catalogPlace && catalogPlace.name) || 'Where2Go place';
        const address = (resolvedPlace && resolvedPlace.formatted_address)
            || [
                catalogPlace && catalogPlace.area ? catalogPlace.area : '',
                catalogPlace && catalogPlace.city ? catalogPlace.city : 'Egypt',
            ].filter(Boolean).join(', ');

        return [placeName, address].filter(Boolean).join(' ');
    }

    function buildGoogleMapsSearchUrl(place) {
        if (place && place.url) {
            return place.url;
        }

        return 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(buildMapsQuery());
    }

    function renderReviewMessage(message) {
        if (!reviewsGrid) {
            return;
        }

        reviewsGrid.innerHTML = '<div class="review-card"><p>' + escapeHtml(message) + '</p></div>';
    }

    function setGalleryPhoto(photoUrl, attributionHtml) {
        galleryMain.style.backgroundImage = photoUrl ? 'url("' + photoUrl.replace(/"/g, '\\"') + '")' : '';
        galleryMain.innerHTML = photoUrl ? '' : '<i data-lucide="' + escapeHtml(catalogPlace && catalogPlace.icon ? catalogPlace.icon : 'map-pinned') + '" style="width:64px;height:64px;"></i>';
        photoCredit.innerHTML = attributionHtml || '';
        lucide.createIcons();
    }

    function renderGallery(photos) {
        if (!Array.isArray(photos) || photos.length === 0) {
            setGalleryPhoto('', '');
            galleryStrip.innerHTML = '';
            return;
        }

        const mainPhoto = photos[0];
        setGalleryPhoto(mainPhoto.getUrl({ maxWidth: 1200, maxHeight: 720 }), Array.isArray(mainPhoto.html_attributions) ? mainPhoto.html_attributions.join(' ') : '');
        galleryStrip.innerHTML = '';

        photos.slice(0, 6).forEach((photo, index) => {
            const thumb = document.createElement('button');
            thumb.type = 'button';
            thumb.className = 'gallery-thumb' + (index === 0 ? ' is-active' : '');
            thumb.style.backgroundImage = 'url("' + photo.getUrl({ maxWidth: 220, maxHeight: 220 }).replace(/"/g, '\\"') + '")';
            thumb.addEventListener('click', () => {
                galleryStrip.querySelectorAll('.gallery-thumb').forEach((item) => item.classList.remove('is-active'));
                thumb.classList.add('is-active');
                setGalleryPhoto(photo.getUrl({ maxWidth: 1200, maxHeight: 720 }), Array.isArray(photo.html_attributions) ? photo.html_attributions.join(' ') : '');
            });
            galleryStrip.appendChild(thumb);
        });
    }

    function renderReviews(reviews) {
        if (!reviewsGrid) {
            return;
        }

        reviewsGrid.innerHTML = '';

        if (!Array.isArray(reviews) || reviews.length === 0) {
            renderReviewMessage('Google Maps did not return public reviews for this place yet.');
            return;
        }

        reviews.slice(0, 3).forEach((review) => {
            const card = document.createElement('article');
            card.className = 'review-card';
            card.innerHTML = [
                '<h3>' + escapeHtml(review.author_name || 'Google user') + '</h3>',
                '<div class="detail-meta" style="margin-bottom:10px;">',
                '<span class="meta-pill"><i data-lucide="star" style="width:14px;height:14px;"></i>' + escapeHtml(review.rating || 'N/A') + '</span>',
                '<span class="meta-pill"><i data-lucide="clock-3" style="width:14px;height:14px;"></i>' + escapeHtml(review.relative_time_description || 'Recent') + '</span>',
                '</div>',
                '<p>' + escapeHtml(review.text || 'No review text provided.') + '</p>',
            ].join('');
            reviewsGrid.appendChild(card);
        });

        lucide.createIcons();
    }

    function renderMeta(place) {
        detailMeta.innerHTML = [
            '<span class="meta-pill"><i data-lucide="star" style="width:14px;height:14px;"></i>' + escapeHtml(place.rating ? place.rating.toFixed(1) : 'N/A') + ' (' + escapeHtml(place.user_ratings_total || 0) + ')</span>',
            '<span class="meta-pill"><i data-lucide="wallet" style="width:14px;height:14px;"></i>' + escapeHtml(formatPriceLevel(place.price_level)) + '</span>',
            '<span class="meta-pill"><i data-lucide="layers-3" style="width:14px;height:14px;"></i>' + escapeHtml((place.types && place.types[0]) ? place.types[0].replace(/_/g, ' ') : 'Place') + '</span>',
        ].join('');

        if (place.opening_hours && typeof place.opening_hours.isOpen === 'function') {
            detailMeta.innerHTML += '<span class="meta-pill"><i data-lucide="clock-3" style="width:14px;height:14px;"></i>' + (place.opening_hours.isOpen() ? 'Open now' : 'Closed now') + '</span>';
        }

        lucide.createIcons();
    }

    function renderMetaFallback(message) {
        const pills = [];

        if (catalogPlace && catalogPlace.category) {
            pills.push('<span class="meta-pill"><i data-lucide="layers-3" style="width:14px;height:14px;"></i>' + escapeHtml(catalogPlace.category) + '</span>');
        }

        if (catalogPlace && catalogPlace.price_range) {
            pills.push('<span class="meta-pill"><i data-lucide="wallet" style="width:14px;height:14px;"></i>' + escapeHtml(catalogPlace.price_range) + '</span>');
        }

        if (catalogPlace && (catalogPlace.area || catalogPlace.city)) {
            pills.push('<span class="meta-pill"><i data-lucide="map-pin" style="width:14px;height:14px;"></i>' + escapeHtml([catalogPlace.area || '', catalogPlace.city || ''].filter(Boolean).join(', ')) + '</span>');
        }

        if (message) {
            pills.push('<span class="meta-pill"><i data-lucide="info" style="width:14px;height:14px;"></i>' + escapeHtml(message) + '</span>');
        }

        detailMeta.innerHTML = pills.join('');
        lucide.createIcons();
    }

    function renderContact(place) {
        const items = [];

        items.push('<span><i data-lucide="map-pin" style="width:16px;height:16px;"></i>' + escapeHtml(place.formatted_address || 'Cairo, Egypt') + '</span>');

        if (place.formatted_phone_number) {
            items.push('<a href="tel:' + escapeHtml(place.formatted_phone_number) + '"><i data-lucide="phone" style="width:16px;height:16px;"></i>' + escapeHtml(place.formatted_phone_number) + '</a>');
        }

        if (place.website) {
            items.push('<a href="' + escapeHtml(place.website) + '" target="_blank" rel="noopener noreferrer"><i data-lucide="globe" style="width:16px;height:16px;"></i>Visit website</a>');
        }

        if (place.url) {
            items.push('<a href="' + escapeHtml(place.url) + '" target="_blank" rel="noopener noreferrer"><i data-lucide="map" style="width:16px;height:16px;"></i>Open in Google Maps</a>');
        }

        contactList.innerHTML = items.join('');
        lucide.createIcons();
    }

    function renderContactFallback(message) {
        const address = [
            catalogPlace && catalogPlace.area ? catalogPlace.area : '',
            catalogPlace && catalogPlace.city ? catalogPlace.city : '',
        ].filter(Boolean).join(', ') || 'Cairo, Egypt';
        const items = [
            '<span><i data-lucide="map-pin" style="width:16px;height:16px;"></i>' + escapeHtml(address) + '</span>',
            '<a href="' + escapeHtml(buildGoogleMapsSearchUrl(catalogPlace)) + '" target="_blank" rel="noopener noreferrer"><i data-lucide="map"></i>Open in Google Maps</a>',
        ];

        if (message) {
            items.push('<span><i data-lucide="info" style="width:16px;height:16px;"></i>' + escapeHtml(message) + '</span>');
        }

        contactList.innerHTML = items.join('');
        lucide.createIcons();
    }

    function renderLocationPanel(place, message) {
        if (!detailLocationSummary || !detailLocationLinks || !detailMapStatus) {
            return;
        }

        const address = (place && place.formatted_address)
            || [
                catalogPlace && catalogPlace.area ? catalogPlace.area : '',
                catalogPlace && catalogPlace.city ? catalogPlace.city : '',
            ].filter(Boolean).join(', ')
            || 'Cairo, Egypt';

        detailLocationSummary.innerHTML = [
            '<strong>Location</strong>',
            '<p>' + escapeHtml(address) + '</p>',
        ].join('');

        const links = [
            '<a href="' + escapeHtml(buildGoogleMapsSearchUrl(place)) + '" target="_blank" rel="noopener noreferrer"><i data-lucide="map-pinned"></i>Open in Google Maps</a>',
        ];

        if (place && place.website) {
            links.push('<a href="' + escapeHtml(place.website) + '" target="_blank" rel="noopener noreferrer"><i data-lucide="globe"></i>Visit website</a>');
        }

        detailLocationLinks.innerHTML = links.join('');
        detailMapStatus.textContent = message;

        if (!place || !place.geometry || !place.geometry.location) {
            detailMapNode.classList.add('hidden');
        }

        lucide.createIcons();
    }

    async function tryFetchEditorialSummary(placeId) {
        if (!window.google.maps.importLibrary || !placeId) {
            return '';
        }

        try {
            const placesLibrary = await window.google.maps.importLibrary('places');

            if (!placesLibrary || typeof placesLibrary.Place !== 'function') {
                return '';
            }

            const place = new placesLibrary.Place({ id: placeId });
            await place.fetchFields({ fields: ['editorialSummary', 'displayName'] });

            return place.editorialSummary || '';
        } catch (error) {
            return '';
        }
    }

    function getActiveSavePlaceId() {
        if (catalogId) {
            return catalogId;
        }

        if (resolvedPlace && resolvedPlace.place_id) {
            return resolvedPlace.place_id;
        }

        if (incomingPlaceId) {
            return incomingPlaceId;
        }

        return '';
    }

    function buildGooglePayload(place) {
        const photo = place && place.photos && place.photos[0] ? place.photos[0] : null;

        return {
            name: place && place.name ? place.name : 'Google place',
            category: (place && place.types && place.types[0]) ? place.types[0].replace(/_/g, ' ') : 'google place',
            address: (place && place.formatted_address) || 'Cairo, Egypt',
            description: detailCopy.textContent || detailSummary.textContent || ('Google Maps result for ' + ((place && place.name) || 'this place') + '.'),
            price_range: formatPriceLevel(place && place.price_level),
            rating: place && place.rating ? place.rating.toFixed(1) : '',
            reviews: place && place.user_ratings_total ? place.user_ratings_total : 0,
            icon: iconForTypes(place && place.types ? place.types : []),
            photo_url: photo && typeof photo.getUrl === 'function' ? photo.getUrl({ maxWidth: 800, maxHeight: 520 }) : '',
            photo_attribution: photo && Array.isArray(photo.html_attributions) ? photo.html_attributions.join(' ') : '',
            google_maps_url: buildGoogleMapsSearchUrl(place),
            website_url: place && place.website ? place.website : '',
        };
    }

    function buildCatalogPayload() {
        const googlePayload = resolvedPlace ? buildGooglePayload(resolvedPlace) : {};

        return Object.assign({}, catalogPlace || {}, googlePayload, {
            google_maps_url: buildGoogleMapsSearchUrl(resolvedPlace || catalogPlace),
        });
    }

    function getActiveSaveTarget() {
        if (catalogId) {
            return {
                placeId: catalogId,
                source: 'catalog',
                payload: buildCatalogPayload(),
            };
        }

        if (resolvedPlace && resolvedPlace.place_id) {
            return {
                placeId: resolvedPlace.place_id,
                source: 'google',
                payload: buildGooglePayload(resolvedPlace),
            };
        }

        if (incomingPlaceId) {
            return {
                placeId: incomingPlaceId,
                source: 'google',
                payload: {},
            };
        }

        return {
            placeId: '',
            source: 'catalog',
            payload: {},
        };
    }

    function updateSaveButton() {
        if (!savePlaceButton) {
            return;
        }

        if (!isLoggedIn) {
            savePlaceButton.classList.remove('is-saved');
            savePlaceButton.disabled = false;
            savePlaceButton.innerHTML = '<i data-lucide="bookmark-plus"></i>Login to save';
            lucide.createIcons();
            return;
        }

        const activePlaceId = getActiveSavePlaceId();
        const isSaved = activePlaceId !== '' && savedLookup.has(activePlaceId);
        const canUseButton = catalogId !== '' || resolvedPlace !== null;

        savePlaceButton.disabled = !canUseButton;
        savePlaceButton.classList.toggle('is-saved', isSaved);
        savePlaceButton.innerHTML = isSaved
            ? '<i data-lucide="bookmark-check"></i>Remove from profile'
            : '<i data-lucide="bookmark-plus"></i>Save to profile';
        lucide.createIcons();
    }

    async function toggleSavedPlace() {
        if (!isLoggedIn) {
            window.location.href = 'login.php';
            return;
        }

        const target = getActiveSaveTarget();

        if (!target.placeId) {
            window.alert('This place is still loading. Try again in a moment.');
            return;
        }

        const isSaved = savedLookup.has(target.placeId);
        savePlaceButton.disabled = true;

        try {
            const response = await fetch('pages/track_visit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: new URLSearchParams({
                    action: isSaved ? 'remove' : 'save',
                    place_id: target.placeId,
                    source: target.source,
                    payload: JSON.stringify(target.payload || {}),
                }).toString(),
            });

            const result = await response.json();

            if (!response.ok || !result.ok) {
                throw new Error(result.message || 'The place could not be updated.');
            }

            syncSavedLookup(result.visited || []);
        } catch (error) {
            window.alert(error.message || 'The place could not be updated right now.');
        } finally {
            savePlaceButton.disabled = false;
            updateSaveButton();
        }
    }

    function setToggleButtonLabel(isVisible, canShowLocation) {
        if (!toggleMapButton) {
            return;
        }

        if (!canShowLocation) {
            toggleMapButton.disabled = true;
            toggleMapButton.innerHTML = '<i data-lucide="map-off"></i>Location unavailable';
            toggleMapButton.dataset.locationVisible = 'false';
            lucide.createIcons();
            return;
        }

        toggleMapButton.disabled = false;
        toggleMapButton.innerHTML = isVisible
            ? '<i data-lucide="map"></i>Hide location'
            : '<i data-lucide="map"></i>Show location';
        toggleMapButton.dataset.locationVisible = isVisible ? 'true' : 'false';
        lucide.createIcons();
    }

    function renderResolvedMap() {
        if (!resolvedPlace || !resolvedPlace.geometry || !resolvedPlace.geometry.location) {
            detailMapNode.classList.add('hidden');
            detailMapStatus.textContent = 'Live map is unavailable for this place right now. Use the Google Maps link above.';
            return;
        }

        detailMapNode.classList.remove('hidden');

        if (!detailMap) {
            detailMap = new window.google.maps.Map(detailMapNode, {
                center: resolvedPlace.geometry.location,
                zoom: 15,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
            });

            detailMarker = new window.google.maps.Marker({
                map: detailMap,
                position: resolvedPlace.geometry.location,
                title: resolvedPlace.name,
            });
        } else {
            detailMap.setCenter(resolvedPlace.geometry.location);

            if (detailMarker) {
                detailMarker.setPosition(resolvedPlace.geometry.location);
                detailMarker.setTitle(resolvedPlace.name || 'Where2Go place');
            }

            window.google.maps.event.trigger(detailMap, 'resize');
        }

        detailMapStatus.textContent = 'Live map loaded for this place.';
    }

    function openLocationPanel() {
        const locationSource = resolvedPlace || catalogPlace;

        if (!locationSource) {
            setToggleButtonLabel(false, false);
            return;
        }

        detailMapPanel.classList.remove('hidden');
        setToggleButtonLabel(true, true);
        renderLocationPanel(
            locationSource,
            resolvedPlace && resolvedPlace.geometry && resolvedPlace.geometry.location
                ? 'Use the live map below or open the place directly in Google Maps.'
                : 'Live map is unavailable right now, but you can still open the place in Google Maps.'
        );

        if (resolvedPlace && resolvedPlace.geometry && resolvedPlace.geometry.location) {
            renderResolvedMap();
        } else {
            detailMapNode.classList.add('hidden');
        }
    }

    function closeLocationPanel() {
        detailMapPanel.classList.add('hidden');
        detailMapNode.classList.add('hidden');
        detailMapStatus.textContent = 'Press "Show location" to open the location section.';
        setToggleButtonLabel(false, Boolean(resolvedPlace || catalogPlace));
    }

    function toggleLocationVisibility() {
        if (detailMapPanel.classList.contains('hidden')) {
            openLocationPanel();
            return;
        }

        closeLocationPanel();
    }

    async function renderPlaceDetails(place) {
        resolvedPlace = place;
        detailTitle.textContent = place.name || detailTitle.textContent;
        renderMeta(place);
        renderContact(place);
        renderGallery(place.photos || []);
        renderReviews(place.reviews || []);

        const editorialSummary = await tryFetchEditorialSummary(place.place_id);
        const fallbackDescription = catalogPlace && catalogPlace.description
            ? catalogPlace.description
            : 'This place is one of the live Google Maps matches for your Where2Go search.';

        detailCopy.textContent = editorialSummary || fallbackDescription;
        detailSummary.textContent = editorialSummary || fallbackDescription;
        renderLocationPanel(
            place,
            place.geometry && place.geometry.location
                ? 'Open the location section any time to view the live map.'
                : 'Live map is unavailable right now, but the Google Maps link is ready.'
        );

        if (toggleMapButton && toggleMapButton.dataset.locationVisible === 'true') {
            openLocationPanel();
        } else {
            setToggleButtonLabel(false, true);
        }

        updateSaveButton();
    }

    function renderCatalogFallback(statusMessage, reviewMessage) {
        resolvedPlace = null;

        if (catalogPlace) {
            detailTitle.textContent = catalogPlace.name || detailTitle.textContent;
            detailSummary.textContent = catalogPlace.description || detailSummary.textContent;
            detailCopy.textContent = catalogPlace.description || detailCopy.textContent;
            renderGallery([]);
            renderMetaFallback(statusMessage);
            renderContactFallback(statusMessage);
            renderLocationPanel(catalogPlace, statusMessage);
        } else {
            renderMetaFallback(statusMessage);
            renderContactFallback(statusMessage);
            renderLocationPanel(null, statusMessage);
        }

        renderReviewMessage(reviewMessage || statusMessage);

        if (toggleMapButton && toggleMapButton.dataset.locationVisible === 'true') {
            openLocationPanel();
        } else {
            setToggleButtonLabel(false, Boolean(catalogPlace || incomingPlaceId));
        }

        updateSaveButton();
    }

    function getPlacesStatusMessage(status) {
        const serviceStatus = window.google && window.google.maps && window.google.maps.places
            ? window.google.maps.places.PlacesServiceStatus
            : {};

        if (status === serviceStatus.REQUEST_DENIED) {
            return 'Google Maps is rejecting live place details right now. Enable billing in Google Cloud to load reviews, ratings, and maps.';
        }

        if (status === serviceStatus.ZERO_RESULTS || status === 'ZERO_RESULTS') {
            return 'No live Google match was found for this place yet.';
        }

        if (status === serviceStatus.OVER_QUERY_LIMIT) {
            return 'Google Maps hit the current request limit. Try again later.';
        }

        return 'Live Google details could not load for this place right now.';
    }

    function getSeedQuery() {
        if (catalogPlace && catalogPlace.query) {
            return catalogPlace.query;
        }

        if (catalogPlace && catalogPlace.name) {
            return catalogPlace.name + ' New Cairo Egypt';
        }

        return '';
    }

    function resolvePlace(service) {
        if (incomingPlaceId) {
            service.getDetails(
                {
                    placeId: incomingPlaceId,
                    fields: ['name', 'formatted_address', 'rating', 'user_ratings_total', 'price_level', 'website', 'formatted_phone_number', 'opening_hours', 'reviews', 'photos', 'url', 'geometry', 'types', 'place_id'],
                },
                (place, status) => {
                    if (status !== window.google.maps.places.PlacesServiceStatus.OK || !place) {
                        const message = getPlacesStatusMessage(status);
                        renderCatalogFallback(message, message);
                        return;
                    }

                    renderPlaceDetails(place);
                }
            );
            return;
        }

        service.textSearch(
            {
                query: getSeedQuery(),
            },
            (results, status) => {
                if (status !== window.google.maps.places.PlacesServiceStatus.OK || !results || !results[0]) {
                    const message = getPlacesStatusMessage(status);
                    renderCatalogFallback(message, message);
                    return;
                }

                const topMatch = results[0];
                service.getDetails(
                    {
                        placeId: topMatch.place_id,
                        fields: ['name', 'formatted_address', 'rating', 'user_ratings_total', 'price_level', 'website', 'formatted_phone_number', 'opening_hours', 'reviews', 'photos', 'url', 'geometry', 'types', 'place_id'],
                    },
                    (place, detailStatus) => {
                        if (detailStatus !== window.google.maps.places.PlacesServiceStatus.OK || !place) {
                            const message = getPlacesStatusMessage(detailStatus);
                            renderCatalogFallback(message, message);
                            return;
                        }

                        renderPlaceDetails(place);
                    }
                );
            }
        );
    }

    async function initPlacePage() {
        hydrateSavedLookup();
        updateSaveButton();

        if (catalogPlace) {
            renderCatalogFallback(
                'Showing your Where2Go place details while live Google data loads.',
                'Live Google reviews will appear here when the place details finish loading.'
            );
        } else {
            renderReviewMessage('Loading live details for this place.');
        }

        if (!mapsApiKey) {
            renderCatalogFallback(
                'Add a Google Maps key to load live place details.',
                'Live Google reviews need a valid Google Maps key before they can load.'
            );
            return;
        }

        try {
            await loadGoogleMapsApi(mapsApiKey);
            const service = new window.google.maps.places.PlacesService(document.createElement('div'));
            resolvePlace(service);
        } catch (error) {
            renderCatalogFallback(
                'Google Maps could not load. Check billing, referrer rules, and that Places is enabled.',
                'Live Google reviews are unavailable until Google Maps loads correctly.'
            );
        }
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const nextTheme = body.classList.contains('dark-mode') ? 'light' : 'dark';
            localStorage.setItem('where2go-theme', nextTheme);
            applyTheme(nextTheme);
        });
    }

    if (savePlaceButton) {
        savePlaceButton.addEventListener('click', toggleSavedPlace);
    }

    if (toggleMapButton) {
        toggleMapButton.addEventListener('click', toggleLocationVisibility);
    }

    window.addEventListener('pageshow', () => {
        hydrateSavedLookup();
        updateSaveButton();
    });

    applyTheme(localStorage.getItem('where2go-theme') || 'light');
    setupProfileMenus();
    setToggleButtonLabel(false, Boolean(catalogPlace || incomingPlaceId));
    lucide.createIcons();
    initPlacePage();
})();
