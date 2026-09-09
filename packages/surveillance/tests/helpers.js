// Minimal stand-ins for the browser objects the detection modules consume, so
// these tests need no canvas and no DOM. A neutral-grey pixel survives the
// luminance weights exactly (0.299 + 0.587 + 0.114 = 1), which keeps the
// expected values in the tests readable.

export function makeFrame(width, height, fill = 0) {
    const data = new Uint8ClampedArray(width * height * 4);

    for (let i = 0; i < width * height; i++) {
        data[i * 4] = fill;
        data[i * 4 + 1] = fill;
        data[i * 4 + 2] = fill;
        data[i * 4 + 3] = 255;
    }

    return { width, height, data };
}

export function paintRect(frame, x, y, width, height, value) {
    for (let row = y; row < y + height; row++) {
        for (let column = x; column < x + width; column++) {
            const offset = (row * frame.width + column) * 4;
            frame.data[offset] = value;
            frame.data[offset + 1] = value;
            frame.data[offset + 2] = value;
        }
    }

    return frame;
}

/**
 * A camera whose frames cycle, so calibration sees either a steady scene or a
 * noisy one depending on how many frames are handed over.
 */
export function stubCamera(frames) {
    let index = 0;

    return {
        grabProcessedFrame: () => frames[index++ % frames.length],
    };
}
