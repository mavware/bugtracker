// @vitest-environment happy-dom

// Drives the local report the way a person does: mount the shell, let the
// script read the night out of a seeded store, and check what appears and what
// the store holds afterwards. Replay is stubbed (happy-dom has no 2d context);
// reportControls and the night logic are the real ones.
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { buildLocalNight, InMemoryNightStore, localTrackFromClosed, referenceBlobKey } from '@mavware/bug-surveillance';

const stubs = vi.hoisted(() => ({
    store: null,
    replays: [],
    claimNight: vi.fn(),
}));

vi.mock('@mavware/bug-surveillance', async (importOriginal) => ({
    ...(await importOriginal()),
    openNightStore: vi.fn(async () => stubs.store),
}));

// Mocked by file, not through the package entry: the controls import the
// renderer relatively inside the package, and happy-dom has no 2d context.
vi.mock('../../../packages/surveillance/src/replay.js', () => ({
    Replay: class {
        constructor({ data }) {
            this.data = data;
            this.playing = false;
            this.playheadMs = 0;
            this.highlightedTrackId = null;
            stubs.replays.push(this);
        }

        draw() {}

        pause() {}

        play() {}

        seek() {}
    },
}));

vi.mock('../../../resources/js/surveillance/claimNight.js', () => ({
    claimNight: stubs.claimNight,
}));

const CONFIG = {
    mode: 'local',
    authenticated: true,
    csrfToken: 'test-csrf-token',
    routes: { report: '/watch/00000000-0000-4000-8000-000000000000/report', watch: '/watch', import: '/surveillance/import', register: '/register' },
};

const closedTrack = (id, startOffsetMs) => ({
    client_track_id: id,
    start_offset_ms: startOffsetMs,
    end_offset_ms: startOffsetMs + 2500,
    points: [[startOffsetMs, 5, 360], [startOffsetMs + 1000, 640, 360], [startOffsetMs + 2500, 640, 715]],
    start_crop: 'c3RhcnQ=',
    end_crop: null,
});

const el = (name) => document.querySelector(`[data-report="${name}"]`);

function mountPage(localId) {
    document.body.innerHTML = `
        <div id="report-app" data-mode="local" data-local-id="${localId}" data-config='${JSON.stringify(CONFIG)}'>
            <div data-report="missing" class="hidden"></div>
            <div data-report="volatile" class="hidden"></div>
            <div data-report="page" class="hidden">
                <h1 data-report="title"></h1>
                <p data-report="range"></p>
                <div data-report="discarded-notice" class="hidden"></div>
                <div data-report="claim-panel">
                    <input data-report="room" value="Kitchen" />
                    <button data-report="claim">Save to my account</button>
                </div>
                <div data-report="claimed-notice" class="hidden"><a data-report="claimed-link" href="#">Open</a></div>
                <span data-report="stat-track-count"></span>
                <span data-report="stat-entry"></span>
                <span data-report="stat-exit"></span>
                <canvas data-report="canvas"></canvas>
                <button data-report="play">Replay</button>
                <select data-report="speed"><option value="60" selected>60</option></select>
                <input type="range" data-report="scrub" />
                <span data-report="clock"></span>
                <input type="checkbox" data-report="trails" checked />
                <div data-report="sightings" class="hidden"><table><tbody data-report="rows"></tbody></table></div>
                <div data-report="no-sightings" class="hidden"></div>
                <button data-report="delete">Delete</button>
            </div>
            <template data-report="row-template"><table><tbody>
                <tr data-track-id="">
                    <td data-cell="time"></td>
                    <td data-cell="duration"></td>
                    <td data-cell="entered"></td>
                    <td data-cell="exited"></td>
                    <td data-cell="start-crop"></td>
                    <td data-cell="end-crop"></td>
                    <td><button data-report="toggle">Not a bug</button></td>
                </tr>
            </tbody></table></template>
        </div>
    `;

    document.querySelector('canvas').getContext = () => ({});
}

async function bootPage() {
    vi.resetModules();
    await import('../../../resources/js/surveillance/localReport.js');
    // Store reads, the reference image and the first replay build are all async.
    await new Promise((resolve) => setTimeout(resolve, 20));
}

