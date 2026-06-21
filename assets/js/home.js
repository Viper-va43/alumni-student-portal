(function () {
    const pageData = window.where2goHomeData || {};
    const body = document.body;
    const topbar = document.querySelector('.topbar');
    const themeToggle = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const themeLabel = document.getElementById('theme-label');
    const introScreen = document.getElementById('intro-screen');
    const introLogos = Array.from(document.querySelectorAll('.intro-logo'));
    const searchInput = document.getElementById('search-input');
    const searchButton = document.getElementById('search-button');
    const saveButtons = Array.from(document.querySelectorAll('[data-track-place]'));
    const visitedCount = document.getElementById('visited-count');
    const profileMenus = Array.from(document.querySelectorAll('[data-profile-menu]'));
    const categoryCards = Array.from(document.querySelectorAll('[data-category-query], [data-category-catalog]'));
    const uiSelects = Array.from(document.querySelectorAll('select[data-ui-select]'));
    const catalogSelects = Array.from(document.querySelectorAll('[data-catalog-target]'));
    const chooseForm = document.querySelector('[data-choose-form]');
    const chooseResult = document.querySelector('[data-choose-result]');
    const choosePlaces = Array.isArray(pageData.choosePlaces) ? pageData.choosePlaces : [];
    const savedLookup = new Set();
    const storedSavedKey = 'where2go-saved-places';
    const isLoggedIn = Boolean(pageData.isLoggedIn);
    let topbarCompact = false;
    let topbarRaf = 0;
    const locationAliases = {
        '5th settlement': ['5th settlement', 'fifth settlement', 'new cairo'],
        '1st settlement': ['1st settlement', 'first settlement', 'new cairo'],
        'al rehab': ['al rehab', 'rehab', 'new cairo'],
        'el shorouk': ['el shorouk', 'shorouk'],
        'new cairo': ['new cairo', 'fifth settlement', '5th settlement', 'first settlement', '1st settlement'],
        'downtown': ['downtown', 'abdeen', 'azbakeya', 'cairo'],
        'islamic cairo': ['islamic cairo', 'el mosky', 'al wayli', 'cairo'],
        'coptic cairo': ['coptic cairo', 'old cairo', 'cairo'],
        'cairo': ['cairo'],
    };
    const activityMatchers = {
        restaurant: /restaurant|dining|food|grill|antar|meals?/,
        restaurants: /restaurant|dining|food|grill|antar|meals?/,
        cafe: /cafe|coffee|relaxed/,
        cafes: /cafe|coffee|relaxed/,
        activity: /activity|active|kayak|paintball|archery|waves|target|outdoors|gaming/,
        activities: /activity|active|kayak|paintball|archery|waves|target|outdoors|gaming/,
        entertainment: /entertainment|fun|cinema|mall|gaming|club|waterway|drive|point/,
        nightlife: /nightlife|rooftop|lounge|evening/,
        heritage: /heritage|museum|markets?|outdoors|viewpoint|pyramids?|sphinx|citadel|coptic|palace|tower|park|historic/,
    };
    const activityCatalogSlugs = {
        restaurant: 'restaurant',
        restaurants: 'restaurant',
        cafe: 'cafe',
        cafes: 'cafe',
        activity: 'activity',
        activities: 'activity',
        entertainment: 'entertainment',
        nightlife: 'nightlife',
        heritage: 'heritage',
    };
    const eventMatchers = {
        food: /restaurant|dining|food|grill|cafe|meals?/,
        friends: /group|mall|entertainment|activity|nightlife|kayak|paintball|archery|club|gaming|hangout/,
        family: /park|museum|heritage|mall|citadel|pyramids?|sphinx|coptic|palace|outdoors/,
        date: /rooftop|nile|view|sunset|fine dining|zamalek|palace|tower|garden|polished/,
        active: /kayak|paintball|archery|activity|outdoors|park|walking|active/,
        culture: /museum|heritage|markets?|citadel|coptic|pyramids?|sphinx|palace|old cairo|historic|artifacts/,
        views: /tower|view|views|rooftop|nile|park|skyline|panoramic/,
    };

    // Apply the saved light or dark theme and refresh the Lucide icons.

    function applyTheme(theme) {
        const isDark = theme === 'dark';
        body.classList.toggle('dark-mode', isDark);
        body.classList.toggle('light-mode', !isDark);
        themeIcon.setAttribute('data-lucide', isDark ? 'moon-star' : 'sun-medium');
        themeLabel.textContent = isDark ? 'Dark mode' : 'Light mode';
        introLogos.forEach((logo) => {
            logo.src = isDark
                ? 'assets/images/where2go_transparent_clean.png'
                : 'assets/images/where2go_transparent_white.png';
        });
        lucide.createIcons();
    }

    // Shrink the header once the customer starts moving down the page.

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
        }, 550);
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

    function getCatalogChooserValue(target) {
        const select = catalogSelects.find((item) => item.dataset.catalogTarget === target);

        return select ? String(select.value || '').trim() : '';
    }

    function goToSearch(query, catalog, filters = {}) {
        const normalized = (query || '').trim();
        const normalizedCatalog = (catalog || '').trim();
        const params = new URLSearchParams();

        if (normalized !== '') {
            params.set('q', normalized);
        }

        if (normalizedCatalog !== '') {
            params.set('catalog', normalizedCatalog);
        }

        ['event', 'location', 'price'].forEach((key) => {
            const value = String(filters[key] || '').trim();

            if (value !== '') {
                params.set(key, value);
            }
        });

        const paramString = params.toString();
        window.location.href = paramString === '' ? 'search.php' : 'search.php?' + paramString;
    }

    function goToSearchFromHero() {
        const selectedActivity = getCatalogChooserValue('activity');
        const selectedEvent = getCatalogChooserValue('event');
        const selectedLocation = getCatalogChooserValue('location');
        const selectedPrice = getCatalogChooserValue('price');

        goToSearch(searchInput ? searchInput.value : '', selectedActivity, {
            event: selectedEvent,
            location: selectedLocation,
            price: selectedPrice,
        });
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        }[char]));
    }

    function normalizeText(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/&/g, ' and ')
            .replace(/[^a-z0-9]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function getLocationNeedles(location) {
        const normalized = normalizeText(location);
        return locationAliases[normalized] || [normalized];
    }

    function getPlaceSearchText(place) {
        return normalizeText([
            place.name,
            place.category,
            place.catalog,
            place.catalog_label,
            place.area,
            place.city,
            place.address,
            place.description,
            place.search_blob,
        ].join(' '));
    }

    function getPlacePriceBand(place) {
        const priceRange = String(place.price_range || '').toLowerCase();
        const dollarCount = (priceRange.match(/\$/g) || []).length;

        if (dollarCount <= 0) {
            return {
                label: priceRange.includes('offer') ? 'Offer price' : 'See details',
                min: 0,
                max: Number.POSITIVE_INFINITY,
                unknown: true,
            };
        }

        if (dollarCount === 1) {
            return { label: '50-100 EGP', min: 50, max: 100, unknown: false };
        }

        if (dollarCount === 2) {
            return { label: '100-200 EGP', min: 100, max: 200, unknown: false };
        }

        return { label: '200+ EGP', min: 200, max: Number.POSITIVE_INFINITY, unknown: false };
    }

    function placeMatchesLocation(place, location) {
        const searchable = getPlaceSearchText(place);

        return getLocationNeedles(location).some((needle) => searchable.includes(normalizeText(needle)));
    }

    function placeMatchesPattern(place, selectedValue, matchers) {
        const normalizedValue = normalizeText(selectedValue);

        if (!normalizedValue) {
            return true;
        }

        const searchable = getPlaceSearchText(place);
        const matcher = matchers[normalizedValue];

        if (matchers === activityMatchers) {
            const expectedCatalog = activityCatalogSlugs[normalizedValue] || normalizedValue;
            const placeCatalog = normalizeText(place.catalog || '');

            if (expectedCatalog && placeCatalog === expectedCatalog) {
                return true;
            }
        }

        return matcher ? matcher.test(searchable) : searchable.includes(normalizedValue);
    }

    function placeFitsParty(place, partySize) {
        const party = Number.isFinite(partySize) ? partySize : 2;
        const searchable = normalizeText([
            place.name,
            place.category,
            place.catalog,
            place.catalog_label,
            place.description,
            place.area,
            place.address,
        ].join(' '));

        if (party >= 6) {
            return /restaurant|entertainment|nightlife|mall|group|dining|club|waterway/.test(searchable);
        }

        if (party <= 2) {
            return /cafe|relaxed|coffee|walk|mall|restaurant|dining/.test(searchable);
        }

        return true;
    }

    function buildChoiceScore(place, answers) {
        const priceBand = getPlacePriceBand(place);
        const withinBudget = !answers.budget || priceBand.unknown || priceBand.min <= answers.budget;
        const locationMatched = placeMatchesLocation(place, answers.location);
        const activityMatched = placeMatchesPattern(place, answers.activity, activityMatchers);
        const eventMatched = placeMatchesPattern(place, answers.event, eventMatchers);
        const partyFit = placeFitsParty(place, answers.partySize);
        let score = 0;

        if (locationMatched) {
            score += 8;
        }

        if (withinBudget) {
            score += 5;
        } else if (answers.budget) {
            score -= 4;
        }

        if (answers.activity && activityMatched) {
            score += 6;
        } else if (answers.activity) {
            score -= 5;
        }

        if (answers.event && eventMatched) {
            score += 6;
        } else if (answers.event) {
            score -= 5;
        }

        if (partyFit) {
            score += 2;
        }

        if (place.has_offer) {
            score += 1;
        }

        if (place.source === 'business') {
            score += 1;
        }

        return {
            place,
            priceBand,
            withinBudget,
            locationMatched,
            activityMatched,
            eventMatched,
            partyFit,
            score,
        };
    }

    function pickRandomPlace(answers) {
        let scoredPlaces = choosePlaces
            .filter((place) => place && String(place.name || '').trim() !== '')
            .map((place) => buildChoiceScore(place, answers));

        const budgetMatches = answers.budget
            ? scoredPlaces.filter((item) => item.withinBudget)
            : scoredPlaces;

        if (budgetMatches.length > 0) {
            scoredPlaces = budgetMatches;
        }

        const locationMatches = scoredPlaces.filter((item) => item.locationMatched);

        if (locationMatches.length > 0) {
            scoredPlaces = locationMatches;
        }

        if (answers.activity) {
            const activityMatches = scoredPlaces.filter((item) => item.activityMatched);

            if (activityMatches.length > 0) {
                scoredPlaces = activityMatches;
            }
        }

        if (answers.event) {
            const eventMatches = scoredPlaces.filter((item) => item.eventMatched);

            if (eventMatches.length > 0) {
                scoredPlaces = eventMatches;
            }
        }

        if (scoredPlaces.length === 0) {
            return null;
        }

        const bestScore = Math.max(...scoredPlaces.map((item) => item.score));
        let topChoices = scoredPlaces.filter((item) => item.score >= bestScore - 2);
        const previousPick = sessionStorage.getItem('where2go-last-random-pick') || '';

        if (topChoices.length > 1) {
            topChoices = topChoices.filter((item) => String(item.place.id || '') !== previousPick);
        }

        const picked = topChoices[Math.floor(Math.random() * topChoices.length)];
        sessionStorage.setItem('where2go-last-random-pick', String(picked.place.id || ''));

        return picked;
    }

    function renderChosenPlace(choice, answers) {
        if (!chooseResult) {
            return;
        }

        if (!choice) {
            chooseResult.hidden = false;
            chooseResult.classList.remove('is-empty');
            chooseResult.innerHTML = '<div class="choose-placeholder"><i data-lucide="map-off"></i><span>No matching place is ready yet.</span></div>';
            lucide.createIcons();
            return;
        }

        const place = choice.place;
        const reasons = [];

        chooseResult.hidden = false;
        chooseResult.classList.remove('is-empty');

        reasons.push(choice.locationMatched ? `near ${answers.location}` : 'from the wider Cairo catalog');

        if (answers.budget) {
            reasons.push(choice.withinBudget ? `${choice.priceBand.label} fits your budget` : `${choice.priceBand.label} is closest`);
        }

        if (answers.activity) {
            reasons.push(choice.activityMatched ? `${answers.activity} match` : 'closest activity match');
        }

        if (answers.event) {
            reasons.push(choice.eventMatched ? `${answers.event} plan` : 'closest event match');
        }

        reasons.push(choice.partyFit ? `good for ${answers.partySize}` : `closest fit for ${answers.partySize}`);

        if (place.has_offer) {
            reasons.push('has an offer');
        }

        chooseResult.innerHTML = `
            <div class="choose-picked">
                <div class="choose-picked-top">
                    <span class="choose-picked-icon"><i data-lucide="${escapeHtml(place.icon || 'map-pinned')}"></i></span>
                    <div>
                        <h3>${escapeHtml(place.name || 'Where2Go place')}</h3>
                        <p>${escapeHtml(place.catalog_label || place.category || 'Place')} - ${escapeHtml(place.address || place.area || 'Cairo')}</p>
                    </div>
                </div>
                <div class="choose-picked-meta">
                    ${reasons.map((reason) => `<span class="place-chip">${escapeHtml(reason)}</span>`).join('')}
                </div>
                <p>${escapeHtml(place.description || 'Saved in the Where2Go catalog.')}</p>
                <a class="choose-picked-link" href="${escapeHtml(place.detail_url || 'search.php')}">
                    Open details
                    <i data-lucide="arrow-up-right"></i>
                </a>
            </div>
        `;
        lucide.createIcons();
    }

    function closeUiSelects(exceptRoot) {
        document.querySelectorAll('[data-ui-select-root].is-open').forEach((root) => {
            if (root !== exceptRoot) {
                root.classList.remove('is-open');
                root.querySelector('[data-ui-select-button]')?.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function getVisibleSelectButton(select) {
        const root = select?.nextElementSibling;

        if (!root || !root.matches('[data-ui-select-root]')) {
            return null;
        }

        return root.querySelector('[data-ui-select-button]');
    }

    function setupUiSelects() {
        uiSelects.forEach((select) => {
            if (select.dataset.uiSelectReady === 'true') {
                return;
            }

            select.dataset.uiSelectReady = 'true';
            select.classList.add('ui-select-native');

            const root = document.createElement('div');
            const button = document.createElement('button');
            const iconName = select.dataset.uiSelectIcon || 'list-filter';
            const label = document.createElement('span');
            const menu = document.createElement('div');
            const options = Array.from(select.options);

            root.className = 'ui-select';
            root.dataset.uiSelectRoot = 'true';

            button.type = 'button';
            button.className = 'ui-select-button';
            button.dataset.uiSelectButton = 'true';
            button.setAttribute('aria-haspopup', 'listbox');
            button.setAttribute('aria-expanded', 'false');
            button.innerHTML = `<i data-lucide="${iconName}"></i><span data-ui-select-label></span><i data-lucide="chevron-down" class="ui-select-chevron"></i>`;

            menu.className = 'ui-select-menu';
            menu.setAttribute('role', 'listbox');

            root.append(button, menu);
            select.after(root);

            const labelSlot = button.querySelector('[data-ui-select-label]') || label;

            function syncFromSelect() {
                const selectedOption = select.options[select.selectedIndex] || options[0];
                const selectedValue = selectedOption ? selectedOption.value : '';

                labelSlot.textContent = selectedOption ? selectedOption.textContent : 'Choose';

                menu.querySelectorAll('[data-ui-select-option]').forEach((optionButton) => {
                    const isSelected = optionButton.dataset.value === selectedValue;
                    optionButton.classList.toggle('is-selected', isSelected);
                    optionButton.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                });
            }

            options.forEach((option) => {
                const optionButton = document.createElement('button');
                optionButton.type = 'button';
                optionButton.className = 'ui-select-option';
                optionButton.dataset.uiSelectOption = 'true';
                optionButton.dataset.value = option.value;
                optionButton.textContent = option.textContent;
                optionButton.setAttribute('role', 'option');

                if (option.disabled) {
                    optionButton.disabled = true;
                }

                optionButton.addEventListener('click', () => {
                    select.value = option.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    closeUiSelects();
                    button.focus();
                });

                menu.append(optionButton);
            });

            button.addEventListener('click', (event) => {
                event.stopPropagation();
                const isOpen = root.classList.contains('is-open');

                closeUiSelects(root);
                root.classList.toggle('is-open', !isOpen);
                button.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
            });

            button.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeUiSelects();
                    button.focus();
                }
            });

            select.addEventListener('change', syncFromSelect);
            syncFromSelect();
        });

        document.addEventListener('click', () => closeUiSelects());
    }

    function setupChooser() {
        if (!chooseForm) {
            return;
        }

        chooseForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const partyInput = Number.parseInt(chooseForm.querySelector('[data-party-size]')?.value || '2', 10);
            const partySize = Number.isFinite(partyInput) ? Math.max(1, partyInput) : 2;
            const budgetValue = Number.parseFloat(chooseForm.querySelector('[data-budget]')?.value || '0');
            const location = chooseForm.querySelector('[data-current-location]')?.value || 'Cairo';
            const activity = chooseForm.querySelector('[data-current-activity]')?.value || '';
            const eventType = chooseForm.querySelector('[data-current-event]')?.value || '';
            const answers = {
                partySize,
                budget: Number.isFinite(budgetValue) && budgetValue > 0 ? budgetValue : 0,
                location,
                activity,
                event: eventType,
            };

            renderChosenPlace(pickRandomPlace(answers), answers);
        });
    }

    function setupCatalogChooserSelects() {
        if (!chooseForm || catalogSelects.length === 0) {
            return;
        }

        const fieldSelectors = {
            event: '[data-current-event]',
            location: '[data-current-location]',
            price: '[data-budget]',
            activity: '[data-current-activity]',
        };

        catalogSelects.forEach((select) => {
            select.addEventListener('change', () => {
                const fieldName = select.dataset.catalogTarget || '';
                const selector = fieldSelectors[fieldName];
                const field = selector ? chooseForm.querySelector(selector) : null;
                const fieldWrap = field ? field.closest('.chooser-field') : null;
                const visibleFieldButton = getVisibleSelectButton(field);

                if (!field) {
                    return;
                }

                field.value = select.value || '';
                field.dispatchEvent(new Event('change', { bubbles: true }));
                (visibleFieldButton || field).focus();

                if (fieldWrap) {
                    fieldWrap.classList.add('is-highlighted');
                    window.setTimeout(() => fieldWrap.classList.remove('is-highlighted'), 900);
                }
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
            goToSearch(card.dataset.categoryQuery || '', card.dataset.categoryCatalog || '');
        });
    });

    if (searchInput) {
        searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                goToSearchFromHero();
            }
        });
    }

    if (searchButton) {
        searchButton.addEventListener('click', () => {
            goToSearchFromHero();
        });
    }

    window.addEventListener('scroll', requestTopbarStateUpdate, { passive: true });

    hydrateSavedLookup();
    saveButtons.forEach((button) => {
        updateSaveButton(button, savedLookup.has(button.dataset.trackPlace));
    });
    applyTheme(localStorage.getItem('where2go-theme') || 'dark');
    showIntro();
    setupProfileMenus();
    setupUiSelects();
    setupChooser();
    setupCatalogChooserSelects();
    updateTopbarState();
    updateVisitedCount();
    lucide.createIcons();
})();
