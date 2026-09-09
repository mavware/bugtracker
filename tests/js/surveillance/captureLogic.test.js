import { describe, expect, test } from 'vitest';
import {
    AUTH_LOST_MESSAGE,
    buildReferenceForm,
    captureMode,
    referenceStoreState,
} from '../../../resources/js/surveillance/captureLogic.js';

describe('buildReferenceForm', () => {
    const form = buildReferenceForm({
        blob: new Blob(['jpeg'], { type: 'image/jpeg' }),
        frameWidth: 1280,
        frameHeight: 720,
        settings: { procWidth: 320, diffThreshold: 22 },
    });

    test('sends the frame dimensions the server validates against', () => {
        expect(form.get('frame_width')).toBe('1280');
        expect(form.get('frame_height')).toBe('720');
    });

    test('names the upload so it arrives as a jpeg', () => {
        expect(form.get('image')).toBeInstanceOf(Blob);
        expect(form.get('image').name).toBe('reference.jpg');
    });

    test('flattens each detection setting into a settings[key] entry', () => {
        expect(form.get('settings[procWidth]')).toBe('320');
        expect(form.get('settings[diffThreshold]')).toBe('22');
    });
});

describe('captureMode', () => {
    test('is local only when the page says so, and server otherwise', () => {
        expect(captureMode({ mode: 'local' })).toBe('local');
        expect(captureMode({ mode: 'server' })).toBe('server');
        expect(captureMode({})).toBe('server');
    });

    test('names what the page is doing with the reference frame in each mode', () => {
        expect(referenceStoreState('local')).toBe('Saving reference frame…');
        expect(referenceStoreState('server')).toBe('Uploading reference frame…');
    });

    test('tells a logged-out user their tracks are still held', () => {
        expect(AUTH_LOST_MESSAGE).toContain('held in memory');
    });
});
