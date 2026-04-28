(function () {
    const pageData = window.where2goPageData || {};
    const body = document.body;
    const themeToggle = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const themeLabel = document.getElementById('theme-label');
    const profileMenus = Array.from(document.querySelectorAll('[data-profile-menu]'));
    const saveButtons = Array.from(document.querySelectorAll('[data-track-place]'));
    const sliderButtons = Array.from(document.querySelectorAll('[data-slider-target]'));
    const savedLookup = new Set();
    const storedSavedKey = 'where2go-saved-places';

    // Apply the saved light or dark theme and refresh the Lucide icons.

    function applyTheme(theme) {
        const isDark = theme === 'dark';
        body.classList.toggle('dark-mode', isDark);
        body.classList.toggle('light-mode', !isDark);

        if (themeIcon) {
            themeIcon.setAttribute('data-lucide', isDark ? 'moon-star' : 'sun-medium');
        }

        if (themeLabel) {
            themeLabel.textContent = isDark ? 'Dark mode' : 'Light mode';
        }

        lucide.createIcons();
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

    // Update the save button text and icon to match the current saved state.

    function updateSaveButton(button, isSaved) {
        button.classList.toggle('is-saved', isSaved);
        button.innerHTML = isSaved
            ? '<i data-lucide="bookmark-check"></i>Remove from profile'
            : '<i data-lucide="bookmark-plus"></i>Save to profile';
        lucide.createIcons();
    }

    // Send the save or remove request for a place card and refresh the button state.

    async function trackPlaceVisit(placeId, button) {
        const source = button.dataset.trackSource || 'catalog';
        const payload = button.dataset.trackPayload || '';
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

            const payload = await response.json();

            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || 'The place could not be updated.');
            }

            syncSavedLookup(payload.visited || []);
            updateSaveButton(button, !isSaved);
        } catch (error) {
            window.alert(error.message || 'The place could not be updated right now.');
        } finally {
            button.disabled = false;
        }
    }

    // Attach click handlers to every save button shown on account-related pages.

    function setupSaveButtons() {
        saveButtons.forEach((button) => {
            if (savedLookup.has(button.dataset.trackPlace)) {
                updateSaveButton(button, true);
            }

            button.addEventListener('click', () => {
                trackPlaceVisit(button.dataset.trackPlace, button);
            });
        });
    }

    // Turn the profile carousels into horizontally scrolling sliders.

    function setupSliders() {
        sliderButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const trackId = button.dataset.sliderTarget;
                const track = document.getElementById(trackId);

                if (!track) {
                    return;
                }

                const direction = button.dataset.sliderDirection === 'next' ? 1 : -1;
                const amount = Math.max(track.clientWidth * 0.8, 260);

                track.scrollBy({
                    left: amount * direction,
                    behavior: 'smooth',
                });
            });
        });
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const nextTheme = body.classList.contains('dark-mode') ? 'light' : 'dark';
            localStorage.setItem('where2go-theme', nextTheme);
            applyTheme(nextTheme);
        });
    }

    hydrateSavedLookup();
    applyTheme(localStorage.getItem('where2go-theme') || 'light');
    setupProfileMenus();
    setupSaveButtons();
    setupSliders();
    lucide.createIcons();
})();
