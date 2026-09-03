// The decisions the capture page makes, separated from the DOM plumbing that
// acts on them: what a calibration result means, what gets sent with the
// reference frame, where the debug boxes land, and how the clock reads.

export const TOO_DARK_MESSAGE =
    'The scene is pitch black — the camera cannot see anything. Add a nightlight or dim lamp, then start again.';

export const DIM_MESSAGE =
    'The scene is very dim. Detection will run, but a small extra light source would improve it.';

/**
 * Detection is frame differencing: anything that moves or changes colour reads as
 * a bug. A fan, a television, a charger LED or a swaying curtain will fill the
 * night with false sightings, so the checklist goes in front of the user while
 * they can still act on it rather than in a page they have already scrolled past.
 */
export const PREFLIGHT_MESSAGE = [
    'Before you start, check the room:',
    '',
    '• Turn on a light — a dim lamp or nightlight is enough, but the camera cannot see in pitch darkness.',
    '• Turn off fans, heaters, and anything else that moves.',
    '• Turn off televisions and screens, and cover blinking LEDs — changing colour reads as movement.',
    '• Draw the curtains if you can, so passing headlights do not sweep the room.',
    '',
    'Then leave the room. You have five seconds once you press OK.',
].join('\n');

/**
 * How long the room is left alone before anything is measured. The person who
 * pressed start must be out of shot first: in the reference frame they become
 * part of the background model, and walking out is recorded as a sighting.
 */
export const LEAVE_ROOM_SECONDS = 5;

export function countdownMessage(secondsLeft) {
    return `Leave the room — starting in ${secondsLeft}…`;
}

/**
 * What to say when the screen wake lock could not be taken. Naming the actual
 * setting matters here: this banner is read on the device itself, in a dark room,
 * by someone who has to fix it now or lose the night. Menu paths are kept to one
 * line because they drift between OS versions — the setting names survive longer
 * than the paths and are what a settings search box will match.
 */
export function wakeLockMessage(userAgent = '') {
    const prefix = 'This device would not keep its screen on by itself — ';

    if (/iPhone|iPad|iPod/i.test(userAgent)) {
        return `${prefix}set Auto-Lock to Never (Settings › Display & Brightness), and turn Low Power Mode off, which blocks the screen lock on its own.`;
    }

    if (/Android/i.test(userAgent)) {
        return `${prefix}set Screen timeout to its longest option (Settings › Display), and keep the device charging.`;
    }

    return `${prefix}turn off screen sleep in your system power settings so the display stays on all night.`;
}

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
