(function () {
    const wheelCards = Array.from(document.querySelectorAll('[data-reward-wheel-card]'));
    const pendingCountNodes = Array.from(document.querySelectorAll('[data-pending-box-count]'));
    const palette = ['#f26c1c', '#ffb266', '#f7d589', '#ff8d4d', '#ffc97c', '#ffddb3'];

    function readJson(value) {
        if (!value) {
            return [];
        }

        try {
            const parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function buildWheelGradient(segments) {
        if (!segments.length) {
            return 'conic-gradient(from -90deg, #f26c1c 0deg 360deg)';
        }

        const slice = 360 / segments.length;
        const stops = segments.map((segment, index) => {
            const start = slice * index;
            const end = slice * (index + 1);
            const color = palette[index % palette.length];
            return `${color} ${start}deg ${end}deg`;
        });

        return `conic-gradient(from -90deg, ${stops.join(', ')})`;
    }

    function renderLegend(card, segments) {
        const legend = card.querySelector('[data-wheel-legend]');

        if (!legend) {
            return;
        }

        legend.innerHTML = '';

        segments.forEach((segment) => {
            const chip = document.createElement('span');
            chip.textContent = `${segment.label} (${Math.round((Number(segment.probability) || 0) * 100)}%)`;
            legend.appendChild(chip);
        });
    }

    function renderWheel(card, segments) {
        const wheel = card.querySelector('[data-reward-wheel]');

        if (!wheel) {
            return;
        }

        wheel.style.background = buildWheelGradient(segments);
        wheel.dataset.segmentCount = String(segments.length);
        card.dataset.segments = JSON.stringify(segments);
        renderLegend(card, segments);
    }

    function setStatus(card, message, statusClass) {
        const node = card.querySelector('[data-wheel-status]');

        if (!node) {
            return;
        }

        node.textContent = message || '';
        node.classList.remove('is-success', 'is-error');

        if (statusClass) {
            node.classList.add(statusClass);
        }
    }

    function updatePendingCount(delta) {
        pendingCountNodes.forEach((node) => {
            const current = Number(node.textContent || '0') || 0;
            node.textContent = String(Math.max(0, current + delta));
        });
    }

    function buildRewardHtml(reward) {
        if (!reward) {
            return '';
        }

        const expiresAt = reward.expires_at ? new Date(reward.expires_at.replace(' ', 'T')) : null;
        const expiryLabel = expiresAt && !Number.isNaN(expiresAt.getTime())
            ? expiresAt.toLocaleDateString()
            : reward.expires_at || '';

        return `You won ${reward.label}. Code: ${reward.voucher_code}${expiryLabel ? ` | Expires ${expiryLabel}` : ''}`;
    }

    async function spinCard(card) {
        const button = card.querySelector('[data-spin-reward-button]');
        const wheel = card.querySelector('[data-reward-wheel]');
        const boxId = Number(card.dataset.boxId || '0');
        const endpoint = card.dataset.spinEndpoint || 'spin-reward';

        if (!button || !wheel || button.disabled) {
            return;
        }

        button.disabled = true;
        setStatus(card, 'Opening your mystery box...', null);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json; charset=UTF-8',
                },
                body: JSON.stringify({
                    box_id: boxId,
                }),
            });
            const payload = await response.json();

            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || 'The reward wheel could not be opened.');
            }

            const segments = Array.isArray(payload.segments) ? payload.segments : readJson(card.dataset.segments);
            const segmentCount = Math.max(1, segments.length);
            const slice = 360 / segmentCount;
            const selectedIndex = Math.max(0, Number(payload.selected_index || 0));
            const fullTurns = 6;
            const targetRotation = (fullTurns * 360) - ((selectedIndex * slice) + (slice / 2));

            renderWheel(card, segments);

            requestAnimationFrame(() => {
                wheel.style.transform = `rotate(${targetRotation}deg)`;
            });

            window.setTimeout(() => {
                setStatus(card, buildRewardHtml(payload.reward), 'is-success');
                card.classList.add('is-opened');
                button.remove();
                updatePendingCount(-1);
            }, 5000);
        } catch (error) {
            button.disabled = false;
            setStatus(card, error.message || 'The reward wheel could not be opened right now.', 'is-error');
        }
    }

    wheelCards.forEach((card) => {
        const segments = readJson(card.dataset.segments);
        renderWheel(card, segments);

        const button = card.querySelector('[data-spin-reward-button]');

        if (button) {
            button.addEventListener('click', () => {
                spinCard(card);
            });
        }
    });
})();

