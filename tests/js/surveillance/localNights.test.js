// @vitest-environment happy-dom

// The list of nights on the watch page, driven against a seeded store.
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { buildLocalNight } from '../../../resources/js/surveillance/localNight.js';
import { InMemoryNightStore } from '../../../resources/js/surveillance/nightStoreMemory.js';

const stubs = vi.hoisted(() => ({
    store: null,
    claimNight: vi.fn(),
}));

vi.mock('../../../resources/js/surveillance/nightStore.js', () => ({
    openNightStore: vi.fn(async () => stubs.store),
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

const el = (name) => document.querySelector(`[data-nights="${name}"]`);
const rows = () => document.querySelectorAll('[data-nights="rows"] tr');

function mountPage() {
    document.body.innerHTML = `
        <section id="local-nights" class="hidden" data-config='${JSON.stringify(CONFIG)}'>
            <input data-nights="room" value="Hall" />
            <button data-nights="claim-all">Import all</button>
            <div data-nights="banner" class="hidden"></div>
            <p data-nights="progress" class="hidden"></p>
            <table><tbody data-nights="rows"></tbody></table>
            <template data-nights="row-template"><table><tbody>
                <tr data-night-id="">
                    <td data-cell="name"></td>
                    <td data-cell="started"></td>
                    <td data-cell="status"></td>
                    <td data-cell="sightings"></td>
                    <td>
                        <a data-cell="report" href="#">Report</a>
                        <button data-cell="claim">Save to account</button>
                        <button data-cell="remove">Remove</button>
                    </td>
                </tr>
            </tbody></table></template>
        </section>
    `;
}

async function bootPage() {
    vi.resetModules();
    await import('../../../resources/js/surveillance/localNights.js');
    await new Promise((resolve) => setTimeout(resolve, 20));
}

const night = (id, startedAt, extra = {}) => ({
    ...buildLocalNight({ id, startedAt, frameWidth: 1280, frameHeight: 720, settings: {} }),
    status: 'completed',
    endedAt: startedAt + 3600000,
    analytics: { track_count: 3, entry_zones: [], exit_zones: [] },
    ...extra,
});

describe('local nights list', () => {
    beforeEach(() => {
        stubs.store = new InMemoryNightStore();
        stubs.store.volatile = false;
        stubs.claimNight.mockReset();
        window.confirm = vi.fn(() => true);
        window.location.assign = vi.fn();
        mountPage();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    test('stays hidden while this browser holds no nights', async () => {
        await bootPage();

        expect(document.getElementById('local-nights').classList.contains('hidden')).toBe(true);
    });

    test('lists nights newest first with a report link and whether they are saved', async () => {
        await stubs.store.putNight(night('old', new Date(2026, 8, 7, 22, 0).getTime(), { claimedSessionId: 3 }));
        await stubs.store.putNight(night('new', new Date(2026, 8, 8, 22, 0).getTime()));
        await bootPage();

        expect(document.getElementById('local-nights').classList.contains('hidden')).toBe(false);
        expect([...rows()].map((row) => row.dataset.nightId)).toEqual(['new', 'old']);
        expect(rows()[0].querySelector('[data-cell="name"]').textContent).toBe('Night of Sep 8');
        expect(rows()[0].querySelector('[data-cell="sightings"]').textContent).toBe('3');
        expect(rows()[0].querySelector('[data-cell="report"]').getAttribute('href')).toBe('/watch/new/report');
        expect(rows()[1].querySelector('[data-cell="status"]').textContent).toContain('saved to account');
        expect(rows()[1].querySelector('[data-cell="claim"]').classList.contains('hidden')).toBe(true);
    });

    test('closes a night whose tab died so it can be read', async () => {
        const startedAt = new Date(2026, 8, 8, 22, 0).getTime();
        await stubs.store.putNight({ ...buildLocalNight({ id: 'n1', startedAt, frameWidth: 1, frameHeight: 1, settings: {} }), lastHeartbeatAt: startedAt + 60000 });
        await bootPage();

        expect((await stubs.store.getNight('n1')).status).toBe('completed');
        expect(rows()[0].querySelector('[data-cell="status"]').textContent).toBe('Completed');
    });

    test('removing a night asks first, then drops it from the store and the list', async () => {
        await stubs.store.putNight(night('n1', new Date(2026, 8, 8, 22, 0).getTime()));
        await bootPage();

        rows()[0].querySelector('[data-cell="remove"]').click();
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(window.confirm).toHaveBeenCalled();
        expect(await stubs.store.getNight('n1')).toBeNull();
        expect(rows()).toHaveLength(0);
    });

    test('importing everything skips nights already saved and reports the outcome', async () => {
        await stubs.store.putNight(night('done', new Date(2026, 8, 6, 22, 0).getTime(), { claimedSessionId: 3 }));
        await stubs.store.putNight(night('a', new Date(2026, 8, 7, 22, 0).getTime()));
        await stubs.store.putNight(night('b', new Date(2026, 8, 8, 22, 0).getTime()));
        stubs.claimNight.mockImplementation(async (store, nightId) => {
            await store.patchNight(nightId, { claimedSessionId: 9 });

            return { sessionId: 9, reportUrl: '/surveillance/9/report' };
        });
        await bootPage();

        el('claim-all').click();
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(stubs.claimNight).toHaveBeenCalledTimes(2);
        expect(stubs.claimNight.mock.calls[0][2]).toMatchObject({ room: 'Hall', importUrl: '/surveillance/import' });
        expect(el('progress').textContent).toBe('2 nights saved to your account, 1 already saved.');
        expect([...rows()].every((row) => row.querySelector('[data-cell="status"]').textContent.includes('saved to account'))).toBe(true);
    });

    test('a failed import is explained and the rest still go through', async () => {
        await stubs.store.putNight(night('a', new Date(2026, 8, 7, 22, 0).getTime()));
        await stubs.store.putNight(night('b', new Date(2026, 8, 8, 22, 0).getTime()));
        stubs.claimNight
            .mockRejectedValueOnce(new Error('Could not save this night to your account (HTTP 500).'))
            .mockResolvedValueOnce({ sessionId: 9, reportUrl: '/surveillance/9/report' });
        await bootPage();

        el('claim-all').click();
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(el('progress').textContent).toContain('1 night saved to your account, 1 could not be saved.');
        expect(el('progress').textContent).toContain('HTTP 500');
    });

    test('warns when the browser will not keep nights between visits', async () => {
        stubs.store.volatile = true;
        await bootPage();

        expect(el('banner').classList.contains('hidden')).toBe(false);
        expect(el('banner').textContent).toContain('will not keep nights');
    });
});
