(function () {
    const pageData = window.where2goPlaceData || {};
    const body = document.body;
    const topbar = document.querySelector('.topbar');
    const themeToggle = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const themeLabel = document.getElementById('theme-label');
    const profileMenus = Array.from(document.querySelectorAll('[data-profile-menu]'));
    const savePlaceButton = document.getElementById('save-place-button');
    const galleryMain = document.getElementById('gallery-main');
    const galleryThumbs = Array.from(document.querySelectorAll('#gallery-strip .gallery-thumb'));
    const starRatings = Array.from(document.querySelectorAll('[data-star-rating]'));
    const savedLookup = new Set();
    const storedSavedKey = 'where2go-saved-places';
    const isLoggedIn = Boolean(pageData.isLoggedIn);
    let galleryIndex = 0;
    let galleryTimer = null;
    let topbarCompact = false;
    let topbarRaf = 0;
    let reservationAbortController = null;

    // Apply the saved light or dark theme and refresh the Lucide icons.

    function applyTheme(theme) {
        const isDark = theme === 'dark';
        body.classList.toggle('dark-mode', isDark);
        body.classList.toggle('light-mode', !isDark);
        themeIcon.setAttribute('data-lucide', isDark ? 'moon-star' : 'sun-medium');
        themeLabel.textContent = isDark ? 'Dark mode' : 'Light mode';
        lucide.createIcons();
    }

    function updateTopbarState() {
        if (!topbar) {
            return;
        }

        const scrollTop = window.scrollY || document.documentElement.scrollTop || 0;

        if (!topbarCompact && scrollTop >= 96) {
            topbarCompact = true;
        } else if (topbarCompact && scrollTop <= 20) {
            topbarCompact = false;
        }

        topbar.classList.toggle('is-compact', topbarCompact);
    }

    function requestTopbarStateUpdate() {
        if (topbarRaf) {
            return;
        }

        topbarRaf = window.requestAnimationFrame(() => {
            topbarRaf = 0;
            updateTopbarState();
        });
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

    // Update the detail-page save button according to login and saved state.

    function updateSaveButton() {
        if (!savePlaceButton) {
            return;
        }

        const placeId = savePlaceButton.dataset.trackPlace || '';
        const isSaved = placeId !== '' && savedLookup.has(placeId);

        if (!isLoggedIn) {
            savePlaceButton.classList.remove('is-saved');
            savePlaceButton.innerHTML = '<i data-lucide="bookmark-plus"></i>Login to save';
            lucide.createIcons();
            return;
        }

        savePlaceButton.classList.toggle('is-saved', isSaved);
        savePlaceButton.innerHTML = isSaved
            ? '<i data-lucide="bookmark-check"></i>Remove from profile'
            : '<i data-lucide="bookmark-plus"></i>Save to profile';
        lucide.createIcons();
    }

    // Send the save or remove request for the active place on the detail page.

    async function trackPlaceVisit() {
        if (!savePlaceButton) {
            return;
        }

        const placeId = savePlaceButton.dataset.trackPlace || '';
        const source = savePlaceButton.dataset.trackSource || 'catalog';
        const payload = savePlaceButton.dataset.trackPayload || '';

        if (!isLoggedIn) {
            window.location.href = 'login.php';
            return;
        }

        const isSaved = savedLookup.has(placeId);
        savePlaceButton.disabled = true;

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
                    csrf_token: pageData.csrfToken || '',
                }).toString(),
            });

            const payloadData = await response.json();

            if (!response.ok || !payloadData.ok) {
                throw new Error(payloadData.message || 'The place could not be updated.');
            }

            syncSavedLookup(payloadData.visited || []);
            updateSaveButton();
        } catch (error) {
            window.alert(error.message || 'The place could not be updated right now.');
        } finally {
            savePlaceButton.disabled = false;
        }
    }

    // Stop the automatic gallery rotation when the page is leaving or reset is needed.

    function stopGalleryAutoplay() {
        if (galleryTimer !== null) {
            window.clearInterval(galleryTimer);
            galleryTimer = null;
        }
    }

    function guessMediaType(url) {
        const cleanUrl = String(url || '').split('?')[0].split('#')[0].toLowerCase();

        return /\.(mp4|webm|ogg|mov|m4v)$/.test(cleanUrl) ? 'video' : 'image';
    }

    function renderGalleryMedia(url, type) {
        if (!galleryMain || !url) {
            return;
        }

        const mediaType = type || guessMediaType(url);
        galleryMain.innerHTML = '';
        galleryMain.style.backgroundImage = '';
        galleryMain.classList.add('has-media');

        if (mediaType === 'video') {
            const video = document.createElement('video');
            video.className = 'gallery-main-media';
            video.src = url;
            video.muted = true;
            video.loop = true;
            video.autoplay = true;
            video.playsInline = true;
            video.preload = 'metadata';
            galleryMain.appendChild(video);
            video.play().catch(() => {});
            return;
        }

        const image = document.createElement('img');
        image.className = 'gallery-main-media';
        image.src = url;
        image.alt = document.getElementById('detail-title')?.textContent || 'Place media';
        galleryMain.appendChild(image);
    }

    // Swap the main gallery media and keep the thumbnail strip in sync.

    function setGalleryImage(index) {
        if (!galleryMain || galleryThumbs.length === 0) {
            return;
        }

        const nextIndex = ((index % galleryThumbs.length) + galleryThumbs.length) % galleryThumbs.length;
        const nextThumb = galleryThumbs[nextIndex];
        const mediaUrl = nextThumb.dataset.mediaUrl || nextThumb.getAttribute('href') || '';
        const mediaType = nextThumb.dataset.mediaType || guessMediaType(mediaUrl);

        galleryThumbs.forEach((thumb, thumbIndex) => {
            thumb.classList.toggle('is-active', thumbIndex === nextIndex);
        });

        if (mediaUrl !== '') {
            renderGalleryMedia(mediaUrl, mediaType);
        }

        galleryIndex = nextIndex;
    }

    // Start the timed gallery rotation when multiple photos are available.

    function startGalleryAutoplay() {
        stopGalleryAutoplay();

        if (galleryThumbs.length <= 1) {
            return;
        }

        galleryTimer = window.setInterval(() => {
            setGalleryImage(galleryIndex + 1);
        }, 2000);
    }

    // Initialize the place gallery thumbnails, main image, and autoplay behavior.

    function setupGalleryAutoplay() {
        if (!galleryMain || galleryThumbs.length === 0) {
            return;
        }

        const activeIndex = galleryThumbs.findIndex((thumb) => thumb.classList.contains('is-active'));
        setGalleryImage(activeIndex >= 0 ? activeIndex : 0);

        galleryThumbs.forEach((thumb, index) => {
            thumb.addEventListener('click', (event) => {
                event.preventDefault();
                setGalleryImage(index);
                startGalleryAutoplay();
            });
        });

        startGalleryAutoplay();
        window.addEventListener('beforeunload', stopGalleryAutoplay);
    }

    function setStarRating(container, value) {
        const input = container.parentElement ? container.parentElement.querySelector('[data-star-input]') : null;
        const buttons = Array.from(container.querySelectorAll('[data-star-value]'));
        const nextValue = Math.max(1, Math.min(5, Number.parseInt(value, 10) || 5));

        if (input) {
            input.value = String(nextValue);
        }

        buttons.forEach((button) => {
            const buttonValue = Number.parseInt(button.dataset.starValue || '0', 10);
            button.classList.toggle('is-selected', buttonValue <= nextValue);
            button.setAttribute('aria-checked', buttonValue === nextValue ? 'true' : 'false');
        });
    }

    function setupStarRatings() {
        starRatings.forEach((container) => {
            const input = container.parentElement ? container.parentElement.querySelector('[data-star-input]') : null;
            const buttons = Array.from(container.querySelectorAll('[data-star-value]'));
            const initialValue = input ? input.value : '5';

            setStarRating(container, initialValue);

            buttons.forEach((button) => {
                button.addEventListener('click', () => {
                    setStarRating(container, button.dataset.starValue || '5');
                });

                button.addEventListener('keydown', (event) => {
                    const currentValue = input ? Number.parseInt(input.value || '5', 10) : 5;
                    let nextValue = currentValue;

                    if (event.key === 'ArrowLeft' || event.key === 'ArrowDown') {
                        nextValue = currentValue - 1;
                    } else if (event.key === 'ArrowRight' || event.key === 'ArrowUp') {
                        nextValue = currentValue + 1;
                    } else if (event.key === 'Home') {
                        nextValue = 1;
                    } else if (event.key === 'End') {
                        nextValue = 5;
                    } else {
                        return;
                    }

                    event.preventDefault();
                    setStarRating(container, nextValue);
                    const nextButton = container.querySelector(`[data-star-value="${Math.max(1, Math.min(5, nextValue))}"]`);

                    if (nextButton) {
                        nextButton.focus();
                    }
                });
            });
        });
    }

    function getReservationRequestUrl(form, submitter) {
        const action = form.getAttribute('action') || window.location.href;
        const requestUrl = new URL(action, window.location.href);
        const formData = new FormData(form);

        if (submitter && submitter.name) {
            formData.set(submitter.name, submitter.value || '');
        }

        formData.forEach((value, key) => {
            requestUrl.searchParams.set(key, value);
        });

        return requestUrl;
    }

    async function refreshReservationPanel(form, submitter) {
        const currentPanel = document.querySelector('.reservation-panel');

        if (!currentPanel) {
            return;
        }

        const requestUrl = getReservationRequestUrl(form, submitter);

        if (reservationAbortController) {
            reservationAbortController.abort();
        }

        const controller = new AbortController();
        reservationAbortController = controller;
        currentPanel.classList.add('is-loading');

        try {
            const response = await fetch(requestUrl.toString(), {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'fetch',
                },
                signal: controller.signal,
            });

            if (!response.ok) {
                throw new Error('Availability could not be refreshed.');
            }

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const nextPanel = doc.querySelector('.reservation-panel');

            if (!nextPanel) {
                throw new Error('Availability could not be refreshed.');
            }

            currentPanel.replaceWith(nextPanel);
            window.history.replaceState({}, '', requestUrl.toString());
            lucide.createIcons();
        } catch (error) {
            if (error.name !== 'AbortError') {
                currentPanel.classList.remove('is-loading');
                window.alert(error.message || 'Availability could not be refreshed right now.');
            }
        } finally {
            if (reservationAbortController === controller) {
                reservationAbortController = null;
            }
        }
    }

    function setupReservationAjax() {
        document.addEventListener('submit', (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement) || !form.matches('.reservation-controls, .availability-grid-form')) {
                return;
            }

            event.preventDefault();
            refreshReservationPanel(form, event.submitter || null);
        });
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const nextTheme = body.classList.contains('dark-mode') ? 'light' : 'dark';
            localStorage.setItem('where2go-theme', nextTheme);
            applyTheme(nextTheme);
        });
    }

    if (savePlaceButton) {
        savePlaceButton.addEventListener('click', () => {
            trackPlaceVisit();
        });
    }

    window.addEventListener('scroll', requestTopbarStateUpdate, { passive: true });

    hydrateSavedLookup();
    updateSaveButton();
    applyTheme(localStorage.getItem('where2go-theme') || 'dark');
    setupProfileMenus();
    setupGalleryAutoplay();
    setupStarRatings();
    setupReservationAjax();
    updateTopbarState();
    lucide.createIcons();
})();
