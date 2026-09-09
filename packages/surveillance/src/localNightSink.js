// The night sink for a guest: the same six calls capture.js makes on the
// Uploader, answered by the browser's own store instead of the server. Nothing
// here touches the network. The reference photo, every closed track and the
// end-of-night summary land in the night store, and the report URL handed back
// points at the local report page.
import {
    buildLocalNight,
    localTrackFromClosed,
    nightAnalytics,
    referenceBlobKey,
    reportUrlFor,
} from './localNight.js';

export const HEARTBEAT_INTERVAL_MS = 60000;

export class LocalNightSink {
    constructor({ store, reportUrlTemplate, onStatus }) {
        this.store = store;
        this.reportUrlTemplate = reportUrlTemplate;
        this.onStatus = onStatus ?? (() => {});
        this.nightId = null;
        this.night = null;
        this.pending = new Set();
        this.retry = [];
        this.timers = [];
    }

    /** Create the night and keep its reference photo. The night's clock starts here. */
    async storeReference({ blob, frameWidth, frameHeight, settings }) {
        this.nightId = crypto.randomUUID();
        this.night = buildLocalNight({ id: this.nightId, startedAt: Date.now(), frameWidth, frameHeight, settings });

        await this.store.putNight(this.night);
        await this.store.putBlob({
            key: referenceBlobKey(this.nightId),
            nightId: this.nightId,
            bytes: await blob.arrayBuffer(),
            type: blob.type || 'image/jpeg',
        });

        // Ask the browser not to evict a night's worth of watching under storage
        // pressure. Not every browser grants it, and there is nothing to do if not.
        navigator.storage?.persist?.().catch(() => {});
    }

    /** A local heartbeat, so an interrupted night can be closed at the right time later. */
    start() {
        this.timers.push(setInterval(() => this.heartbeat(), HEARTBEAT_INTERVAL_MS));
    }

    stop() {
        this.timers.forEach(clearInterval);
        this.timers = [];
    }

    enqueue(track) {
        this.write(localTrackFromClosed(track, this.nightId, this.night.frameWidth, this.night.frameHeight));
    }

    write(localTrack) {
        const attempt = this.store
            .putTrack(localTrack)
            .catch((error) => {
                // Kept for the next flush, as the uploader keeps a failed batch.
                this.retry.push(localTrack);
                this.onStatus({ queueDepth: this.pending.size + this.retry.length, lastError: String(error) });
            })
            .finally(() => {
                this.pending.delete(attempt);
                this.onStatus({ queueDepth: this.pending.size + this.retry.length });
            });

        this.pending.add(attempt);
        this.onStatus({ queueDepth: this.pending.size + this.retry.length });
    }

    async flush() {
        await Promise.allSettled([...this.pending]);

        const leftovers = this.retry;
        this.retry = [];
        leftovers.forEach((localTrack) => this.write(localTrack));

        if (leftovers.length > 0) {
            await Promise.allSettled([...this.pending]);
        }
    }

    async heartbeat() {
        try {
            await this.store.patchNight(this.nightId, { lastHeartbeatAt: Date.now() });
        } catch {
            // A missed heartbeat only shifts where an interrupted night is closed.
        }
    }

    /** Close the night, summarise it, and say where its report is. */
    async end({ endedAtOffsetMs, aborted }) {
        await this.flush();

        const endedAt = this.night.startedAt + endedAtOffsetMs;
        const tracks = await this.store.listTracks(this.nightId);
        const closed = { ...this.night, endedAt };

        this.night = await this.store.patchNight(this.nightId, {
            status: aborted ? 'aborted' : 'completed',
            endedAt,
            lastHeartbeatAt: endedAt,
            analytics: nightAnalytics(closed, tracks),
        });

        return { ok: true, status: 200, reportUrl: reportUrlFor(this.reportUrlTemplate, this.nightId) };
    }
}
