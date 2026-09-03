import { describe, expect, test } from 'vitest';
import { BRIGHTNESS_BLOCK, calibrate, toGrayscale } from '../../../resources/js/surveillance/brightness.js';
import { makeFrame, stubCamera } from '../helpers.js';

describe('toGrayscale', () => {
    test('gives one luminance value per pixel', () => {
        expect(toGrayscale(makeFrame(4, 3, 0))).toHaveLength(12);
    });

    test('passes a neutral grey through unchanged', () => {
        expect(Array.from(toGrayscale(makeFrame(2, 1, 140)))).toEqual([140, 140]);
    });

    test('weights the channels by perceived brightness', () => {
        const frame = makeFrame(1, 1, 0);
        frame.data.set([255, 0, 0, 255]);

        expect(toGrayscale(frame)[0]).toBeCloseTo(76.245, 3);

        frame.data.set([0, 255, 0, 255]);
        expect(toGrayscale(frame)[0]).toBeCloseTo(149.685, 3);
    });
});

describe('calibrate', () => {
    // Short durations keep these fast; every assertion holds regardless of how
    // many sample iterations fit into the window.
    const options = [40, 5];

    test('blocks a scene too dark to see anything in', async () => {
        const camera = stubCamera([makeFrame(8, 8, 1)]);

        const result = await calibrate(camera, ...options);

        expect(result.tooDark).toBe(true);
        expect(result.dim).toBe(true);
        expect(result.meanLuminance).toBeLessThan(BRIGHTNESS_BLOCK);
    });

    test('warns about a dim room without blocking it', async () => {
        const camera = stubCamera([makeFrame(8, 8, 9)]);

        const result = await calibrate(camera, ...options);

        expect(result.tooDark).toBe(false);
        expect(result.dim).toBe(true);
    });

    test('accepts a lit room', async () => {
        const camera = stubCamera([makeFrame(8, 8, 120)]);

        const result = await calibrate(camera, ...options);

        expect(result.tooDark).toBe(false);
        expect(result.dim).toBe(false);
    });

    test('floors the motion threshold on a noise-free sensor', async () => {
        const camera = stubCamera([makeFrame(8, 8, 120)]);

        const result = await calibrate(camera, ...options);

        expect(result.diffThreshold).toBe(14);
    });

    test('caps the motion threshold on a wildly noisy sensor', async () => {
        const camera = stubCamera([makeFrame(8, 8, 0), makeFrame(8, 8, 255)]);

        const result = await calibrate(camera, ...options);

        expect(result.diffThreshold).toBe(40);
    });
});
