import { beforeEach, describe, expect, test, vi } from 'vitest';
import { ClaimError, claimNight } from '../../../resources/js/surveillance/claimNight.js';
import { CLAIM_AUTH_LOST_MESSAGE, IMPORT_CHUNK_SIZE } from '../../../resources/js/surveillance/claimLogic.js';
import { buildLocalNight, InMemoryNightStore, localTrackFromClosed, referenceBlobKey } from '@bugtracker/surveillance';

const IMPORT_URL = 'https://bugtracker.test/surveillance/import';

const closedTrack = (id) => ({
    client_track_id: id,
    start_offset_ms: 1000,
    end_offset_ms: 3500,
    points: [[1000, 5, 360], [3500, 640, 715]],
    start_crop: 'c3RhcnQ=',
    end_crop: null,
});

describe('claimNight', () => {
    let store;
    let fetch;

    beforeEach(async () => {
        store = new InMemoryNightStore();
        fetch = vi.fn(async () => ({
            ok: true,
            status: 200,
            json: async () => ({ session_id: 42, created: true, accepted: [], duplicate: [], report_url: 'https://bugtracker.test/surveillance/42/report' }),
        }));

        const night = { ...buildLocalNight({ id: 'n1', startedAt: 1_000_000, frameWidth: 1280, frameHeight: 720, settings: {} }), status: 'completed', endedAt: 2_000_000 };
        await store.putNight(night);
        await store.putBlob({ key: referenceBlobKey('n1'), nightId: 'n1', bytes: new Uint8Array([255, 216, 255]).buffer, type: 'image/jpeg' });
        await store.putTrack(localTrackFromClosed(closedTrack('t1'), 'n1', 1280, 720));
    });

    test('posts the night with the right headers and marks it claimed with the session it became', async () => {
        const result = await claimNight(store, 'n1', { importUrl: IMPORT_URL, csrfToken: 'test-csrf-token', room: 'Kitchen', fetch });

        expect(fetch).toHaveBeenCalledTimes(1);
        const [url, options] = fetch.mock.calls[0];
        expect(url).toBe(IMPORT_URL);
        expect(options.headers['X-CSRF-TOKEN']).toBe('test-csrf-token');
        expect(options.headers['Content-Type']).toBe('application/json');

        const body = JSON.parse(options.body);
        expect(body.local_id).toBe('n1');
        expect(body.room).toBe('Kitchen');
        expect(body.reference_image).toBe('/9j/');
        expect(body.tracks[0].client_track_id).toBe('t1');

        expect(result).toEqual({ sessionId: 42, reportUrl: 'https://bugtracker.test/surveillance/42/report' });
        expect((await store.getNight('n1')).claimedSessionId).toBe(42);
    });

    test('a large night goes up in several requests, each carrying the local id', async () => {
        for (let index = 0; index < IMPORT_CHUNK_SIZE + 5; index++) {
            await store.putTrack(localTrackFromClosed(closedTrack(`big${index}`), 'n1', 1280, 720));
        }

        await claimNight(store, 'n1', { importUrl: IMPORT_URL, csrfToken: 'x', fetch });

        expect(fetch).toHaveBeenCalledTimes(2);
        expect(JSON.parse(fetch.mock.calls[1][1].body).local_id).toBe('n1');
    });

    test('a lost login stops the import with the message to show, and the night stays unclaimed', async () => {
        fetch.mockResolvedValueOnce({ ok: false, status: 419 });

        await expect(claimNight(store, 'n1', { importUrl: IMPORT_URL, csrfToken: 'x', fetch })).rejects.toMatchObject({
            message: CLAIM_AUTH_LOST_MESSAGE,
            status: 419,
        });
        expect((await store.getNight('n1')).claimedSessionId).toBeNull();
    });

    test('a night that is not on the device cannot be claimed', async () => {
        await expect(claimNight(store, 'missing', { importUrl: IMPORT_URL, csrfToken: 'x', fetch })).rejects.toBeInstanceOf(ClaimError);
        expect(fetch).not.toHaveBeenCalled();
    });
});
