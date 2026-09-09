// One contract, two implementations: the in-memory store every higher spec
// runs against, and the IndexedDB adapter driven by fake-indexeddb. A case
// added here runs against both, so the double cannot drift from the real thing.
import { beforeEach, describe, expect, test } from 'vitest';
import 'fake-indexeddb/auto';
import { IDBFactory } from 'fake-indexeddb';
import { InMemoryNightStore } from '../src/nightStoreMemory.js';
import { IndexedDbNightStore, openNightStore } from '../src/nightStore.js';

const night = (id, startedAt) => ({
    id,
    name: `Night ${id}`,
    status: 'active',
    startedAt,
    endedAt: null,
    lastHeartbeatAt: startedAt,
    frameWidth: 1280,
    frameHeight: 720,
    settings: { procWidth: 320 },
    analytics: null,
    createdAt: startedAt,
    claimedSessionId: null,
    claimedAt: null,
});

const track = (nightId, clientTrackId, startOffsetMs) => ({
    nightId,
    clientTrackId,
    startOffsetMs,
    endOffsetMs: startOffsetMs + 1000,
    pointCount: 2,
    points: [[startOffsetMs, 1, 1], [startOffsetMs + 1000, 2, 2]],
    entryEdge: 'left',
    exitEdge: 'interior',
    startCrop: 'abc',
    endCrop: null,
    dismissedAt: null,
});

describe.each([
    ['memory', async () => new InMemoryNightStore()],
    ['indexeddb', () => openNightStore({ indexedDB: new IDBFactory() })],
])('night store (%s)', (_, makeStore) => {
    let store;

    beforeEach(async () => {
        store = await makeStore();
    });

    test('round-trips a night and reports an unknown id as null', async () => {
        await store.putNight(night('a', 100));

        expect(await store.getNight('a')).toEqual(night('a', 100));
        expect(await store.getNight('missing')).toBeNull();
    });

    test('lists nights newest first', async () => {
        await store.putNight(night('old', 100));
        await store.putNight(night('new', 300));
        await store.putNight(night('mid', 200));

        expect((await store.listNights()).map((n) => n.id)).toEqual(['new', 'mid', 'old']);
    });

    test('patches a night in place and returns the merged record', async () => {
        await store.putNight(night('a', 100));

        const patched = await store.patchNight('a', { status: 'completed', endedAt: 900 });

        expect(patched.status).toBe('completed');
        expect(patched.endedAt).toBe(900);
        expect((await store.getNight('a')).name).toBe('Night a');
        expect(await store.patchNight('missing', { status: 'completed' })).toBeNull();
    });

    test('keeps tracks per night, ordered by start, keyed by client id', async () => {
        await store.putTrack(track('a', 't2', 2000));
        await store.putTrack(track('a', 't1', 1000));
        await store.putTrack(track('b', 't1', 500));
        await store.putTrack({ ...track('a', 't1', 1000), endCrop: 'replaced' });

        const tracks = await store.listTracks('a');

        expect(tracks.map((t) => t.clientTrackId)).toEqual(['t1', 't2']);
        expect(tracks[0].endCrop).toBe('replaced');
    });

    test('patches a single track', async () => {
        await store.putTrack(track('a', 't1', 1000));

        expect((await store.patchTrack('a', 't1', { dismissedAt: 5 })).dismissedAt).toBe(5);
        expect((await store.listTracks('a'))[0].dismissedAt).toBe(5);
        expect(await store.patchTrack('a', 'missing', { dismissedAt: 5 })).toBeNull();
    });

    test('round-trips a binary blob', async () => {
        const bytes = new Uint8Array([255, 216, 255, 1, 2, 3]).buffer;
        await store.putBlob({ key: 'a/reference', nightId: 'a', bytes, type: 'image/jpeg' });

        const blob = await store.getBlob('a/reference');

        expect(blob.type).toBe('image/jpeg');
        expect([...new Uint8Array(blob.bytes)]).toEqual([255, 216, 255, 1, 2, 3]);
        expect(await store.getBlob('missing')).toBeNull();
    });

    test('deleting a night takes its tracks and blobs with it, and nothing else', async () => {
        await store.putNight(night('a', 100));
        await store.putNight(night('b', 200));
        await store.putTrack(track('a', 't1', 1000));
        await store.putTrack(track('b', 't1', 1000));
        await store.putBlob({ key: 'a/reference', nightId: 'a', bytes: new ArrayBuffer(1), type: 'image/jpeg' });
        await store.putBlob({ key: 'b/reference', nightId: 'b', bytes: new ArrayBuffer(1), type: 'image/jpeg' });

        await store.deleteNight('a');

        expect(await store.getNight('a')).toBeNull();
        expect(await store.listTracks('a')).toEqual([]);
        expect(await store.getBlob('a/reference')).toBeNull();
        expect(await store.getNight('b')).not.toBeNull();
        expect(await store.listTracks('b')).toHaveLength(1);
        expect(await store.getBlob('b/reference')).not.toBeNull();
    });

    test('hands back copies, so a caller cannot edit the store by accident', async () => {
        await store.putNight(night('a', 100));

        const read = await store.getNight('a');
        read.name = 'changed';

        expect((await store.getNight('a')).name).toBe('Night a');
    });
});

describe('openNightStore', () => {
    test('opens IndexedDB when it is available and says so', async () => {
        const store = await openNightStore({ indexedDB: new IDBFactory() });

        expect(store).toBeInstanceOf(IndexedDbNightStore);
        expect(store.volatile).toBe(false);
    });

    test('falls back to memory, marked volatile, when there is no IndexedDB', async () => {
        const store = await openNightStore({ indexedDB: null });

        expect(store).toBeInstanceOf(InMemoryNightStore);
        expect(store.volatile).toBe(true);
    });

    test('falls back to memory when IndexedDB refuses to open', async () => {
        const refusing = {
            open: () => {
                const request = { error: new Error('refused') };
                setTimeout(() => request.onerror?.(), 0);

                return request;
            },
        };

        expect(await openNightStore({ indexedDB: refusing })).toBeInstanceOf(InMemoryNightStore);
    });
});
