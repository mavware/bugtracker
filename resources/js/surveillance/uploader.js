import { buildReferenceForm } from './captureLogic.js';

// The night sink for a logged-in user: queues closed tracks and posts them in
// batches with retry. Tracks stay queued until the server accepts them, so a
// flaky connection overnight loses nothing; duplicates are idempotent
// server-side. LocalNightSink answers the same calls for a guest — keep the two
// interfaces identical, capture.js does not know which it holds.
export class Uploader {
    constructor({ routes, csrfToken, onStatus }) {
        this.routes = routes;
        this.csrfToken = csrfToken;
        this.onStatus = onStatus ?? (() => {});
        this.queue = [];
        this.flushIntervalMs = 20000;
        this.batchSize = 10;
        this.backoffMs = 1000;
        this.flushing = false;
        this.paused = false;
        this.timers = [];
    }

    /** Upload the reference frame, which is what starts the session server-side. */
    async storeReference({ blob, frameWidth, frameHeight, settings }) {
        const response = await fetch(this.routes.reference, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
            body: buildReferenceForm({ blob, frameWidth, frameHeight, settings }),
        });

        if (!response.ok) {
            throw new Error(`Could not start the session (HTTP ${response.status}).`);
        }
    }

    /** End the session and learn where its report is. */
    async end({ endedAtOffsetMs, aborted }) {
        const response = await this.post(this.routes.end, {
            ended_at_offset_ms: endedAtOffsetMs,
            aborted,
        });

        return {
            ok: response.ok,
            status: response.status,
            reportUrl: response.ok ? (await response.json()).report_url : null,
        };
    }

    start() {
        this.timers.push(setInterval(() => this.flush(), this.flushIntervalMs));
        this.timers.push(setInterval(() => this.heartbeat(), 60000));
    }

    stop() {
        this.timers.forEach(clearInterval);
        this.timers = [];
    }

    enqueue(track) {
        this.queue.push(track);
        this.onStatus({ queueDepth: this.queue.length });

        if (this.queue.length >= this.batchSize) {
            this.flush();
        }
    }

    async flush({ keepalive = false } = {}) {
        if (this.flushing || this.paused || this.queue.length === 0) {
            return;
        }

        this.flushing = true;

        try {
            while (this.queue.length > 0) {
                const batch = this.queue.slice(0, 50);
                const response = await this.post(this.routes.tracks, { tracks: batch }, { keepalive });

                if (!response.ok) {
                    if (response.status === 401 || response.status === 419) {
                        this.paused = true;
                        this.onStatus({ authLost: true, queueDepth: this.queue.length });

                        return;
                    }

                    throw new Error(`Track upload failed with ${response.status}`);
                }

                // Accepted and duplicate tracks are both safely persisted.
                this.queue.splice(0, batch.length);
                this.backoffMs = 1000;
                this.onStatus({ queueDepth: this.queue.length });
            }
        } catch (error) {
            this.onStatus({ queueDepth: this.queue.length, lastError: String(error) });
            this.backoffMs = Math.min(60000, this.backoffMs * 2);
            setTimeout(() => this.flush(), this.backoffMs);
        } finally {
            this.flushing = false;
        }
    }

    async heartbeat() {
        if (this.paused) {
            return;
        }

        try {
            await this.post(this.routes.heartbeat, {});
        } catch {
            // A missed heartbeat is harmless; the next one retries.
        }
    }

    post(url, body, { keepalive = false } = {}) {
        return fetch(url, {
            method: 'POST',
            keepalive,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken,
            },
            body: JSON.stringify(body),
        });
    }
}
