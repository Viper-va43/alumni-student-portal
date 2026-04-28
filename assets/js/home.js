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
    const savedLookup = new Set();
    const storedSavedKey = 'where2go-saved-places';
    const isLoggedIn = Boolean(pageData.isLoggedIn);

    // Apply the saved light or dark theme and refresh the Lucide icons.

    function applyTheme(theme) {
        const isDark = theme === 'dark';
        body.classList.toggle('dark-mode', isDark);
        body.classList.toggle('light-mode', !isDark);
        themeIcon.setAttribute('data-lucide', isDark ? 'moon-star' : 'sun-medium');
        themeLabel.textContent = isDark ? 'Dark mode' : 'Light mode';
        lucide.createIcons();
    }

    // Show the homepage intro only once per browser session.

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

    // Close any open profile dropdown before another menu interaction happens.

    function closeProfileMenus() {
        profileMenus.forEach((menu) => {
            const dropdown = menu.querySelector('[data-profile-dropdown]');

            if (dropdown) {
                dropdown.classList.remove('is-open');
            }
        });
    }

    // Wire the account avatar dropdown so it opens on click and closes outside the menu.

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

    // Read the locally cached saved-place ids for quick UI hydration.

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

    // Persist the latest saved-place ids so reloads can repaint buttons immediately.

    function writeStoredSavedIds() {
        if (!isLoggedIn) {
            sessionStorage.removeItem(storedSavedKey);
            return;
        }

        sessionStorage.setItem(storedSavedKey, JSON.stringify(Array.from(savedLookup)));
    }

    // Combine server-provided ids with session storage into one save-state lookup.

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

    // Replace the local save-state lookup after a successful save or remove request.

    function syncSavedLookup(nextVisitedIds) {
        savedLookup.clear();

        (Array.isArray(nextVisitedIds) ? nextVisitedIds : []).forEach((placeId) => {
            if (typeof placeId === 'string' && placeId.trim() !== '') {
                savedLookup.add(placeId);
            }
        });

        writeStoredSavedIds();
    }

    // Refresh the homepage stat that shows how many places are saved to the profile.

    function updateVisitedCount() {
        if (visitedCount) {
            visitedCount.textContent = String(savedLookup.size);
        }
    }

    // Update the homepage save button label according to login and saved state.

    function updateSaveButton(button, isSaved) {
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

    // Send the save or remove request for a homepage place card.

    async function trackPlaceVisit(button) {
        const placeId = button.dataset.trackPlace || '';
        const source = button.dataset.trackSource || 'catalog';
        const payload = button.dataset.trackPayload || '';

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
                    source: source,
                    payload: payload,
                }).toString(),
            });

            const payloadData = await response.json();

            if (!response.ok || !payloadData.ok) {
                throw new Error(payloadData.message || 'The place could not be updated.');
            }

            syncSavedLookup(payloadData.visited || []);
            updateSaveButton(button, !isSaved);
            updateVisitedCount();
        } catch (error) {
            window.alert(error.message || 'The place could not be updated right now.');
        } finally {
            button.disabled = false;
        }
    }

    // Redirect the visitor into the shared search page with an optional query.

    function goToSearch(query) {
        const normalized = (query || '').trim();
        window.location.href = normalized === '' ? 'search.php' : 'search.php?q=' + encodeURIComponent(normalized);
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
            trackPlaceVisit(button);
        });
    });

    window.addEventListener('pageshow', () => {
        hydrateSavedLookup();
        saveButtons.forEach((button) => {
            updateSaveButton(button, savedLookup.has(button.dataset.trackPlace));
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
        updateSaveButton(button, savedLookup.has(button.dataset.trackPlace));
    });
    applyTheme(localStorage.getItem('where2go-theme') || 'light');
    showIntro();
    setupProfileMenus();
    updateVisitedCount();
    lucide.createIcons();
})();
