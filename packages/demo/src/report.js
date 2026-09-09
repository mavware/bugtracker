// The report for a night kept in this browser. Reads the night out of the
// store, fills the shell in report.html, and recomputes the analytics and
// rebuilds the replay whenever a sighting is dismissed or restored.
import {
    buildLocalReportPayload,
    finalizeInterruptedNight,
    mountReportControls,
    nightAnalytics,
    openNightStore,
    referenceBlobKey,
    reportHeader,
    sightingRows,
    statTiles,
    VOLATILE_STORE_MESSAGE,
} from '@mavware/bug-surveillance';
import { INDEX_URL, nightIdFromSearch } from './links.js';

const root = document.getElementById('report-app');

if (root !== null) {
    initReport(root);
}

async function initReport(root) {
    const el = (name) => root.querySelector(`[data-report="${name}"]`);
    const nightId = nightIdFromSearch(window.location.search);

    const store = await openNightStore();
    let night = nightId === null ? null : await store.getNight(nightId);

    if (night === null) {
        el('missing').classList.remove('hidden');

        return;
    }

    if (store.volatile) {
        el('volatile').textContent = VOLATILE_STORE_MESSAGE;
        el('volatile').classList.remove('hidden');
    }

    let tracks = await store.listTracks(nightId);

    // A night still active when its report is opened is one whose tab died.
    const closing = finalizeInterruptedNight(night, tracks);

    if (closing !== null) {
        night = await store.patchNight(nightId, closing);
    }

    if (night.analytics === null) {
        night = await store.patchNight(nightId, { analytics: nightAnalytics(night, tracks) });
    }

    const referenceImage = await loadReference(store, nightId);

    const controls = mountReportControls(root, {
        loadData: async () => ({ data: buildLocalReportPayload(night, tracks), referenceImage }),
    });

    const render = () => {
        const header = reportHeader(night);
        el('title').textContent = header.title;
        el('range').textContent = header.range;
        el('discarded-notice').classList.toggle('hidden', !header.discarded);
        el('discard-label').textContent = header.discarded ? 'Keep this night' : 'Discard night';

        const tiles = statTiles(night.analytics);
        el('stat-track-count').textContent = String(tiles.trackCount);
        el('stat-entry').textContent = tiles.topEntry;
        el('stat-exit').textContent = tiles.topExit;

        const rows = sightingRows(night, tracks);
        const template = el('row-template');
        const body = el('rows');

        el('sightings').classList.toggle('hidden', rows.length === 0);
        el('no-sightings').classList.toggle('hidden', rows.length !== 0);
        body.replaceChildren();

        for (const row of rows) {
            const tr = template.content.cloneNode(true).querySelector('[data-track-id]');
            const cell = (name) => tr.querySelector(`[data-cell="${name}"]`);

            tr.dataset.trackId = row.clientTrackId;
            tr.classList.toggle('dismissed', row.dismissed);
            cell('time').textContent = row.time;
            cell('duration').textContent = `${row.durationSeconds} s`;
            cell('entered').textContent = row.entered;
            cell('exited').textContent = row.exited;
            cell('start-crop').replaceChildren(cropImage(row.startCropSrc));
            cell('end-crop').replaceChildren(cropImage(row.endCropSrc));

            const toggle = tr.querySelector('[data-report="toggle"]');
            toggle.textContent = row.dismissed ? 'Restore' : 'Not a bug';
            toggle.dataset.clientTrackId = row.clientTrackId;

            body.appendChild(tr);
        }
    };

    // Delegated, as the rows are re-rendered on every change.
    root.addEventListener('click', async (event) => {
        const toggle = event.target.closest('[data-report="toggle"]');

        if (toggle === null) {
            return;
        }

        const clientTrackId = toggle.dataset.clientTrackId;
        const track = tracks.find((candidate) => candidate.clientTrackId === clientTrackId);

        await store.patchTrack(nightId, clientTrackId, { dismissedAt: track.dismissedAt == null ? Date.now() : null });
        tracks = await store.listTracks(nightId);
        night = await store.patchNight(nightId, { analytics: nightAnalytics(night, tracks) });

        render();
        await controls.rebuild();
    });

    // Discarding is decided here rather than at the camera: whether the setup was
    // any good is something only the finished trails show. It toggles back.
    el('discard').addEventListener('click', async () => {
        night = await store.patchNight(nightId, {
            status: night.status === 'aborted' ? 'completed' : 'aborted',
        });

        render();
    });

    el('delete').addEventListener('click', async () => {
        if (!window.confirm('Delete this night from this device? There is no way to get it back.')) {
            return;
        }

        await store.deleteNight(nightId);
        window.location.assign(INDEX_URL);
    });

    render();
    el('page').classList.remove('hidden');
    await controls.ready;
}

function cropImage(src) {
    if (src === null) {
        return document.createTextNode('—');
    }

    const image = document.createElement('img');
    image.src = src;
    image.alt = '';
    image.loading = 'lazy';

    return image;
}

/** The reference photo as an Image, from the stored bytes; revoked when the page goes. */
async function loadReference(store, nightId) {
    const blob = await store.getBlob(referenceBlobKey(nightId));

    if (blob === null) {
        return null;
    }

    const url = URL.createObjectURL(new Blob([blob.bytes], { type: blob.type }));
    window.addEventListener('pagehide', () => URL.revokeObjectURL(url));

    return new Promise((resolve) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => resolve(null);
        image.src = url;
    });
}
