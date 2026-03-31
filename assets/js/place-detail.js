(function () {
    const pageData = window.where2goPlaceData || {};
    const body = document.body;
    const themeToggle = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const themeLabel = document.getElementById('theme-label');
    const profileMenus = Array.from(document.querySelectorAll('[data-profile-menu]'));
    const savePlaceButton = document.getElementById('save-place-button');
    const galleryMain = document.getElementById('gallery-main');
    const galleryThumbs = Array.from(document.querySelectorAll('#gallery-strip .gallery-thumb'));
    const savedLookup = new Set();
    const storedSavedKey = 'where2go-saved-places';
    const isLoggedIn = Boolean(pageData.isLoggedIn);
    let galleryIndex = 0;
    let galleryTimer = null;

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

    function stopGalleryAutoplay() {
        if (galleryTimer !== null) {
            window.clearInterval(galleryTimer);
            galleryTimer = null;
        }
    }

    function setGalleryImage(index) {
        if (!galleryMain || galleryThumbs.length === 0) {
            return;
        }

        const nextIndex = ((index % galleryThumbs.length) + galleryThumbs.length) % galleryThumbs.length;
        const nextThumb = galleryThumbs[nextIndex];
        const imageUrl = nextThumb.getAttribute('href') || '';

        galleryThumbs.forEach((thumb, thumbIndex) => {
            thumb.classList.toggle('is-active', thumbIndex === nextIndex);
        });

        if (imageUrl !== '') {
            galleryMain.innerHTML = '';
            galleryMain.style.backgroundImage = `url("${imageUrl.replace(/"/g, '\\"')}")`;
            galleryMain.style.backgroundSize = 'cover';
            galleryMain.style.backgroundPosition = 'center';
        }

        galleryIndex = nextIndex;
    }

    function startGalleryAutoplay() {
        stopGalleryAutoplay();

        if (galleryThumbs.length <= 1) {
            return;
        }

        galleryTimer = window.setInterval(() => {
            setGalleryImage(galleryIndex + 1);
        }, 2000);
    }

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

    hydrateSavedLookup();
    updateSaveButton();
    applyTheme(localStorage.getItem('where2go-theme') || 'light');
    setupProfileMenus();
    setupGalleryAutoplay();
    lucide.createIcons();
})();
