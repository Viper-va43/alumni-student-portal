(function () {
    const pageData = window.where2goHomeData || {};
    const body = document.body;
    const themeToggle = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const themeLabel = document.getElementById('theme-label');
    const introScreen = document.getElementById('intro-screen');
    const searchInput = document.getElementById('search-input');
    const searchButton = document.getElementById('search-button');
    const saveButtons = Array.from(document.querySelectorAll('[data-track-place]'));
    const visitedCount = document.getElementById('visited-count');
    const profileMenus = Array.from(document.querySelectorAll('[data-profile-menu]'));
    const categoryCards = Array.from(document.querySelectorAll('[data-category-query]'));
    const featuredPlaces = pageData.featuredPlaces || [];
    const savedLookup = new Set();
    const storedSavedKey = 'where2go-saved-places';
    const isLoggedIn = Boolean(pageData.isLoggedIn);
    const mapsApiKey = pageData.mapsApiKey || '';
    const featuredPayloads = {};

    function applyTheme(theme) {
        const isDark = theme === 'dark';
        body.classList.toggle('dark-mode', isDark);
        body.classList.toggle('light-mode', !isDark);
        themeIcon.setAttribute('data-lucide', isDark ? 'moon-star' : 'sun-medium');
        themeLabel.textContent = isDark ? 'Dark mode' : 'Light mode';
        lucide.createIcons();
    }

    function showIntro() {
        const hasSeenIntro = sessionStorage.getItem('where2go-home-intro') === 'seen';

        if (hasSeenIntro) {
            introScreen.classList.add('is-hidden');
            return;
        }

        window.setTimeout(() => {
            introScreen.classList.add('is-hidden');
            sessionStorage.setItem('where2go-home-intro', 'seen');
        }, 1500);
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

    function updateVisitedCount() {
        if (visitedCount) {
            visitedCount.textContent = String(savedLookup.size);
        }
    }

    function updateSaveButton(placeId, isSaved) {
        const button = document.querySelector('[data-track-place="' + placeId + '"]');

        if (!button) {
            return;
        }

        if (!isLoggedIn) {
            button.classList.remove('is-saved');
            button.textContent = 'Login to save';
            return;
        }

        button.classList.toggle('is-saved', isSaved);
        button.textContent = isSaved ? 'Remove from profile' : 'Save to profile';
    }

    async function trackPlaceVisit(placeId, button) {
        if (!isLoggedIn) {
            window.location.href = 'login.php';
            return;
        }

        const isSaved = savedLookup.has(placeId);
        button.disabled = true;

        try {
            const response = await fetch('pages/track_visit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: new URLSearchParams({
                    action: isSaved ? 'remove' : 'save',
                    place_id: placeId,
                    source: 'catalog',
                    payload: JSON.stringify(featuredPayloads[placeId] || {}),
                }).toString(),
            });

            const payload = await response.json();

            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || 'The place could not be updated.');
            }

            syncSavedLookup(payload.visited || []);
            updateSaveButton(placeId, !isSaved);
            updateVisitedCount();
        } catch (error) {
            window.alert(error.message || 'The place could not be updated right now.');
        } finally {
            button.disabled = false;
        }
    }

    function goToSearch(query) {
        const term = (query || '').trim();
        const normalized = term === '' ? 'nightlife' : term;
        window.location.href = 'search.php?q=' + encodeURIComponent(normalized);
    }

    function setBackgroundMedia(element, photoUrl) {
        if (!element || !photoUrl) {
            return;
        }

        element.classList.add('has-photo');
        element.style.backgroundImage = 'url("' + photoUrl.replace(/"/g, '\\"') + '")';
        element.innerHTML = '';
    }

    function setAttribution(target, html) {
        if (target) {
            target.innerHTML = html || '';
        }
    }

    function updateFeaturedCard(placeId, updates) {
        const photoElement = document.querySelector('[data-card-photo="' + placeId + '"]');
        const ratingElement = document.querySelector('[data-card-rating="' + placeId + '"]');
        const addressElement = document.querySelector('[data-card-address="' + placeId + '"]');
        const descriptionElement = document.querySelector('[data-card-description="' + placeId + '"]');
        const attributionElement = document.querySelector('[data-card-attribution="' + placeId + '"]');

        if (updates.photoUrl) {
            setBackgroundMedia(photoElement, updates.photoUrl);
        }

        if (ratingElement && updates.ratingText) {
            ratingElement.innerHTML = '<i data-lucide="star" class="tiny-icon"></i>' + updates.ratingText;
        }

        if (addressElement && updates.address) {
            addressElement.innerHTML = '<i data-lucide="map-pin" class="tiny-icon"></i>' + updates.address;
        }

        if (descriptionElement && updates.description) {
            descriptionElement.textContent = updates.description;
        }

        setAttribution(attributionElement, updates.attribution || '');
        lucide.createIcons();
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

    function formatPriceLevel(level, fallbackValue) {
        if (typeof level === 'number' && level > 0) {
            return '$'.repeat(level);
        }

        return fallbackValue || '$$';
    }

    function refreshFeaturedPlacesWithGoogle(service) {
        featuredPlaces.forEach((place) => {
            service.findPlaceFromQuery(
                {
                    query: place.query,
                    fields: ['name', 'formatted_address', 'photos', 'price_level', 'rating', 'place_id'],
                },
                (results, status) => {
                    if (status !== window.google.maps.places.PlacesServiceStatus.OK || !results || !results[0]) {
                        return;
                    }

                    const result = results[0];
                    const photo = result.photos && result.photos[0] ? result.photos[0] : null;
                    const updates = {
                        address: result.formatted_address || (place.area + ', ' + place.city),
                        description: place.description,
                        ratingText: result.rating ? result.rating.toFixed(1) + ' rating' : 'Live rating',
                        photoUrl: photo && typeof photo.getUrl === 'function' ? photo.getUrl({ maxWidth: 680, maxHeight: 420 }) : '',
                        attribution: photo && Array.isArray(photo.html_attributions) ? photo.html_attributions.join(' ') : '',
                    };

                    updateFeaturedCard(place.id, updates);
                    featuredPayloads[place.id] = {
                        photo_url: updates.photoUrl,
                        photo_attribution: updates.attribution,
                        rating: result.rating ? result.rating.toFixed(1) : place.rating,
                        price_range: formatPriceLevel(result.price_level, place.price_range),
                        google_maps_url: result.place_id ? ('place.php?place_id=' + encodeURIComponent(result.place_id)) : '',
                    };
                }
            );
        });
    }

    async function initHomepageGoogle() {
        if (!mapsApiKey) {
            return;
        }

        try {
            await loadGoogleMapsApi(mapsApiKey);
            const service = new window.google.maps.places.PlacesService(document.createElement('div'));
            refreshFeaturedPlacesWithGoogle(service);
        } catch (error) {
            // The homepage still works without Google refresh data.
        }
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const nextTheme = body.classList.contains('dark-mode') ? 'light' : 'dark';
            localStorage.setItem('where2go-theme', nextTheme);
            applyTheme(nextTheme);
        });
    }

    saveButtons.forEach((button) => {
        button.addEventListener('click', () => {
            trackPlaceVisit(button.dataset.trackPlace, button);
        });
    });

    window.addEventListener('pageshow', () => {
        hydrateSavedLookup();
        saveButtons.forEach((button) => {
            updateSaveButton(button.dataset.trackPlace, savedLookup.has(button.dataset.trackPlace));
        });
        updateVisitedCount();
    });

    categoryCards.forEach((card) => {
        card.addEventListener('click', () => {
            goToSearch(card.dataset.categoryQuery || '');
        });
    });

    if (searchInput) {
        searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                goToSearch(searchInput.value);
            }
        });
    }

    if (searchButton) {
        searchButton.addEventListener('click', () => {
            goToSearch(searchInput ? searchInput.value : '');
        });
    }

    hydrateSavedLookup();
    saveButtons.forEach((button) => {
        updateSaveButton(button.dataset.trackPlace, savedLookup.has(button.dataset.trackPlace));
    });
    applyTheme(localStorage.getItem('where2go-theme') || 'light');
    showIntro();
    setupProfileMenus();
    updateVisitedCount();
    lucide.createIcons();
    initHomepageGoogle();
})();
