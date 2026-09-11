// @vitest-environment happy-dom

// The dashboard's per-night import list, driven against a seeded store.
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { buildLocalNight, InMemoryNightStore } from '@mavware/bug-surveillance';

const stubs = vi.hoisted(() => ({
    store: null,
    claimNight: vi.fn(),
}));

vi.mock('@mavware/bug-surveillance', async (importOriginal) => ({
    ...(await importOriginal()),
    openNightStore: vi.fn(async () => stubs.store),
}));

vi.mock('../../../resources/js/surveillance/claimNight.js', () => ({
    claimNight: stubs.claimNight,
}));

const CONFIG = {
    csrfToken: 'test-csrf-token',
    routes: { import: '/surveillance/import' },
    customers: [{ id: 7, name: 'Alvarez' }, { id: 9, name: 'Brody' }],
};

const el = (name) => document.querySelector(`[data-claim="${name}"]`);
const rows = () => document.querySelectorAll('[data-claim="rows"] [data-night-id]');
const cell = (row, name) => row.querySelector(`[data-cell="${name}"]`);

function mountPage(config = CONFIG) {
    document.body.innerHTML = `
        <div id="claim-nights" class="hidden" data-config='${JSON.stringify(config)}'>
            <span data-claim="pending"><span data-claim="count">0</span></span>
            <span data-claim="all-saved" class="hidden">All saved</span>
            <p data-claim="progress" class="hidden"></p>
            <ul data-claim="rows"></ul>
            <template data-claim="row-template">
                <li data-night-id="">
                    <span data-cell="name"></span>
                    <span data-cell="started"></span>
                    <span data-cell="sightings"></span>
                    <span data-cell="status"></span>
                    <div data-cell="controls">
                        <select data-cell="customer" class="hidden"><option value="">No customer</option></select>
                        <input data-cell="room" />
                        <button data-cell="import"><span data-cell="import-label">Import</span></button>
                    </div>
                    <div data-cell="saved" class="hidden"><button data-cell="remove">Remove local copy</button></div>
                </li>
            </template>
        </div>
    `;
}

async function bootPage() {
    vi.resetModules();
    await import('../../../resources/js/surveillance/claim.js');
    await settle();
}

const settle = () => new Promise((resolve) => setTimeout(resolve, 20));

const night = (id, startedAt, extra = {}) => ({
    ...buildLocalNight({ id, startedAt, frameWidth: 1280, frameHeight: 720, settings: {} }),
    status: 'completed',
    endedAt: startedAt + 3600000,
    analytics: { track_count: 3, entry_zones: [], exit_zones: [] },
    ...extra,
});

