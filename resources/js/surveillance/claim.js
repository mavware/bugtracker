// The dashboard's offer to import nights this browser recorded before the user
// had an account. Shown only when the night store holds unclaimed nights;
// otherwise the panel stays hidden and the dashboard is as it was.
import { claimNight } from './claimNight.js';
import { claimSummary } from './claimLogic.js';
import { openNightStore } from './nightStore.js';

const root = document.getElementById('claim-nights');

if (root !== null) {
    initClaimPanel(root);
}

async function initClaimPanel(root) {
    const config = JSON.parse(root.dataset.config);
    const el = (name) => root.querySelector(`[data-claim="${name}"]`);
    const store = await openNightStore();

    const unclaimed = async () => (await store.listNights()).filter((night) => night.claimedSessionId === null && night.status !== 'active');
    const claimed = async () => (await store.listNights()).filter((night) => night.claimedSessionId !== null);

    const render = async () => {
        const pending = await unclaimed();
        const done = await claimed();

        root.classList.toggle('hidden', pending.length === 0 && done.length === 0);
        el('pending').classList.toggle('hidden', pending.length === 0);
        el('count').textContent = pending.length;
        el('remove').classList.toggle('hidden', done.length === 0);
    };

    el('import').addEventListener('click', async () => {
        el('import').setAttribute('disabled', 'disabled');
        const summary = { imported: 0, skipped: 0, failed: 0 };
        let lastError = null;

        for (const night of await unclaimed()) {
            try {
                await claimNight(store, night.id, {
                    importUrl: config.routes.import,
                    csrfToken: config.csrfToken,
                    room: el('room').value,
                });
                summary.imported++;
            } catch (error) {
                summary.failed++;
                lastError = error;
            }

            el('progress').textContent = claimSummary(summary);
            el('progress').classList.remove('hidden');
        }

        el('import').removeAttribute('disabled');

        if (lastError !== null) {
            el('progress').textContent = `${claimSummary(summary)} ${lastError.message}`;

            return;
        }

        // The sessions list and trends are server-rendered; a reload picks the
        // imported nights up.
        window.location.reload();
    });

    el('remove').addEventListener('click', async () => {
        if (!window.confirm('Remove the local copies of nights already saved to your account?')) {
            return;
        }

        for (const night of await claimed()) {
            await store.deleteNight(night.id);
        }

        await render();
    });

    await render();
}
