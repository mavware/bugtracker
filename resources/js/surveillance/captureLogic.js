// The decisions the capture page makes, separated from the DOM plumbing that
// acts on them: what a calibration result means, what gets sent with the
// reference frame, where the debug boxes land, and how the clock reads.

export const TOO_DARK_MESSAGE =
    'The scene is pitch black — the camera cannot see anything. Add a nightlight or dim lamp, then start again.';

export const DIM_MESSAGE =
    'The scene is very dim. Detection will run, but a small extra light source would improve it.';

/**
 * Whether a calibrated scene can be watched, and what to tell the user about it.
 * A pitch-black room is refused outright: with nothing to see, every frame is
 * sensor noise and the night would be wasted.
 */
export function calibrationOutcome(calibration) {
    if (calibration.tooDark) {
        return { blocked: true, banner: TOO_DARK_MESSAGE };
    }

    return { blocked: false, banner: calibration.dim ? DIM_MESSAGE : null };
}

/**
 * The multipart body for the reference frame. Detection settings are flattened
 * into settings[key] entries, which is the shape the server's validation expects.
 */
export function buildReferenceForm({ blob, frameWidth, frameHeight, settings }) {
    const form = new FormData();

    form.append('image', blob, 'reference.jpg');
    form.append('frame_width', frameWidth);
    form.append('frame_height', frameHeight);

    for (const [key, value] of Object.entries(settings)) {
        form.append(`settings[${key}]`, value);
    }

    return form;
}

/**
 * Detection boxes scaled from the small processing canvas up to the on-screen
 * overlay, padded slightly so the outline sits around the blob rather than on it.
 */
export function overlayBoxes(blobs, { canvasWidth, canvasHeight, procWidth, procHeight }) {
    const scaleX = canvasWidth / procWidth;
    const scaleY = canvasHeight / procHeight;

    return blobs.map((blob) => ({
        x: (blob.box.x - 2) * scaleX,
        y: (blob.box.y - 2) * scaleY,
        width: (blob.box.width + 4) * scaleX,
        height: (blob.box.height + 4) * scaleY,
    }));
}

/** Milliseconds as HH:MM:SS, for a clock that may run all night. */
export function formatClock(ms) {
    const totalSeconds = Math.max(0, Math.floor(ms / 1000));
    const hours = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
    const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
    const seconds = String(totalSeconds % 60).padStart(2, '0');

    return `${hours}:${minutes}:${seconds}`;
}