describe('dashboard import list', () => {
    beforeEach(() => {
        stubs.store = new InMemoryNightStore();
        stubs.store.volatile = false;
        stubs.claimNight.mockReset();
        // The real claimNight removes the local copy once the account has the night.
        stubs.claimNight.mockImplementation(async (store, nightId) => {
            await store.deleteNight(nightId);

            return { sessionId: 42, reportUrl: '/surveillance/42/report' };
        });
        window.confirm = vi.fn(() => true);
        window.Livewire = { dispatch: vi.fn() };
        mountPage();
    });

    afterEach(() => {
        delete window.Livewire;
        vi.unstubAllGlobals();
    });

    test('stays hidden while this browser holds no nights', async () => {
        await bootPage();

        expect(document.getElementById('claim-nights').classList.contains('hidden')).toBe(true);
    });

    test('lists every night with its own room field and customer choice, newest first', async () => {
        await stubs.store.putNight(night('old', new Date(2026, 8, 7, 22, 0).getTime(), { claimedSessionId: 3 }));
        await stubs.store.putNight(night('new', new Date(2026, 8, 8, 22, 0).getTime()));
        await bootPage();

        expect(document.getElementById('claim-nights').classList.contains('hidden')).toBe(false);
        expect([...rows()].map((row) => row.dataset.nightId)).toEqual(['new', 'old']);
        expect(el('count').textContent).toBe('1');

        const [pending, saved] = rows();
        expect(cell(pending, 'name').textContent).toBe('Night of Sep 8');
        expect(cell(pending, 'controls').classList.contains('hidden')).toBe(false);
        expect(cell(pending, 'saved').classList.contains('hidden')).toBe(true);
        expect([...cell(pending, 'customer').options].map((option) => option.textContent)).toEqual(['No customer', 'Alvarez', 'Brody']);
        expect(cell(pending, 'customer').classList.contains('hidden')).toBe(false);

        expect(cell(saved, 'controls').classList.contains('hidden')).toBe(true);
        expect(cell(saved, 'saved').classList.contains('hidden')).toBe(false);
    });

    test('a user with no customers is not shown an empty customer choice', async () => {
        mountPage({ ...CONFIG, customers: [] });
        await stubs.store.putNight(night('n1', new Date(2026, 8, 8, 22, 0).getTime()));
        await bootPage();

        expect(cell(rows()[0], 'customer').classList.contains('hidden')).toBe(true);
    });

    test('importing one night sends that row\'s room and customer, drops its copy, and tells the sessions list', async () => {
        await stubs.store.putNight(night('a', new Date(2026, 8, 7, 22, 0).getTime()));
        await stubs.store.putNight(night('b', new Date(2026, 8, 8, 22, 0).getTime()));
        await bootPage();

        const [rowB, rowA] = rows();
        cell(rowB, 'room').value = 'Kitchen';
        cell(rowB, 'customer').value = '9';
        cell(rowA, 'room').value = 'Hall';
        cell(rowA, 'customer').value = '7';

        cell(rowB, 'import').click();
        await settle();

        expect(stubs.claimNight).toHaveBeenCalledTimes(1);
        expect(stubs.claimNight.mock.calls[0][1]).toBe('b');
        expect(stubs.claimNight.mock.calls[0][2]).toMatchObject({ importUrl: '/surveillance/import', csrfToken: 'test-csrf-token', room: 'Kitchen', customerId: '9' });

        // B's local copy is gone, and the other row keeps what was typed into it.
        expect([...rows()].map((row) => row.dataset.nightId)).toEqual(['a']);
        expect(el('count').textContent).toBe('1');
        expect(cell(rows()[0], 'room').value).toBe('Hall');
        expect(cell(rows()[0], 'customer').value).toBe('7');
        expect(window.Livewire.dispatch).toHaveBeenCalledWith('night-imported');
    });

    test('importing the last night hides the panel', async () => {
        await stubs.store.putNight(night('n1', new Date(2026, 8, 8, 22, 0).getTime()));
        await bootPage();

        cell(rows()[0], 'import').click();
        await settle();

        expect(rows()).toHaveLength(0);
        expect(document.getElementById('claim-nights').classList.contains('hidden')).toBe(true);
    });

    test('a night with no customer chosen is sent with none', async () => {
        await stubs.store.putNight(night('n1', new Date(2026, 8, 8, 22, 0).getTime()));
        await bootPage();

        cell(rows()[0], 'import').click();
        await settle();

        expect(stubs.claimNight.mock.calls[0][2]).toMatchObject({ room: '', customerId: null });
    });

    test('a failed import is explained and the row can be tried again', async () => {
        stubs.claimNight.mockRejectedValueOnce(new Error('Could not save this night to your account (HTTP 500).'));
        await stubs.store.putNight(night('n1', new Date(2026, 8, 8, 22, 0).getTime()));
        await bootPage();

        cell(rows()[0], 'import').click();
        await settle();

        expect(el('progress').textContent).toContain('HTTP 500');
        expect(rows()).toHaveLength(1);
        expect(cell(rows()[0], 'import').hasAttribute('disabled')).toBe(false);
        expect(cell(rows()[0], 'import-label').textContent).toBe('Import');
        expect(await stubs.store.getNight('n1')).not.toBeNull();
    });

    // A copy kept by its own report page still shows here, and its row is the
    // one place to clear it; clearing the last one hides the panel.
    test('a copy saved from its report page can be removed from its row', async () => {
        await stubs.store.putNight(night('n1', new Date(2026, 8, 8, 22, 0).getTime(), { claimedSessionId: 3 }));
        await bootPage();

        expect(el('pending').classList.contains('hidden')).toBe(true);
        expect(el('all-saved').classList.contains('hidden')).toBe(false);

        cell(rows()[0], 'remove').click();
        await settle();

        expect(window.confirm).toHaveBeenCalled();
        expect(await stubs.store.listNights()).toHaveLength(0);
        expect(document.getElementById('claim-nights').classList.contains('hidden')).toBe(true);
    });
});
