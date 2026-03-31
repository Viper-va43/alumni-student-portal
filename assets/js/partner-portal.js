(function () {
    const typeInputs = Array.from(document.querySelectorAll('input[name="type"]'));
    const customTypeWrap = document.querySelector('[data-custom-type-wrap]');
    const locationList = document.querySelector('[data-location-list]');
    const offerList = document.querySelector('[data-offer-list]');
    const locationTemplate = document.getElementById('location-template');
    const offerTemplate = document.getElementById('offer-template');

    function updateCustomTypeVisibility() {
        if (!customTypeWrap || typeInputs.length === 0) {
            return;
        }

        const activeType = typeInputs.find((input) => input.checked);
        const isOther = activeType && activeType.value === 'other';
        customTypeWrap.hidden = !isOther;

        const input = customTypeWrap.querySelector('input');

        if (input) {
            input.disabled = !isOther;

            if (!isOther) {
                input.value = '';
            }
        }
    }

    function updateHoursRow(row) {
        const closedInput = row.querySelector('[data-hours-closed]');
        const timeInputs = Array.from(row.querySelectorAll('[data-hours-time]'));

        if (!closedInput) {
            return;
        }

        const isClosed = closedInput.checked;
        timeInputs.forEach((input) => {
            input.disabled = isClosed;

            if (isClosed) {
                input.value = '';
            }
        });
    }

    function hydrateHoursRows(scope) {
        const rows = Array.from(scope.querySelectorAll('[data-hours-row]'));
        rows.forEach((row) => updateHoursRow(row));
    }

    function nextDynamicIndex(container) {
        let maxIndex = -1;
        Array.from(container.querySelectorAll('[data-dynamic-index]')).forEach((node) => {
            const value = Number(node.getAttribute('data-dynamic-index'));

            if (!Number.isNaN(value)) {
                maxIndex = Math.max(maxIndex, value);
            }
        });

        return maxIndex + 1;
    }

    function createNodeFromTemplate(template, index) {
        if (!template) {
            return null;
        }

        const html = template.innerHTML.replace(/__INDEX__/g, String(index));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();

        return wrapper.firstElementChild;
    }

    function updateRemoveButtons() {
        const locationCards = locationList ? Array.from(locationList.querySelectorAll('[data-location-card]')) : [];
        const offerCards = offerList ? Array.from(offerList.querySelectorAll('[data-offer-card]')) : [];

        locationCards.forEach((card) => {
            const button = card.querySelector('[data-remove-location]');

            if (button) {
                button.hidden = locationCards.length <= 1;
            }
        });

        offerCards.forEach((card) => {
            const button = card.querySelector('[data-remove-offer]');

            if (button) {
                button.hidden = offerCards.length <= 1;
            }
        });
    }

    function applyHoursToLocation(card) {
        const openInput = card.querySelector('[data-apply-open]');
        const closeInput = card.querySelector('[data-apply-close]');

        if (!openInput || !closeInput) {
            return;
        }

        const openValue = openInput.value;
        const closeValue = closeInput.value;

        Array.from(card.querySelectorAll('[data-hours-row]')).forEach((row) => {
            const closedInput = row.querySelector('[data-hours-closed]');
            const timeInputs = Array.from(row.querySelectorAll('[data-hours-time]'));

            if (closedInput) {
                closedInput.checked = false;
            }

            if (timeInputs[0]) {
                timeInputs[0].value = openValue;
            }

            if (timeInputs[1]) {
                timeInputs[1].value = closeValue;
            }

            updateHoursRow(row);
        });
    }

    typeInputs.forEach((input) => {
        input.addEventListener('change', updateCustomTypeVisibility);
    });

    document.addEventListener('change', (event) => {
        if (event.target.matches('[data-hours-closed]')) {
            const row = event.target.closest('[data-hours-row]');

            if (row) {
                updateHoursRow(row);
            }
        }
    });

    document.addEventListener('click', (event) => {
        const addLocationButton = event.target.closest('[data-add-location]');
        const removeLocationButton = event.target.closest('[data-remove-location]');
        const addOfferButton = event.target.closest('[data-add-offer]');
        const removeOfferButton = event.target.closest('[data-remove-offer]');
        const applyHoursButton = event.target.closest('[data-apply-hours]');

        if (addLocationButton && locationList) {
            event.preventDefault();
            const index = nextDynamicIndex(locationList);
            const node = createNodeFromTemplate(locationTemplate, index);

            if (node) {
                locationList.appendChild(node);
                hydrateHoursRows(node);
                updateRemoveButtons();
                lucide.createIcons();
            }

            return;
        }

        if (removeLocationButton && locationList) {
            event.preventDefault();
            const cards = Array.from(locationList.querySelectorAll('[data-location-card]'));

            if (cards.length > 1) {
                const card = removeLocationButton.closest('[data-location-card]');

                if (card) {
                    card.remove();
                    updateRemoveButtons();
                }
            }

            return;
        }

        if (addOfferButton && offerList) {
            event.preventDefault();
            const index = nextDynamicIndex(offerList);
            const node = createNodeFromTemplate(offerTemplate, index);

            if (node) {
                offerList.appendChild(node);
                updateRemoveButtons();
                lucide.createIcons();
            }

            return;
        }

        if (removeOfferButton && offerList) {
            event.preventDefault();
            const cards = Array.from(offerList.querySelectorAll('[data-offer-card]'));

            if (cards.length > 1) {
                const card = removeOfferButton.closest('[data-offer-card]');

                if (card) {
                    card.remove();
                    updateRemoveButtons();
                }
            }

            return;
        }

        if (applyHoursButton) {
            event.preventDefault();
            const card = applyHoursButton.closest('[data-location-card]');

            if (card) {
                applyHoursToLocation(card);
            }
        }
    });

    hydrateHoursRows(document);
    updateCustomTypeVisibility();
    updateRemoveButtons();
    lucide.createIcons();
})();
