import { describe, expect, test } from 'vitest';
import {
    calibrationOutcome,
    cameraCheckLabel,
    countdownMessage,
    DIM_MESSAGE,
    formatClock,
    LARGE_MOTION_MESSAGE,
    overlayBoxes,
    TOO_DARK_MESSAGE,
    wakeLockMessage,
    watchingState,
} from '../src/captureLogic.js';

describe('calibrationOutcome', () => {
    test('refuses a pitch-black scene and says what to do about it', () => {
        expect(calibrationOutcome({ tooDark: true, dim: true })).toEqual({ blocked: true, banner: TOO_DARK_MESSAGE });
    });

    test('lets a dim scene through with a warning', () => {
        expect(calibrationOutcome({ tooDark: false, dim: true })).toEqual({ blocked: false, banner: DIM_MESSAGE });
    });

    test('says nothing about a properly lit scene', () => {
        expect(calibrationOutcome({ tooDark: false, dim: false })).toEqual({ blocked: false, banner: null });
    });

    test('lets a consumer word the banners their own way', () => {
        expect(calibrationOutcome({ tooDark: true }, { tooDark: 'Lights, please.' }).banner).toBe('Lights, please.');
        expect(calibrationOutcome({ tooDark: false, dim: true }, { dim: 'A bit dark.' }).banner).toBe('A bit dark.');
    });
});

describe('countdownMessage', () => {
    test('counts the seconds left to leave the room', () => {
        expect(countdownMessage(3)).toBe('Leave the room — starting in 3…');
    });
});

describe('overlayBoxes', () => {
    test('scales a detection from the processing canvas onto the overlay', () => {
        const [box] = overlayBoxes([{ box: { x: 10, y: 20, width: 4, height: 6 } }], {
            canvasWidth: 640,
            canvasHeight: 360,
            procWidth: 320,
            procHeight: 180,
        });

        expect(box).toEqual({ x: 16, y: 36, width: 16, height: 20 });
    });

    test('pads the outline so it sits around the blob, not on it', () => {
        const [box] = overlayBoxes([{ box: { x: 10, y: 10, width: 2, height: 2 } }], {
            canvasWidth: 100,
            canvasHeight: 100,
            procWidth: 100,
            procHeight: 100,
        });

        expect(box).toEqual({ x: 8, y: 8, width: 6, height: 6 });
    });

    test('handles a frame with nothing moving in it', () => {
        expect(overlayBoxes([], { canvasWidth: 1, canvasHeight: 1, procWidth: 1, procHeight: 1 })).toEqual([]);
    });
});

describe('wakeLockMessage', () => {
    test('names the iOS setting, and Low Power Mode which blocks the lock by itself', () => {
        const message = wakeLockMessage('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)');

        expect(message).toContain('Auto-Lock');
        expect(message).toContain('Low Power Mode');
    });

    test('names the Android setting', () => {
        expect(wakeLockMessage('Mozilla/5.0 (Linux; Android 14)')).toContain('Screen timeout');
    });

    test('falls back to system power settings for anything else', () => {
        expect(wakeLockMessage('Mozilla/5.0 (Macintosh)')).toContain('power settings');
    });
});

describe('cameraCheckLabel', () => {
    test('offers to open the preview while it is closed', () => {
        expect(cameraCheckLabel(false)).toBe('Check camera');
    });

    test('offers to close the preview while it is open', () => {
        expect(cameraCheckLabel(true)).toBe('Stop camera');
    });
});

describe('watchingState', () => {
    test('reads as plain watching while the room is quiet', () => {
        expect(watchingState(false)).toBe('Tracking');
    });

    test('says a large thing is being ignored, so silence does not look like a fault', () => {
        expect(watchingState(true)).toBe(LARGE_MOTION_MESSAGE);
        expect(LARGE_MOTION_MESSAGE).toContain('ignoring');
    });

    test('takes a consumer\'s own wording', () => {
        expect(watchingState(true, { largeMotion: 'Someone is in the room.' })).toBe('Someone is in the room.');
    });
});

describe('formatClock', () => {
    test('reads as hours, minutes and seconds', () => {
        expect(formatClock(65000)).toBe('00:01:05');
    });

    test('keeps counting past an hour, as an overnight session will', () => {
        expect(formatClock(8 * 3600 * 1000 + 30 * 60 * 1000)).toBe('08:30:00');
    });

    test('never shows a negative clock if the start time is ahead', () => {
        expect(formatClock(-5000)).toBe('00:00:00');
    });
});
