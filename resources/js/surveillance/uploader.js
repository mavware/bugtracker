// Queues closed tracks and posts them in batches with retry. Tracks stay
// queued until the server accepts them, so a flaky connection overnight
// loses nothing; duplicates are idempotent server-side.
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
