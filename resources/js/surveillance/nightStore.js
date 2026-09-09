// Where a guest's nights live: an IndexedDB database in this browser profile.
// Nothing here leaves the device. Three stores: nights, their tracks, and the
// one binary per night (the reference photo, as an ArrayBuffer; crops stay as
// base64 strings on the track, the same shape the server accepts, so claiming a
// night later re-sends them untouched).
//
// The interface is shared with InMemoryNightStore, which is both the fallback
// when IndexedDB is refused and the double the specs use. Keep the two in step.
import { InMemoryNightStore } from './nightStoreMemory.js';

export const STORE_NAME = 'bugtracker-local';
export const STORE_VERSION = 1;

/** Build the schema. Exported so a spec can run it against a fake factory. */
export function upgradeSchema(db) {
    if (!db.objectStoreNames.contains('nights')) {
        db.createObjectStore('nights', { keyPath: 'id' }).createIndex('byStartedAt', 'startedAt');
    }

    if (!db.objectStoreNames.contains('tracks')) {
        db.createObjectStore('tracks', { keyPath: ['nightId', 'clientTrackId'] }).createIndex('byNight', 'nightId');
    }

    if (!db.objectStoreNames.contains('blobs')) {
        db.createObjectStore('blobs', { keyPath: 'key' }).createIndex('byNight', 'nightId');
    }
}

/**
 * Open the store, or fall back to memory when the browser has no IndexedDB or
 * refuses to open it. The fallback is marked volatile so a page can warn that
 * the night will not survive the tab.
 */
export async function openNightStore({ indexedDB = globalThis.indexedDB } = {}) {
    if (indexedDB === undefined || indexedDB === null) {
        return new InMemoryNightStore();
    }

    try {
        const db = await new Promise((resolve, reject) => {
            const request = indexedDB.open(STORE_NAME, STORE_VERSION);
            request.onupgradeneeded = () => upgradeSchema(request.result);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
            request.onblocked = () => reject(new Error('IndexedDB open blocked'));
        });

        return new IndexedDbNightStore(db);
    } catch {
        return new InMemoryNightStore();
    }
}

export class IndexedDbNightStore {
    constructor(db) {
        this.db = db;
        this.volatile = false;
    }

    async putNight(night) {
        await this.write('nights', (store) => store.put(night));
    }

    async getNight(id) {
        return (await this.read('nights', (store) => store.get(id))) ?? null;
    }

    async patchNight(id, patch) {
        return this.patch('nights', id, patch);
    }

    async listNights() {
        const nights = await this.read('nights', (store) => store.getAll());

        return nights.sort((a, b) => b.startedAt - a.startedAt);
    }

    /** Remove a night with its tracks and blobs in one transaction. */
    async deleteNight(id) {
        const transaction = this.db.transaction(['nights', 'tracks', 'blobs'], 'readwrite');

        transaction.objectStore('nights').delete(id);
        deleteByIndex(transaction.objectStore('tracks').index('byNight'), id);
        deleteByIndex(transaction.objectStore('blobs').index('byNight'), id);

        await settled(transaction);
    }

    async putTrack(track) {
        await this.write('tracks', (store) => store.put(track));
    }

    async listTracks(nightId) {
        const tracks = await this.read('tracks', (store) => store.index('byNight').getAll(nightId));

        return tracks.sort((a, b) => a.startOffsetMs - b.startOffsetMs);
    }

    async patchTrack(nightId, clientTrackId, patch) {
        return this.patch('tracks', [nightId, clientTrackId], patch);
    }

    async putBlob(blob) {
        await this.write('blobs', (store) => store.put(blob));
    }

    async getBlob(key) {
        return (await this.read('blobs', (store) => store.get(key))) ?? null;
    }

    async patch(storeName, key, patch) {
        const transaction = this.db.transaction(storeName, 'readwrite');
        const store = transaction.objectStore(storeName);
        const existing = await request(store.get(key));

        if (existing === undefined) {
            return null;
        }

        const updated = { ...existing, ...patch };
        store.put(updated);
        await settled(transaction);

        return updated;
    }

    read(storeName, operation) {
        return request(operation(this.db.transaction(storeName, 'readonly').objectStore(storeName)));
    }

    async write(storeName, operation) {
        const transaction = this.db.transaction(storeName, 'readwrite');
        operation(transaction.objectStore(storeName));
        await settled(transaction);
    }
}

function request(idbRequest) {
    return new Promise((resolve, reject) => {
        idbRequest.onsuccess = () => resolve(idbRequest.result);
        idbRequest.onerror = () => reject(idbRequest.error);
    });
}

function settled(transaction) {
    return new Promise((resolve, reject) => {
        transaction.oncomplete = () => resolve();
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error ?? new Error('IndexedDB transaction aborted'));
    });
}

function deleteByIndex(index, value) {
    const cursorRequest = index.openKeyCursor(IDBKeyRange.only(value));

    cursorRequest.onsuccess = () => {
        const cursor = cursorRequest.result;

        if (cursor) {
            index.objectStore.delete(cursor.primaryKey);
            cursor.continue();
        }
    };
}
