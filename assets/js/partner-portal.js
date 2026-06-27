(function () {
    const typeInputs = Array.from(document.querySelectorAll('input[name="type"]'));
    const customTypeWrap = document.querySelector('[data-custom-type-wrap]');
    const locationList = document.querySelector('[data-location-list]');
    const offerList = document.querySelector('[data-offer-list]');
    const photoList = document.querySelector('[data-photo-list]');
    const locationTemplate = document.getElementById('location-template');
    const offerTemplate = document.getElementById('offer-template');
    let draggedPhotoRow = null;

    // Show the custom business-type field only when the partner selects Other.

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

    // Disable the time inputs for a day when that location is marked as closed.

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

    // Apply the closed or open state rules to every hours row in the given scope.

    function hydrateHoursRows(scope) {
        const rows = Array.from(scope.querySelectorAll('[data-hours-row]'));
        rows.forEach((row) => updateHoursRow(row));
    }

    // Find the next numeric index for dynamically added location or offer cards.

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

    // Clone a hidden HTML template and replace the placeholder index values.

    function createNodeFromTemplate(template, index) {
        if (!template) {
            return null;
        }

        const html = template.innerHTML.replace(/__INDEX__/g, String(index));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();

        return wrapper.firstElementChild;
    }

    // Keep photo labels and move buttons aligned with the current visual order.

    function updatePhotoRows() {
        if (!photoList) {
            return;
        }

        const rows = Array.from(photoList.querySelectorAll('[data-photo-row]'));

        rows.forEach((row, index) => {
            const label = row.querySelector('label');
            const input = row.querySelector('input[name="photo_urls[]"]');
            const dragHandle = row.querySelector('[data-drag-handle]');
            const moveUp = row.querySelector('[data-move-photo="up"]');
            const moveDown = row.querySelector('[data-move-photo="down"]');
            const position = index + 1;

            if (input) {
                input.id = 'photo_' + index;
            }

            if (label) {
                label.setAttribute('for', 'photo_' + index);
                label.textContent = 'Photo URL ' + position;
            }

            if (dragHandle) {
                dragHandle.setAttribute('aria-label', 'Drag photo ' + position);
            }

            if (moveUp) {
                moveUp.disabled = index === 0;
                moveUp.setAttribute('aria-label', 'Move photo ' + position + ' up');
            }

            if (moveDown) {
                moveDown.disabled = index === rows.length - 1;
                moveDown.setAttribute('aria-label', 'Move photo ' + position + ' down');
            }
        });
    }

    function clearPhotoDropState() {
        if (!photoList) {
            return;
        }

        Array.from(photoList.querySelectorAll('[data-photo-row]')).forEach((row) => {
            row.classList.remove('is-drop-before', 'is-drop-after');
        });
    }

    function placeDraggedPhoto(targetRow, clientY) {
        if (!photoList || !draggedPhotoRow || !targetRow || draggedPhotoRow === targetRow) {
            return;
        }

        const rect = targetRow.getBoundingClientRect();
        const insertAfter = clientY > rect.top + rect.height / 2;

        clearPhotoDropState();
        targetRow.classList.add(insertAfter ? 'is-drop-after' : 'is-drop-before');

        if (insertAfter) {
            photoList.insertBefore(draggedPhotoRow, targetRow.nextElementSibling);
        } else {
            photoList.insertBefore(draggedPhotoRow, targetRow);
        }
    }

    // Hide remove buttons when only one location or offer card remains.

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

    // Copy one opening and closing schedule across all days in a location card.

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

    // Render each partner location QR code when the dashboard exposes a QR container.

    function renderLocationQrs() {
        if (!window.QRCode) {
            return;
        }

        const qrNodes = Array.from(document.querySelectorAll('[data-location-qr]'));

        qrNodes.forEach((node) => {
            const qrUrl = node.getAttribute('data-qr-url') || '';

            if (!qrUrl || node.getAttribute('data-qr-ready') === '1') {
                return;
            }

            node.innerHTML = '';

            QRCode.toCanvas(qrUrl, {
                width: 172,
                margin: 1,
                color: {
                    dark: '#23160c',
                    light: '#0000',
                },
            }, (error, canvas) => {
                if (error || !canvas) {
                    node.textContent = 'QR preview unavailable';
                    return;
                }

                node.appendChild(canvas);
                node.setAttribute('data-qr-ready', '1');
            });
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
        const movePhotoButton = event.target.closest('[data-move-photo]');

        if (movePhotoButton && photoList) {
            event.preventDefault();
            const row = movePhotoButton.closest('[data-photo-row]');
            const direction = movePhotoButton.getAttribute('data-move-photo');

            if (row && direction === 'up' && row.previousElementSibling) {
                photoList.insertBefore(row, row.previousElementSibling);
                updatePhotoRows();
            }

            if (row && direction === 'down' && row.nextElementSibling) {
                photoList.insertBefore(row.nextElementSibling, row);
                updatePhotoRows();
            }

            return;
        }

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

    document.addEventListener('dragstart', (event) => {
        const row = event.target.closest('[data-photo-row]');

        if (!row || !photoList || !photoList.contains(row)) {
            return;
        }

        draggedPhotoRow = row;
        row.classList.add('is-dragging');

        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', '');
        }
    });

    document.addEventListener('dragover', (event) => {
        const row = event.target.closest('[data-photo-row]');

        if (!row || !draggedPhotoRow || !photoList || !photoList.contains(row)) {
            return;
        }

        event.preventDefault();
        placeDraggedPhoto(row, event.clientY);
    });

    document.addEventListener('drop', (event) => {
        if (!draggedPhotoRow) {
            return;
        }

        event.preventDefault();
        clearPhotoDropState();
        updatePhotoRows();
    });

    document.addEventListener('dragend', () => {
        if (draggedPhotoRow) {
            draggedPhotoRow.classList.remove('is-dragging');
        }

        draggedPhotoRow = null;
        clearPhotoDropState();
        updatePhotoRows();
    });

    hydrateHoursRows(document);
    updateCustomTypeVisibility();
    updateRemoveButtons();
    updatePhotoRows();
    lucide.createIcons();
    renderLocationQrs();
})();
