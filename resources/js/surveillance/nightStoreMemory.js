// The night store held in plain Maps. Two jobs: the runtime fallback when a
// browser refuses IndexedDB (some private modes), where a night lives only as
// long as the tab; and the double every spec above the store runs against.
// Values are cloned on the way in and out so nothing can pass by aliasing.

export class InMemoryNightStore {
    constructor() {
        this.nights = new Map();
        this.tracks = new Map();
        this.blobs = new Map();
        this.volatile = true;
    }

    async putNight(night) {
        this.nights.set(night.id, structuredClone(night));
    }

    async getNight(id) {
        const night = this.nights.get(id);

        return night === undefined ? null : structuredClone(night);
    }

    async patchNight(id, patch) {
        const night = this.nights.get(id);

        if (night === undefined) {
            return null;
        }

        const updated = { ...night, ...structuredClone(patch) };
        this.nights.set(id, updated);

        return structuredClone(updated);
    }

    async listNights() {
        return [...this.nights.values()]
            .sort((a, b) => b.startedAt - a.startedAt)
            .map((night) => structuredClone(night));
    }

    async deleteNight(id) {
        this.nights.delete(id);

        for (const [key, track] of this.tracks) {
            if (track.nightId === id) {
                this.tracks.delete(key);
            }
        }

        for (const [key, blob] of this.blobs) {
            if (blob.nightId === id) {
                this.blobs.delete(key);
            }
        }
    }

    async putTrack(track) {
        this.tracks.set(trackKey(track.nightId, track.clientTrackId), structuredClone(track));
    }

    async listTracks(nightId) {
        return [...this.tracks.values()]
            .filter((track) => track.nightId === nightId)
            .sort((a, b) => a.startOffsetMs - b.startOffsetMs)
            .map((track) => structuredClone(track));
    }

    async patchTrack(nightId, clientTrackId, patch) {
        const key = trackKey(nightId, clientTrackId);
        const track = this.tracks.get(key);

        if (track === undefined) {
            return null;
        }

        const updated = { ...track, ...structuredClone(patch) };
        this.tracks.set(key, updated);

        return structuredClone(updated);
    }

    async putBlob(blob) {
        this.blobs.set(blob.key, structuredClone(blob));
    }

    async getBlob(key) {
        const blob = this.blobs.get(key);

        return blob === undefined ? null : structuredClone(blob);
    }
}

function trackKey(nightId, clientTrackId) {
    return `${nightId} ${clientTrackId}`;
}
