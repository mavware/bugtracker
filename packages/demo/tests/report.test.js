// @vitest-environment happy-dom

// Drives the demo's report page against a seeded store: the shell fills in,
// a dismissed sighting drops out of the tiles and the replay, and a night the
// browser does not hold says so.
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { buildLocalNight, InMemoryNightStore, localTrackFromClosed, referenceBlobKey } from '@bugtracker/surveillance';

const stubs = vi.hoisted(() => ({ store: null, replays: [] }));

vi.mock('@bugtracker/surveillance', async (importOriginal) => ({
    ...(await importOriginal()),
    openNightStore: vi.fn(async () => stubs.store),
}));

// Mocked by file: the controls import the renderer relatively inside the
// package, and happy-dom has no 2d context.
vi.mock('../../surveillance/src/replay.js', () => ({
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

const closedTrack = (id, startOffsetMs) => ({
    client_track_id: id,
    start_offset_ms: startOffsetMs,
    end_offset_ms: startOffsetMs + 2500,
    points: [[startOffsetMs, 5, 360], [startOffsetMs + 1000, 640, 360], [startOffsetMs + 2500, 640, 715]],
    start_crop: 'c3RhcnQ=',
    end_crop: null,
});

const el = (name) => document.querySelector(`[data-report="${name}"]`);

function mountPage() {
    document.body.innerHTML = `
        <div id="report-app">
            <div data-report="missing" class="hidden"></div>
            <div data-report="volatile" class="hidden"></div>
            <div data-report="page" class="hidden">
                <h1 data-report="title"></h1>
                <p data-report="range"></p>
                <div data-report="discarded-notice" class="hidden"></div>
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

async function bootPage(search) {
    window.history.replaceState(null, '', `/report.html${search}`);
    vi.resetModules();
    await import('../src/report.js');
    await new Promise((resolve) => setTimeout(resolve, 20));
}

describe('demo report page', () => {
    beforeEach(async () => {
        stubs.store = new InMemoryNightStore();
        stubs.replays = [];
        URL.createObjectURL = vi.fn(() => 'blob:reference');
        URL.revokeObjectURL = vi.fn();
        vi.stubGlobal(
            'Image',
            class {
                set src(value) {
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
        mountPage();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    test('shows the night named in the address, with tiles, sightings and a replay', async () => {
        await bootPage('?night=n1');

        expect(el('page').classList.contains('hidden')).toBe(false);
        expect(el('title').textContent).toBe('Night of Sep 8');
        expect(el('stat-track-count').textContent).toBe('2');
        expect(el('stat-entry').textContent).toBe('Left edge');
        expect(document.querySelectorAll('[data-report="rows"] tr')).toHaveLength(2);
        expect(stubs.replays.at(-1).data.tracks.map((track) => track.id)).toEqual(['first', 'second']);
    });

    test('marking a sighting as not a bug takes it out of the tiles, the replay and the store', async () => {
        await bootPage('?night=n1');

        document.querySelector('[data-report="rows"] tr [data-report="toggle"]').click();
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(el('stat-track-count').textContent).toBe('1');
        expect((await stubs.store.getNight('n1')).analytics.track_count).toBe(1);
        expect(stubs.replays.at(-1).data.tracks.map((track) => track.id)).toEqual(['second']);
        expect(document.querySelector('[data-report="rows"] tr').classList.contains('dismissed')).toBe(true);
    });

    test('a night this browser does not hold, or no night at all, says so', async () => {
        await bootPage('?night=missing');
        expect(el('missing').classList.contains('hidden')).toBe(false);

        mountPage();
        await bootPage('');
        expect(el('missing').classList.contains('hidden')).toBe(false);
        expect(el('page').classList.contains('hidden')).toBe(true);
    });

    test('deleting the night removes it and goes back to the watch page', async () => {
        await bootPage('?night=n1');

        el('delete').click();
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(await stubs.store.getNight('n1')).toBeNull();
        expect(window.location.assign).toHaveBeenCalledWith('./');
    });
});
