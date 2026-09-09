// The list of nights this browser holds, on the watch page. Fills the table
// from the night store, closes any night whose tab died, and, for a signed-in
// visitor, offers to save nights to the account.
import { claimNight } from './claimNight.js';
import { claimSummary } from './claimLogic.js';
import { finalizeInterruptedNight, nightRows, VOLATILE_STORE_MESSAGE } from './localNight.js';
import { openNightStore } from './nightStore.js';

const root = document.getElementById('local-nights');

if (root !== null) {
    initLocalNights(root);
}

async function initLocalNights(root) {
    const config = JSON.parse(root.dataset.config);
    const el = (name) => root.querySelector(`[data-nights="${name}"]`);
    const store = await openNightStore();

    const showBanner = (message) => {
        el('banner').textContent = message;
        el('banner').classList.remove('hidden');
    };

    if (store.volatile) {
        showBanner(VOLATILE_STORE_MESSAGE);
    }

    const closeInterrupted = async () => {
        for (const night of await store.listNights()) {
            const closing = finalizeInterruptedNight(night, await store.listTracks(night.id));

            if (closing !== null) {
                await store.patchNight(night.id, closing);
            }
        }
    };

    const render = async () => {
        const rows = nightRows(await store.listNights(), { reportUrlTemplate: config.routes.report });
        const template = el('row-template');
        const body = el('rows');

        root.classList.toggle('hidden', rows.length === 0 && !store.volatile);
        body.replaceChildren();

        for (const row of rows) {
            const fragment = template.content.cloneNode(true);
            const tr = fragment.querySelector('[data-night-id]');
            const cell = (name) => tr.querySelector(`[data-cell="${name}"]`);

            tr.dataset.nightId = row.id;
            cell('name').textContent = row.name;
            cell('started').textContent = row.started;
            cell('status').textContent = row.claimed ? `${row.status} · saved to account` : row.status;
            cell('sightings').textContent = String(row.sightings);
            cell('report').setAttribute('href', row.reportUrl);
            cell('claim')?.classList.toggle('hidden', row.claimed);

            body.appendChild(tr);
        }
    };

    const claimMany = async (nights) => {
        const summary = { imported: 0, skipped: 0, failed: 0 };
        let lastError = null;

        for (const night of nights) {
            if (night.claimedSessionId !== null) {
                summary.skipped++;
                continue;
            }

            try {
                await claimNight(store, night.id, {
                    importUrl: config.routes.import,
                    csrfToken: config.csrfToken,
                    room: el('room')?.value ?? null,
                });
                summary.imported++;
            } catch (error) {
                summary.failed++;
                lastError = error;
            }

            el('progress').textContent = claimSummary(summary);
            el('progress').classList.remove('hidden');
        }

        el('progress').textContent = lastError === null ? claimSummary(summary) : `${claimSummary(summary)} ${lastError.message}`;
        el('progress').classList.remove('hidden');
        await render();
    };

    root.addEventListener('click', async (event) => {
        const row = event.target.closest('[data-night-id]');
        const button = event.target.closest('[data-cell]');

        if (row === null || button === null) {
            return;
        }

        const nightId = row.dataset.nightId;

        if (button.dataset.cell === 'report') {
            event.preventDefault();
            window.location.assign(button.getAttribute('href'));
        } else if (button.dataset.cell === 'remove') {
            if (window.confirm('Remove this night from this device? There is no way to get it back.')) {
                await store.deleteNight(nightId);
                await render();
            }
        } else if (button.dataset.cell === 'claim') {
            await claimMany([await store.getNight(nightId)]);
        }
    });

    el('claim-all')?.addEventListener('click', async () => {
        el('claim-all').setAttribute('disabled', 'disabled');
        await claimMany(await store.listNights());
        el('claim-all').removeAttribute('disabled');
    });

    await closeInterrupted();
    await render();
}
