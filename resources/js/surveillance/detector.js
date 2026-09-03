import { toGrayscale } from './brightness.js';

export const DEFAULT_PARAMS = {
    processFps: 6,
    procWidth: 320,
    bgAlpha: 0.03, // slow background adaptation so a pausing bug isn't absorbed instantly
    diffThreshold: 22, // overridden by calibration
    minArea: 4,
    maxArea: 300,
    // Moving pixels a whole frame may hold before it is dropped as a person, a pet
    // or the light changing: five roaches at maxArea. A body does not arrive as one
    // oversized blob but as dozens of roach-sized fragments (folds, edges, patterned
    // clothing), so per-blob limits never see it — only the frame total does.
    maxChangedArea: 1500,
    maxAspectRatio: 5,
    darkerThanBackground: true, // roaches are dark blobs against the scene
    darkMargin: 5,
};

// Frame-differencing blob detector: maintains a running-average background,
// thresholds the difference, and extracts roach-sized connected components.
export class Detector {
    constructor(params = {}) {
        this.params = { ...DEFAULT_PARAMS, ...params };
        this.background = null;
        this.mask = null;
        this.stack = null;
        // True while the last frame was dropped for holding something far larger
        // than a roach, so the page can say so instead of reporting silence.
        this.largeMotion = false;
    }

    // Returns blobs as [{cx, cy, area}] in processing-canvas coordinates.
    detect(imageData) {
        const { width, height } = imageData;
        const gray = toGrayscale(imageData);

        if (this.background === null) {
            this.background = Float32Array.from(gray);
            this.mask = new Uint8Array(gray.length);
            this.stack = new Int32Array(gray.length);

            return [];
        }

        const { bgAlpha, diffThreshold, darkerThanBackground, darkMargin, maxChangedArea } = this.params;
        const background = this.background;
        const mask = this.mask;
        let changedArea = 0;

        for (let i = 0; i < gray.length; i++) {
            const diff = gray[i] - background[i];
            const moving = Math.abs(diff) > diffThreshold;
            const changed = moving && (!darkerThanBackground || diff < -darkMargin);
            mask[i] = changed ? 1 : 0;
            changedArea += changed ? 1 : 0;
            background[i] += bgAlpha * diff;
        }

        // Something that big is not roaches, however many fragments it breaks into.
        // The background keeps adapting underneath it on purpose: a pet that curls
        // up and sleeps, or a chair left in shot, is absorbed within seconds and
        // detection resumes around it, rather than the night going blind until it
        // moves again.
        this.largeMotion = changedArea > maxChangedArea;

        if (this.largeMotion) {
            return [];
        }

        return this.extractBlobs(mask, width, height);
    }

    reset() {
        this.background = null;
        this.largeMotion = false;
    }

    // Iterative flood-fill connected components over the binary mask.
    extractBlobs(mask, width, height) {
        const { minArea, maxArea, maxAspectRatio } = this.params;
        const blobs = [];
        const stack = this.stack;

        for (let start = 0; start < mask.length; start++) {
            if (mask[start] !== 1) {
                continue;
            }

            let stackSize = 0;
            stack[stackSize++] = start;
            mask[start] = 2;

            let area = 0;
            let sumX = 0;
            let sumY = 0;
            let minX = width;
            let maxX = 0;
            let minY = height;
            let maxY = 0;

            while (stackSize > 0) {
                const index = stack[--stackSize];
                const x = index % width;
                const y = (index / width) | 0;

                area++;
                sumX += x;
                sumY += y;
                if (x < minX) minX = x;
                if (x > maxX) maxX = x;
                if (y < minY) minY = y;
                if (y > maxY) maxY = y;

                if (x > 0 && mask[index - 1] === 1) { mask[index - 1] = 2; stack[stackSize++] = index - 1; }
                if (x < width - 1 && mask[index + 1] === 1) { mask[index + 1] = 2; stack[stackSize++] = index + 1; }
                if (y > 0 && mask[index - width] === 1) { mask[index - width] = 2; stack[stackSize++] = index - width; }
                if (y < height - 1 && mask[index + width] === 1) { mask[index + width] = 2; stack[stackSize++] = index + width; }
            }

            const boxWidth = maxX - minX + 1;
            const boxHeight = maxY - minY + 1;
            const aspect = Math.max(boxWidth, boxHeight) / Math.max(1, Math.min(boxWidth, boxHeight));

            if (area >= minArea && area <= maxArea && aspect <= maxAspectRatio) {
                blobs.push({
                    cx: sumX / area,
                    cy: sumY / area,
                    area,
                    box: { x: minX, y: minY, width: boxWidth, height: boxHeight },
                });
            }
        }

        return blobs;
    }
}
