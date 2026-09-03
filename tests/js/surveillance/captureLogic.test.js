import { describe, expect, test } from 'vitest';
import {
    buildReferenceForm,
    calibrationOutcome,
    DIM_MESSAGE,
    formatClock,
    overlayBoxes,
    TOO_DARK_MESSAGE,
    wakeLockMessage,
} from '../../../resources/js/surveillance/captureLogic.js';

describe('calibrationOutcome', () => {
    test('refuses a pitch-black scene and says what to do about it', () => {
        const outcome = calibrationOutcome({ tooDark: true, dim: true });

        expect(outcome.blocked).toBe(true);
        expect(outcome.banner).toBe(TOO_DARK_MESSAGE);
    });

    test('lets a dim scene through with a warning', () => {
        const outcome = calibrationOutcome({ tooDark: false, dim: true });

        expect(outcome.blocked).toBe(false);
        expect(outcome.banner).toBe(DIM_MESSAGE);
    });

    test('says nothing about a properly lit scene', () => {
        expect(calibrationOutcome({ tooDark: false, dim: false })).toEqual({
            blocked: false,
            banner: null,
        });
    });
});

describe('buildReferenceForm', () => {
    const form = () => buildReferenceForm({
        blob: new Blob(['jpeg'], { type: 'image/jpeg' }),
        frameWidth: 1280,
        frameHeight: 720,
        settings: { procWidth: 320, diffThreshold: 18 },
    });

    test('sends the frame dimensions the server validates against', () => {
        expect(form().get('frame_width')).toBe('1280');
        expect(form().get('frame_height')).toBe('720');
    });

    test('names the upload so it arrives as a jpeg', () => {
        expect(form().get('image').name).toBe('reference.jpg');
    });

    // The server reads these as a settings array; flat keys would be dropped.
    test('flattens each detection setting into a settings[key] entry', () => {
        const built = form();

        expect(built.get('settings[procWidth]')).toBe('320');
        expect(built.get('settings[diffThreshold]')).toBe('18');
        expect(built.get('procWidth')).toBeNull();
    });
});

describe('overlayBoxes', () => {
    const blob = { box: { x: 10, y: 20, width: 6, height: 8 } };

    test('scales a detection from the processing canvas onto the overlay', () => {
        const [box] = overlayBoxes([blob], {
            canvasWidth: 640,
            canvasHeight: 360,
            procWidth: 320,
            procHeight: 180,
        });

        // Doubled, and padded by two processing pixels on every side.
        expect(box).toEqual({ x: 16, y: 36, width: 20, height: 24 });
    });

    test('pads the outline so it sits around the blob, not on it', () => {
        const [box] = overlayBoxes([blob], {
            canvasWidth: 320,
            canvasHeight: 180,
            procWidth: 320,
            procHeight: 180,
        });

        expect(box).toEqual({ x: 8, y: 18, width: 10, height: 12 });
    });

    test('handles a frame with nothing moving in it', () => {
        expect(overlayBoxes([], { canvasWidth: 320, canvasHeight: 180, procWidth: 320, procHeight: 180 })).toEqual([]);
    });
});

describe('wakeLockMessage', () => {
    // Read on the device itself, in a dark room, by someone who must fix it now:
    // it has to name the setting, not just say the lock failed.
    test('names the iOS setting, and Low Power Mode which blocks the lock by itself', () => {
        const message = wakeLockMessage('Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15');

        expect(message).toContain('Auto-Lock');
        expect(message).toContain('Low Power Mode');
    });

    test('names the Android setting', () => {
        const message = wakeLockMessage('Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36');

        expect(message).toContain('Screen timeout');
        expect(message).not.toContain('Auto-Lock');
    });

    test('falls back to system power settings for anything else', () => {
        expect(wakeLockMessage('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)')).toContain('system power settings');
        expect(wakeLockMessage()).toContain('system power settings');
    });
});

describe('formatClock', () => {
    test('reads as hours, minutes and seconds', () => {
        expect(formatClock(0)).toBe('00:00:00');
        expect(formatClock(1000)).toBe('00:00:01');
        expect(formatClock(61_000)).toBe('00:01:01');
    });

    test('keeps counting past an hour, as an overnight session will', () => {
        expect(formatClock(9 * 3600 * 1000 + 5 * 60 * 1000 + 3000)).toBe('09:05:03');
        expect(formatClock(36 * 3600 * 1000)).toBe('36:00:00');
    });

    test('never shows a negative clock if the start time is ahead', () => {
        expect(formatClock(-5000)).toBe('00:00:00');
    });
});
