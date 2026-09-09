// The list of nights this browser holds, under the capture panel. Fills the
// table from the night store and closes any night whose tab died.
import { finalizeInterruptedNight, nightRows, openNightStore, VOLATILE_STORE_MESSAGE } from '@mavware/bug-surveillance';
import { REPORT_URL_TEMPLATE } from './links.js';

const root = document.getElementById('local-nights');

if (root !== null) {
    initLocalNights(root);
}

async function initLocalNights(root) {
    const el = (name) => root.querySelector(`[data-nights="${name}"]`);
    const store = await openNightStore();

    if (store.volatile) {
        el('banner').textContent = VOLATILE_STORE_MESSAGE;
        el('banner').classList.remove('hidden');
    }

    for (const night of await store.listNights()) {
        const closing = finalizeInterruptedNight(night, await store.listTracks(night.id));

        if (closing !== null) {
            await store.patchNight(night.id, closing);
        }
    }

    const render = async () => {
        const rows = nightRows(await store.listNights(), { reportUrlTemplate: REPORT_URL_TEMPLATE });
        const template = el('row-template');
        const body = el('rows');

        root.classList.toggle('hidden', rows.length === 0 && !store.volatile);
        body.replaceChildren();

        for (const row of rows) {
            const tr = template.content.cloneNode(true).querySelector('[data-night-id]');
            const cell = (name) => tr.querySelector(`[data-cell="${name}"]`);

            tr.dataset.nightId = row.id;
            cell('name').textContent = row.name;
            cell('started').textContent = row.started;
            cell('status').textContent = row.status;
            cell('sightings').textContent = String(row.sightings);
            cell('report').setAttribute('href', row.reportUrl);

            body.appendChild(tr);
        }
    };

    root.addEventListener('click', async (event) => {
        const row = event.target.closest('[data-night-id]');
        const button = event.target.closest('[data-cell="remove"]');

        if (row === null || button === null) {
            return;
        }

        if (window.confirm('Remove this night from this device? There is no way to get it back.')) {
            await store.deleteNight(row.dataset.nightId);
            await render();
        }
    });

    await render();
}
