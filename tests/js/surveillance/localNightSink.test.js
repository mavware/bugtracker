import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { LOCAL_ID_PLACEHOLDER, referenceBlobKey } from '../../../resources/js/surveillance/localNight.js';
import { HEARTBEAT_INTERVAL_MS, LocalNightSink } from '../../../resources/js/surveillance/localNightSink.js';
import { InMemoryNightStore } from '../../../resources/js/surveillance/nightStoreMemory.js';

const REPORT_TEMPLATE = `https://bugtracker.test/watch/${LOCAL_ID_PLACEHOLDER}/report`;

const closedTrack = (id, startOffsetMs = 1000) => ({
    client_track_id: id,
    start_offset_ms: startOffsetMs,
    end_offset_ms: startOffsetMs + 2500,
    points: [[startOffsetMs, 5, 360], [startOffsetMs + 1000, 640, 360], [startOffsetMs + 2500, 640, 715]],
    start_crop: 'c3RhcnQ=',
    end_crop: null,
});

describe('LocalNightSink', () => {
    let store;
    let sink;
    let statuses;

    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 8, 8, 22, 30));
        vi.stubGlobal('navigator', { storage: { persist: vi.fn(async () => true) } });

        store = new InMemoryNightStore();
        statuses = [];
        sink = new LocalNightSink({ store, reportUrlTemplate: REPORT_TEMPLATE, onStatus: (status) => statuses.push(status) });
    });

    afterEach(() => {
        sink.stop();
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    async function startNight() {
        await sink.storeReference({
            blob: new Blob([new Uint8Array([255, 216, 255, 1])], { type: 'image/jpeg' }),
            frameWidth: 1280,
            frameHeight: 720,
            settings: { procWidth: 320 },
        });
    }

    test('storing the reference creates the night and keeps the photo, then asks for persistence', async () => {
        await startNight();

        const night = await store.getNight(sink.nightId);
        const blob = await store.getBlob(referenceBlobKey(sink.nightId));

        expect(night).toMatchObject({ status: 'active', frameWidth: 1280, frameHeight: 720, settings: { procWidth: 320 }, name: 'Night of Sep 8' });
        expect([...new Uint8Array(blob.bytes)]).toEqual([255, 216, 255, 1]);
        expect(blob.type).toBe('image/jpeg');
        expect(navigator.storage.persist).toHaveBeenCalled();
    });

    test('a closed track lands in the store with its edges, and the queue depth is reported', async () => {
        await startNight();

        sink.enqueue(closedTrack('t1'));
        expect(statuses.at(-1)).toEqual({ queueDepth: 1 });

        await sink.flush();

        expect(await store.listTracks(sink.nightId)).toMatchObject([{ clientTrackId: 't1', entryEdge: 'left', exitEdge: 'bottom' }]);
        expect(statuses.at(-1)).toEqual({ queueDepth: 0 });
    });

    test('a failed write is kept and retried on the next flush', async () => {
        await startNight();
        const putTrack = store.putTrack.bind(store);
        vi.spyOn(store, 'putTrack').mockRejectedValueOnce(new Error('quota'));

        sink.enqueue(closedTrack('t1'));
        await Promise.allSettled([...sink.pending]);

        expect(statuses.some((status) => status.lastError?.includes('quota'))).toBe(true);
        expect(await store.listTracks(sink.nightId)).toEqual([]);

        store.putTrack.mockImplementation(putTrack);
        await sink.flush();

        expect(await store.listTracks(sink.nightId)).toHaveLength(1);
    });

    test('a heartbeat is written every minute while running, so an interrupted night can be closed later', async () => {
        await startNight();
        sink.start();

        const startedAt = Date.now();
        await vi.advanceTimersByTimeAsync(HEARTBEAT_INTERVAL_MS + 5);

        expect((await store.getNight(sink.nightId)).lastHeartbeatAt).toBe(startedAt + HEARTBEAT_INTERVAL_MS);
    });

    test('ending the night closes it with analytics and points at the local report', async () => {
        await startNight();
        sink.enqueue(closedTrack('t1'));

        const result = await sink.end({ endedAtOffsetMs: 60000, aborted: false });
        const night = await store.getNight(sink.nightId);

        expect(result).toEqual({ ok: true, status: 200, reportUrl: `https://bugtracker.test/watch/${sink.nightId}/report` });
        expect(night.status).toBe('completed');
        expect(night.endedAt).toBe(night.startedAt + 60000);
        expect(night.analytics).toMatchObject({ track_count: 1, total_points: 3, duration_ms: 60000 });
        expect(night.analytics.entry_zones[0].edge).toBe('left');
    });

    test('a discarded night is marked aborted but keeps what it caught', async () => {
        await startNight();
        sink.enqueue(closedTrack('t1'));

        await sink.end({ endedAtOffsetMs: 1000, aborted: true });

        expect((await store.getNight(sink.nightId)).status).toBe('aborted');
        expect(await store.listTracks(sink.nightId)).toHaveLength(1);
    });
});
