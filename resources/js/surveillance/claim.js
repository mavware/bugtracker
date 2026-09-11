// The dashboard's list of nights this browser recorded before the user had an
// account: one row a night, each filed under its own room and customer as it is
// saved. Shown only while the night store holds something; otherwise the panel
// stays hidden and the dashboard is as it was.
import { claimNight } from './claimNight.js';
import { finalizeInterruptedNight, nightRows, openNightStore } from '@mavware/bug-surveillance';

const root = document.getElementById('claim-nights');

if (root !== null) {
    initClaimPanel(root);
}

async function initClaimPanel(root) {
    const config = JSON.parse(root.dataset.config);
    const el = (name) => root.querySelector(`[data-claim="${name}"]`);
    const store = await openNightStore();

    const showProgress = (message) => {
        el('progress').textContent = message;
        el('progress').classList.toggle('hidden', message === '');
    };

    // A night still marked active is one whose tab died; close it so it can be
    // saved, the same as the watch page does.
    const closeInterrupted = async () => {
        for (const night of await store.listNights()) {
            const closing = finalizeInterruptedNight(night, await store.listTracks(night.id));

            if (closing !== null) {
                await store.patchNight(night.id, closing);
            }
        }
    };

    /**
     * What has been typed into each unsaved row, by night, so a render after one
     * import does not wipe the room and customer chosen for the others.
     */
    const rememberChoices = () => {
        const choices = new Map();

        for (const row of el('rows').querySelectorAll('[data-night-id]')) {
            choices.set(row.dataset.nightId, {
                room: row.querySelector('[data-cell="room"]').value,
                customerId: row.querySelector('[data-cell="customer"]').value,
            });
        }

        return choices;
    };

    const buildRow = (night, choice) => {
        const fragment = el('row-template').content.cloneNode(true);
        const li = fragment.querySelector('[data-night-id]');
        const cell = (name) => li.querySelector(`[data-cell="${name}"]`);

        li.dataset.nightId = night.id;
        cell('name').textContent = night.name;
        cell('started').textContent = night.started;
        cell('sightings').textContent = String(night.sightings);
        cell('status').textContent = night.status;
        cell('controls').classList.toggle('hidden', night.claimed);
        cell('saved').classList.toggle('hidden', !night.claimed);

        const select = cell('customer');

        for (const customer of config.customers) {
            const option = document.createElement('option');
            option.value = String(customer.id);
            option.textContent = customer.name;
            select.appendChild(option);
        }

        select.classList.toggle('hidden', config.customers.length === 0);
        select.value = choice?.customerId ?? '';
        cell('room').value = choice?.room ?? '';

        return li;
    };

    const render = async () => {
        const choices = rememberChoices();
        const rows = nightRows(await store.listNights(), { reportUrlTemplate: '' });
        const pending = rows.filter((row) => !row.claimed);

        root.classList.toggle('hidden', rows.length === 0);
        el('pending').classList.toggle('hidden', pending.length === 0);
        el('all-saved').classList.toggle('hidden', pending.length !== 0);
        el('count').textContent = String(pending.length);

        el('rows').replaceChildren(...rows.map((row) => buildRow(row, choices.get(row.id))));
    };

    const importOne = async (row) => {
        const cell = (name) => row.querySelector(`[data-cell="${name}"]`);
        const customerId = cell('customer').value;

        cell('import').setAttribute('disabled', 'disabled');
        cell('import-label').textContent = 'Importing…';
        showProgress('');

        try {
            await claimNight(store, row.dataset.nightId, {
                importUrl: config.routes.import,
                csrfToken: config.csrfToken,
                room: cell('room').value,
                customerId: customerId === '' ? null : customerId,
            });
        } catch (error) {
            cell('import').removeAttribute('disabled');
            cell('import-label').textContent = 'Import';
            showProgress(error.message);

            return;
        }

        await render();

        // The sessions list below is a Livewire component; told, it re-renders
        // with the new night in it, and the rest of this list stays as typed.
        window.Livewire?.dispatch('night-imported');
    };

    root.addEventListener('click', async (event) => {
        const row = event.target.closest('[data-night-id]');
        const button = event.target.closest('[data-cell]');

        if (row === null || button === null) {
            return;
        }

        if (button.dataset.cell === 'import') {
            await importOne(row);
        } else if (button.dataset.cell === 'remove') {
            if (window.confirm('Remove this night from this device? Its copy in your account stays.')) {
                await store.deleteNight(row.dataset.nightId);
                await render();
            }
        }
    });

    await closeInterrupted();
    await render();
}
