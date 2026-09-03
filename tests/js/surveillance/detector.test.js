import { beforeEach, describe, expect, test } from 'vitest';
import { Detector } from '../../../resources/js/surveillance/detector.js';
import { makeFrame, paintRect } from '../helpers.js';

const SIZE = 12;
const SCENE = 200;

/** A lit room with nothing moving in it. */
function emptyScene() {
    return makeFrame(SIZE, SIZE, SCENE);
}

/** The same room with a dark, roach-sized patch in it. */
function sceneWithBlob(x, y, width, height, value = 100) {
    return paintRect(emptyScene(), x, y, width, height, value);
}

describe('Detector', () => {
    let detector;

    beforeEach(() => {
        detector = new Detector();
    });

    test('reports nothing on the first frame, which only seeds the background', () => {
        expect(detector.detect(sceneWithBlob(2, 2, 3, 3))).toEqual([]);
    });

    test('finds a dark blob against the settled background', () => {
        detector.detect(emptyScene());

        const blobs = detector.detect(sceneWithBlob(2, 2, 3, 3));

        expect(blobs).toHaveLength(1);
        expect(blobs[0]).toMatchObject({
            cx: 3,
            cy: 3,
            area: 9,
            box: { x: 2, y: 2, width: 3, height: 3 },
        });
    });

    test('ignores something brighter than the background, because roaches are dark', () => {
        detector.detect(emptyScene());

        expect(detector.detect(sceneWithBlob(2, 2, 3, 3, 255))).toEqual([]);
    });

    test('finds a brighter blob once the darker-than-background filter is off', () => {
        const permissive = new Detector({ darkerThanBackground: false });
        permissive.detect(emptyScene());

        expect(permissive.detect(sceneWithBlob(2, 2, 3, 3, 255))).toHaveLength(1);
    });

    test('discards specks below the minimum area', () => {
        detector.detect(emptyScene());

        expect(detector.detect(sceneWithBlob(5, 5, 1, 1))).toEqual([]);
    });

    test('discards anything larger than a roach', () => {
        const fussy = new Detector({ maxArea: 5 });
        fussy.detect(emptyScene());

        expect(fussy.detect(sceneWithBlob(2, 2, 3, 3))).toEqual([]);
    });

    test('drops the whole frame when far more of it moves than a few roaches could account for', () => {
        // A person is not one oversized blob but many roach-sized fragments, so the
        // small patch here would survive the per-blob limits on its own.
        const wary = new Detector({ maxArea: 9, maxChangedArea: 30 });
        wary.detect(emptyScene());

        const frame = paintRect(sceneWithBlob(0, 0, 6, 6), 9, 9, 2, 2, 100);

        expect(wary.detect(frame)).toEqual([]);
        expect(wary.largeMotion).toBe(true);
    });

    test('reports the roach again once the large thing has left', () => {
        const wary = new Detector({ maxArea: 9, maxChangedArea: 30 });
        wary.detect(emptyScene());
        wary.detect(paintRect(sceneWithBlob(0, 0, 6, 6), 9, 9, 2, 2, 100));

        expect(wary.detect(sceneWithBlob(9, 9, 2, 2))).toHaveLength(1);
        expect(wary.largeMotion).toBe(false);
    });

    test('keeps absorbing a large thing that stays put, so detection resumes around it', () => {
        const wary = new Detector({ maxArea: 9, maxChangedArea: 30 });
        wary.detect(emptyScene());

        for (let frame = 0; frame < 80; frame++) {
            wary.detect(sceneWithBlob(0, 0, 6, 6));
        }

        expect(wary.largeMotion).toBe(false);
        expect(wary.detect(paintRect(sceneWithBlob(0, 0, 6, 6), 9, 9, 2, 2, 100))).toHaveLength(1);
    });

    test('discards long thin shapes, which are shadows and edges rather than bugs', () => {
        detector.detect(emptyScene());

        expect(detector.detect(sceneWithBlob(4, 2, 1, 8))).toEqual([]);
    });

    test('keeps two separated blobs apart', () => {
        detector.detect(emptyScene());

        const frame = paintRect(sceneWithBlob(1, 1, 2, 2), 8, 8, 2, 2, 100);

        expect(detector.detect(frame)).toHaveLength(2);
    });

    test('joins an L-shaped region into a single blob', () => {
        detector.detect(emptyScene());

        const frame = paintRect(sceneWithBlob(2, 2, 3, 1), 2, 3, 1, 2, 100);
        const blobs = detector.detect(frame);

        expect(blobs).toHaveLength(1);
        expect(blobs[0].area).toBe(5);
    });

    test('treats diagonally touching regions as separate, since it walks four neighbours', () => {
        detector.detect(emptyScene());

        const frame = paintRect(sceneWithBlob(2, 2, 2, 2), 4, 4, 2, 2, 100);

        expect(detector.detect(frame)).toHaveLength(2);
    });

    test('lets a bug that stops moving fade into the background', () => {
        detector.detect(emptyScene());

        expect(detector.detect(sceneWithBlob(2, 2, 3, 3))).toHaveLength(1);

        for (let frame = 0; frame < 80; frame++) {
            detector.detect(sceneWithBlob(2, 2, 3, 3));
        }

        expect(detector.detect(sceneWithBlob(2, 2, 3, 3))).toEqual([]);
    });

    test('re-seeds the background after a reset', () => {
        detector.detect(emptyScene());
        detector.reset();

        expect(detector.largeMotion).toBe(false);

        expect(detector.detect(sceneWithBlob(2, 2, 3, 3))).toEqual([]);
        expect(detector.detect(sceneWithBlob(2, 2, 3, 3))).toEqual([]);
    });
});
