// The list of nights this browser holds, on the watch page. Fills the table
// from the night store, closes any night whose tab died, and, for a signed-in
// visitor, offers to save nights to the account.
import { claimNight } from './claimNight.js';
import { claimSummary } from './claimLogic.js';
import { NIGHTS_PER_PAGE, paginate, pageSummary } from './localNightsLogic.js';
import { finalizeInterruptedNight, nightRows, openNightStore, VOLATILE_STORE_MESSAGE } from '@mavware/bug-surveillance';

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

    // The page on show. Kept across renders so removing a night or saving one
    // does not throw the reader back to the first page; paginate() pulls it back
    // if the page it names no longer exists.
    let page = 1;

    const render = async () => {
        const all = nightRows(await store.listNights(), { reportUrlTemplate: config.routes.report });
        const current = paginate(all, page, NIGHTS_PER_PAGE);
        const template = el('row-template');
        const body = el('rows');

        page = current.page;
        root.classList.toggle('hidden', all.length === 0 && !store.volatile);
        body.replaceChildren();

        el('pager').classList.toggle('hidden', current.pageCount <= 1);
        el('page-summary').textContent = pageSummary(current);
        el('prev').toggleAttribute('disabled', current.page <= 1);
        el('next').toggleAttribute('disabled', current.page >= current.pageCount);

        for (const row of current.rows) {
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

    el('prev').addEventListener('click', async () => {
        page--;
        await render();
    });

    el('next').addEventListener('click', async () => {
        page++;
        await render();
    });

    el('claim-all')?.addEventListener('click', async () => {
        el('claim-all').setAttribute('disabled', 'disabled');
        await claimMany(await store.listNights());
        el('claim-all').removeAttribute('disabled');
    });

    await closeInterrupted();
    await render();
}