describe('local report page', () => {
    beforeEach(async () => {
        stubs.store = new InMemoryNightStore();
        stubs.replays = [];
        stubs.claimNight.mockReset();
        // happy-dom's URL has no object-URL support; add it rather than replace the constructor.
        URL.createObjectURL = vi.fn(() => 'blob:reference');
        URL.revokeObjectURL = vi.fn();
        vi.stubGlobal(
            'Image',
            class {
                set src(value) {
                    this.loaded = value;
                    setTimeout(() => this.onload?.(), 0);
                }
            },
        );
        window.confirm = vi.fn(() => true);
        window.location.assign = vi.fn();

        const night = {
            ...buildLocalNight({ id: 'n1', startedAt: new Date(2026, 8, 8, 22, 30).getTime(), frameWidth: 1280, frameHeight: 720, settings: {} }),
            status: 'completed',
            endedAt: new Date(2026, 8, 9, 5, 0).getTime(),
        };
        await stubs.store.putNight(night);
        await stubs.store.putBlob({ key: referenceBlobKey('n1'), nightId: 'n1', bytes: new ArrayBuffer(4), type: 'image/jpeg' });
        await stubs.store.putTrack(localTrackFromClosed(closedTrack('first', 1000), 'n1', 1280, 720));
        await stubs.store.putTrack(localTrackFromClosed(closedTrack('second', 60000), 'n1', 1280, 720));
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    test('shows the night with its tiles, its sightings and a replay over the reference photo', async () => {
        mountPage('n1');
        await bootPage();

        expect(el('page').classList.contains('hidden')).toBe(false);
        expect(el('title').textContent).toBe('Night of Sep 8');
        expect(el('stat-track-count').textContent).toBe('2');
        expect(el('stat-entry').textContent).toBe('Left edge');
        expect(el('stat-exit').textContent).toBe('Bottom edge');

        const rows = document.querySelectorAll('[data-report="rows"] tr');
        expect(rows).toHaveLength(2);
        expect(rows[0].dataset.trackId).toBe('first');
        expect(rows[0].querySelector('[data-cell="start-crop"] img').getAttribute('src')).toBe('data:image/jpeg;base64,c3RhcnQ=');
        expect(rows[0].querySelector('[data-cell="end-crop"]').textContent).toBe('—');

        expect(URL.createObjectURL).toHaveBeenCalled();
        expect(stubs.replays.at(-1).data.tracks.map((track) => track.id)).toEqual(['first', 'second']);
    });

    test('marking a sighting as not a bug takes it out of the tiles, the replay and the store', async () => {
        mountPage('n1');
        await bootPage();

        document.querySelector('[data-report="rows"] tr [data-report="toggle"]').click();
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(el('stat-track-count').textContent).toBe('1');
        expect((await stubs.store.listTracks('n1'))[0].dismissedAt).not.toBeNull();
        expect((await stubs.store.getNight('n1')).analytics.track_count).toBe(1);
        expect(stubs.replays.at(-1).data.tracks.map((track) => track.id)).toEqual(['second']);

        const row = document.querySelector('[data-report="rows"] tr');
        expect(row.classList.contains('opacity-40')).toBe(true);
        expect(row.querySelector('[data-report="toggle"]').textContent).toBe('Restore');
    });

    test('clicking a row highlights its trail by its uuid', async () => {
        mountPage('n1');
        await bootPage();

        document.querySelector('[data-report="rows"] tr [data-cell="time"]').click();

        expect(stubs.replays.at(-1).highlightedTrackId).toBe('first');
    });

    test('a night whose tab died is closed at its last heartbeat before being shown', async () => {
        const startedAt = new Date(2026, 8, 8, 22, 30).getTime();
        await stubs.store.putNight({
            ...buildLocalNight({ id: 'n2', startedAt, frameWidth: 1280, frameHeight: 720, settings: {} }),
            lastHeartbeatAt: startedAt + 120000,
        });
        mountPage('n2');
        await bootPage();

        const night = await stubs.store.getNight('n2');
        expect(night.status).toBe('completed');
        expect(night.endedAt).toBe(startedAt + 120000);
        expect(el('no-sightings').classList.contains('hidden')).toBe(false);
    });

    test('a night this browser does not hold says so', async () => {
        mountPage('missing');
        await bootPage();

        expect(el('missing').classList.contains('hidden')).toBe(false);
        expect(el('page').classList.contains('hidden')).toBe(true);
    });

    test('deleting the night removes it and goes back to the watch page', async () => {
        mountPage('n1');
        await bootPage();

        el('delete').click();
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(await stubs.store.getNight('n1')).toBeNull();
        expect(window.location.assign).toHaveBeenCalledWith('/watch');
    });

    test('saving the night to the account hands over the room and shows the account report link', async () => {
        stubs.claimNight.mockImplementation(async (store, nightId) => {
            await store.patchNight(nightId, { claimedSessionId: 7 });

            return { sessionId: 7, reportUrl: '/surveillance/7/report' };
        });
        mountPage('n1');
        await bootPage();

        el('claim').click();
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(stubs.claimNight).toHaveBeenCalledWith(stubs.store, 'n1', expect.objectContaining({ importUrl: '/surveillance/import', csrfToken: 'test-csrf-token', room: 'Kitchen' }));
        expect(el('claimed-link').getAttribute('href')).toBe('/surveillance/7/report');
        expect(el('claim-panel').classList.contains('hidden')).toBe(true);
        expect(el('claimed-notice').classList.contains('hidden')).toBe(false);
    });

    test('a failed save is explained and the night stays put', async () => {
        stubs.claimNight.mockRejectedValue(new Error('Could not save this night to your account (HTTP 500).'));
        mountPage('n1');
        await bootPage();

        el('claim').click();
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(el('volatile').textContent).toContain('HTTP 500');
        expect(el('claim-panel').classList.contains('hidden')).toBe(false);
    });
});
