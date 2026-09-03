import { describe, expect, test } from 'vitest';
import { Tracker } from '../../../resources/js/surveillance/tracker.js';

const SESSION_START = 1000;
const SCALE = 4;

function makeTracker(params = {}) {
    const closed = [];
    const crops = [];

    const tracker = new Tracker({
        scale: SCALE,
        sessionStartTime: SESSION_START,
        captureCrop: (x, y) => {
            crops.push([x, y]);

            return `crop:${x},${y}`;
        },
        onTrackClosed: (track) => closed.push(track),
        params,
    });

    return { tracker, closed, crops };
}

/** Walk a blob across the frame, one frame every 100ms. */
function walk(tracker, { from = 10, step = 5, frames = 6, y = 10 } = {}) {
    for (let frame = 0; frame < frames; frame++) {
        tracker.update([{ cx: from + step * frame, cy: y }], SESSION_START + frame * 100);
    }
}

/** Let the frames roll by with nothing in them. */
function idle(tracker, frames, startingAt = SESSION_START + 1000) {
    for (let frame = 0; frame < frames; frame++) {
        tracker.update([], startingAt + frame * 100);
    }
}

describe('Tracker', () => {
    test('a single sighting is only a candidate, not yet a track', () => {
        const { tracker, closed } = makeTracker();

        tracker.update([{ cx: 10, cy: 10 }], SESSION_START);

        expect(tracker.candidates).toHaveLength(1);
        expect(tracker.active).toHaveLength(0);
        expect(closed).toHaveLength(0);
    });

    test('a blob seen repeatedly is confirmed and gets an opening snapshot', () => {
        const { tracker, crops } = makeTracker();

        walk(tracker, { frames: 3 });

        expect(tracker.active).toHaveLength(1);
        expect(tracker.candidates).toHaveLength(0);
        expect(crops).toEqual([[80, 40]]);
    });

    test('a confirmed track is handed over once the bug leaves, in full-frame pixels', () => {
        const { tracker, closed } = makeTracker();

        walk(tracker);
        idle(tracker, 8);

        expect(closed).toHaveLength(1);
        expect(closed[0]).toMatchObject({
            start_offset_ms: 0,
            end_offset_ms: 500,
            points: [
                [0, 40, 40],
                [100, 60, 40],
                [200, 80, 40],
                [300, 100, 40],
                [400, 120, 40],
                [500, 140, 40],
            ],
            start_crop: 'crop:80,40',
            end_crop: 'crop:140,40',
        });
        expect(closed[0].client_track_id).toMatch(/^[0-9a-f-]{36}$/);
    });

    test('a track is not handed over until the bug has been gone a while', () => {
        const { tracker, closed } = makeTracker();

        walk(tracker);
        idle(tracker, 7);

        expect(closed).toHaveLength(0);

        idle(tracker, 1, SESSION_START + 2000);

        expect(closed).toHaveLength(1);
    });

    test('something that twitches in place is discarded as noise', () => {
        const { tracker, closed } = makeTracker();

        walk(tracker, { step: 0, frames: 10 });
        idle(tracker, 8);

        expect(closed).toHaveLength(0);
    });

    test('a track too short to be worth reporting is discarded', () => {
        const { tracker, closed } = makeTracker();

        walk(tracker, { frames: 3 });
        idle(tracker, 8);

        expect(closed).toHaveLength(0);
    });

    test('a blob that jumps too far is a new track rather than the same one', () => {
        const { tracker } = makeTracker();

        tracker.update([{ cx: 10, cy: 10 }], SESSION_START);
        tracker.update([{ cx: 200, cy: 200 }], SESSION_START + 100);

        expect(tracker.candidates).toHaveLength(2);
    });

    test('two bugs at once stay two tracks', () => {
        const { tracker, closed } = makeTracker();

        for (let frame = 0; frame < 6; frame++) {
            tracker.update([
                { cx: 10 + 5 * frame, cy: 10 },
                { cx: 200 + 5 * frame, cy: 200 },
            ], SESSION_START + frame * 100);
        }

        expect(tracker.active).toHaveLength(2);

        tracker.flush();

        expect(closed).toHaveLength(2);
        expect(closed[0].points[0]).toEqual([0, 40, 40]);
        expect(closed[1].points[0]).toEqual([0, 800, 800]);
    });

    test('ending the night hands over whatever is still open', () => {
        const { tracker, closed } = makeTracker();

        walk(tracker);

        expect(closed).toHaveLength(0);

        tracker.flush();

        expect(closed).toHaveLength(1);
        expect(tracker.active).toHaveLength(0);
        expect(tracker.candidates).toHaveLength(0);
    });

    test('a track that runs too long is cut short so uploads stay small', () => {
        const { tracker, closed } = makeTracker({ maxPointsPerTrack: 5 });

        walk(tracker, { frames: 5 });

        expect(closed).toHaveLength(1);
        expect(closed[0].points).toHaveLength(5);
        expect(tracker.active).toHaveLength(0);
    });

    test('offsets never go negative when a frame arrives before the session start', () => {
        const { tracker, closed } = makeTracker();

        tracker.update([{ cx: 10, cy: 10 }], SESSION_START - 500);
        walk(tracker, { frames: 6 });
        tracker.flush();

        expect(closed[0].start_offset_ms).toBe(0);
    });
});
