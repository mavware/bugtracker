// The server half of the night sink: what reaches the network and how the
// queue behaves when the server answers badly.
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { Uploader } from '../../../resources/js/surveillance/uploader.js';

const ROUTES = {
    reference: 'https://bugtracker.test/surveillance/1/reference',
    tracks: 'https://bugtracker.test/surveillance/1/tracks',
    heartbeat: 'https://bugtracker.test/surveillance/1/heartbeat',
    end: 'https://bugtracker.test/surveillance/1/end',
};

const track = (id) => ({ client_track_id: id, start_offset_ms: 0, end_offset_ms: 1, points: [[0, 1, 1], [1, 2, 2]] });

describe('Uploader', () => {
    let uploader;
    let statuses;

    beforeEach(() => {
        vi.useFakeTimers();
        vi.stubGlobal('fetch', vi.fn(async () => ({ ok: true, status: 200, json: async () => ({}) })));
        statuses = [];
        uploader = new Uploader({ routes: ROUTES, csrfToken: 'test-csrf-token', onStatus: (status) => statuses.push(status) });
    });

    afterEach(() => {
        uploader.stop();
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    test('the reference frame goes up as multipart with the dimensions and flattened settings', async () => {
        await uploader.storeReference({
            blob: new Blob(['jpeg'], { type: 'image/jpeg' }),
            frameWidth: 1280,
            frameHeight: 720,
            settings: { procWidth: 320, diffThreshold: 20 },
        });

        const [url, options] = fetch.mock.calls[0];
        expect(url).toBe(ROUTES.reference);
        expect(options.method).toBe('POST');
        expect(options.headers['X-CSRF-TOKEN']).toBe('test-csrf-token');
        expect(options.body.get('frame_width')).toBe('1280');
        expect(options.body.get('frame_height')).toBe('720');
        expect(options.body.get('settings[procWidth]')).toBe('320');
        expect(options.body.get('image')).toBeInstanceOf(Blob);
        expect(options.body.has('planned_end_at')).toBe(false);
    });

    test('a planned end rides along with the reference frame', async () => {
        await uploader.storeReference({
            blob: new Blob(['jpeg'], { type: 'image/jpeg' }),
            frameWidth: 1280,
            frameHeight: 720,
            settings: {},
            plannedEndAt: Date.UTC(2026, 8, 12, 6, 30),
        });

        const [, options] = fetch.mock.calls[0];
        expect(options.body.get('planned_end_at')).toBe('2026-09-12T06:30:00.000Z');
    });

    test('a refused reference frame throws with the status, so the page can show it', async () => {
        fetch.mockResolvedValueOnce({ ok: false, status: 409 });

        await expect(uploader.storeReference({ blob: new Blob(['x']), frameWidth: 1, frameHeight: 1, settings: {} })).rejects.toThrow(
            'Could not start the session (HTTP 409).',
        );
    });

    test('ending the session sends the offset and hands back the report url', async () => {
        fetch.mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ report_url: 'https://bugtracker.test/surveillance/1/report' }) });

        const result = await uploader.end({ endedAtOffsetMs: 60000, aborted: true });

        const [url, options] = fetch.mock.calls[0];
        expect(url).toBe(ROUTES.end);
        expect(JSON.parse(options.body)).toEqual({ ended_at_offset_ms: 60000, aborted: true });
        expect(result).toEqual({ ok: true, status: 200, reportUrl: 'https://bugtracker.test/surveillance/1/report' });
    });

    test('a failed end reports the status without a report url', async () => {
        fetch.mockResolvedValueOnce({ ok: false, status: 500 });

        expect(await uploader.end({ endedAtOffsetMs: 1, aborted: false })).toEqual({ ok: false, status: 500, reportUrl: null });
    });

    test('a flush posts the queue in one batch and empties it once accepted', async () => {
        uploader.enqueue(track('a'));
        uploader.enqueue(track('b'));

        await uploader.flush();

        expect(JSON.parse(fetch.mock.calls[0][1].body).tracks.map((t) => t.client_track_id)).toEqual(['a', 'b']);
        expect(uploader.queue).toEqual([]);
        expect(statuses.at(-1)).toEqual({ queueDepth: 0 });
    });

    test('a lost login pauses uploads and holds the queue rather than dropping it', async () => {
        fetch.mockResolvedValueOnce({ ok: false, status: 419 });
        uploader.enqueue(track('a'));

        await uploader.flush();

        expect(uploader.paused).toBe(true);
        expect(uploader.queue).toHaveLength(1);
        expect(statuses.at(-1)).toEqual({ authLost: true, queueDepth: 1 });
    });

    test('a server error keeps the batch and retries after a backoff', async () => {
        fetch.mockResolvedValueOnce({ ok: false, status: 500 });
        uploader.enqueue(track('a'));

        await uploader.flush();
        expect(uploader.queue).toHaveLength(1);
        expect(statuses.at(-1).lastError).toContain('500');

        await vi.advanceTimersByTimeAsync(2000);

        expect(fetch).toHaveBeenCalledTimes(2);
        expect(uploader.queue).toEqual([]);
    });
});
