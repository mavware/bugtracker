export const TRACKER_DEFAULTS = {
    maxMatchDistance: 40, // proc-px per frame
    confirmAfterHits: 3,
    closeAfterMisses: 8,
    minPoints: 5,
    minDisplacement: 10, // proc-px; discard jitter that never went anywhere
    maxPointsPerTrack: 3000,
    maxTrackDurationMs: 5 * 60 * 1000,
};

// Associates per-frame blobs into tracks via nearest-neighbor matching, and
// hands closed tracks (scaled to full-frame pixels) to the onTrackClosed
// callback for upload.
export class Tracker {
    constructor({ scale, sessionStartTime, captureCrop, onTrackClosed, params = {} }) {
        this.params = { ...TRACKER_DEFAULTS, ...params };
        this.scale = scale;
        this.sessionStartTime = sessionStartTime;
        this.captureCrop = captureCrop;
        this.onTrackClosed = onTrackClosed;
        this.candidates = [];
        this.active = [];
        this.closedCount = 0;
    }

    update(blobs, now = Date.now()) {
        const offsetMs = Math.max(0, Math.round(now - this.sessionStartTime));
        const unmatched = new Set(blobs.map((_, index) => index));

        for (const track of [...this.active, ...this.candidates]) {
            const matchIndex = this.nearestBlob(track, blobs, unmatched);

            if (matchIndex === null) {
                track.misses++;
                continue;
            }

            unmatched.delete(matchIndex);
            const blob = blobs[matchIndex];
            track.misses = 0;
            track.hits++;
            track.lastX = blob.cx;
            track.lastY = blob.cy;
            track.points.push([offsetMs, Math.round(blob.cx * this.scale), Math.round(blob.cy * this.scale)]);
        }

        for (const index of unmatched) {
            const blob = blobs[index];
            this.candidates.push({
                id: crypto.randomUUID(),
                points: [[offsetMs, Math.round(blob.cx * this.scale), Math.round(blob.cy * this.scale)]],
                lastX: blob.cx,
                lastY: blob.cy,
                hits: 1,
                misses: 0,
                startCrop: null,
                endCrop: null,
            });
        }

        this.promoteCandidates();
        this.closeStaleTracks(offsetMs);
    }

    promoteCandidates() {
        const { confirmAfterHits, closeAfterMisses } = this.params;

        this.candidates = this.candidates.filter((candidate) => {
            if (candidate.hits >= confirmAfterHits) {
                const [, x, y] = candidate.points[candidate.points.length - 1];
                candidate.startCrop = this.captureCrop(x, y);
                this.active.push(candidate);

                return false;
            }

            return candidate.misses < closeAfterMisses;
        });
    }

    closeStaleTracks(nowOffsetMs) {
        const { closeAfterMisses, maxPointsPerTrack, maxTrackDurationMs } = this.params;

        this.active = this.active.filter((track) => {
            const durationMs = nowOffsetMs - track.points[0][0];
            const stale = track.misses >= closeAfterMisses;
            const oversized = track.points.length >= maxPointsPerTrack || durationMs >= maxTrackDurationMs;

            if (!stale && !oversized) {
                return true;
            }

            this.closeTrack(track);

            return false;
        });
    }

    closeTrack(track) {
        const { minPoints, minDisplacement } = this.params;

        if (track.points.length < minPoints || this.displacement(track) < minDisplacement * this.scale) {
            return;
        }

        const [, endX, endY] = track.points[track.points.length - 1];
        track.endCrop = this.captureCrop(endX, endY);
        this.closedCount++;

        this.onTrackClosed({
            client_track_id: track.id,
            start_offset_ms: track.points[0][0],
            end_offset_ms: track.points[track.points.length - 1][0],
            points: track.points,
            start_crop: track.startCrop,
            end_crop: track.endCrop,
        });
    }

    // Close everything still open (used when the night ends).
    flush() {
        this.active.forEach((track) => this.closeTrack(track));
        this.active = [];
        this.candidates = [];
    }

    nearestBlob(track, blobs, unmatched) {
        let best = null;
        let bestDistance = this.params.maxMatchDistance;

        for (const index of unmatched) {
            const blob = blobs[index];
            const distance = Math.hypot(blob.cx - track.lastX, blob.cy - track.lastY);

            if (distance <= bestDistance) {
                best = index;
                bestDistance = distance;
            }
        }

        return best;
    }

    displacement(track) {
        const [, startX, startY] = track.points[0];
        const [, endX, endY] = track.points[track.points.length - 1];

        return Math.hypot(endX - startX, endY - startY);
    }
}
