import { toGrayscale } from './brightness.js';

export const DEFAULT_PARAMS = {
    processFps: 6,
    procWidth: 320,
    bgAlpha: 0.03, // slow background adaptation so a pausing bug isn't absorbed instantly
    diffThreshold: 22, // overridden by calibration
    minArea: 4,
    maxArea: 300,
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

        const { bgAlpha, diffThreshold, darkerThanBackground, darkMargin } = this.params;
        const background = this.background;
        const mask = this.mask;

        for (let i = 0; i < gray.length; i++) {
            const diff = gray[i] - background[i];
            const moving = Math.abs(diff) > diffThreshold;
            mask[i] = moving && (!darkerThanBackground || diff < -darkMargin) ? 1 : 0;
            background[i] += bgAlpha * diff;
        }

        return this.extractBlobs(mask, width, height);
    }

    reset() {
        this.background = null;
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
