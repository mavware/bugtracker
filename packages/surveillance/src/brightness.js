// Calibrates against the actual night-time scene: measures mean luminance to
// warn when the room is too dark, and the sensor noise floor so the motion
// threshold adapts to this camera in this light.
export const BRIGHTNESS_WARN = 12;
export const BRIGHTNESS_BLOCK = 5;

export async function calibrate(camera, durationMs = 3000, sampleIntervalMs = 200) {
    const luminances = [];
    const diffs = [];
    let previous = null;

    const deadline = performance.now() + durationMs;

    while (performance.now() < deadline) {
        const frame = camera.grabProcessedFrame();
        const gray = toGrayscale(frame);

        luminances.push(mean(gray));

        if (previous !== null) {
            for (let i = 0; i < gray.length; i += 7) {
                diffs.push(Math.abs(gray[i] - previous[i]));
            }
        }

        previous = gray;
        await new Promise((resolve) => setTimeout(resolve, sampleIntervalMs));
    }

    const meanLuminance = mean(luminances);
    const diffMean = mean(diffs);
    const diffStd = Math.sqrt(mean(diffs.map((d) => (d - diffMean) ** 2)));

    return {
        meanLuminance,
        tooDark: meanLuminance < BRIGHTNESS_BLOCK,
        dim: meanLuminance < BRIGHTNESS_WARN,
        diffThreshold: Math.min(40, Math.max(14, Math.round(diffMean + 4 * diffStd))),
    };
}

export function toGrayscale(imageData) {
    const { data } = imageData;
    const gray = new Float32Array(data.length / 4);

    for (let i = 0; i < gray.length; i++) {
        const o = i * 4;
        gray[i] = 0.299 * data[o] + 0.587 * data[o + 1] + 0.114 * data[o + 2];
    }

    return gray;
}

function mean(values) {
    if (values.length === 0) {
        return 0;
    }

    let sum = 0;
    for (const value of values) {
        sum += value;
    }

    return sum / values.length;
}
