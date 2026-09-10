// The decisions the capture page makes that are this app's alone: which sink a
// page gets, what the reference-frame upload looks like, and what to say when
// the login behind it expires. Everything else the page decides — calibration
// outcomes, the countdown, the status line, the overlay boxes, the clock —
// lives in @mavware/bug-surveillance, so any consumer of the library gets it.

/**
 * Which night sink the page gets. A guest's night never leaves the device; a
 * logged-in user's night is uploaded as it happens so the dashboard can follow
 * it. The page says which in its config, and this is the only place that reads it.
 */
export function captureMode(config) {
    return config.mode === 'local' ? 'local' : 'server';
}

export function referenceStoreState(mode) {
    return mode === 'local' ? 'Saving reference frame…' : 'Uploading reference frame…';
}

export const AUTH_LOST_MESSAGE =
    'Your login session expired — log in again in another tab, then reload this page. Detected tracks are held in memory.';

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
 * How long a night runs before ending itself, in milliseconds, or null for a
 * night that runs until End night is pressed. Read off the checklist's "stop
 * tracking after" box and its hours field: an unticked box is null however the
 * field reads, and a ticked box with a blank, unreadable or non-positive number
 * is null too, so the worst a stray value can do is let the night run on.
 *
 * @param {{ enabled: boolean, hours: string | number }} choice
 * @returns {number | null}
 */
export function autoEndAfterMs({ enabled, hours }) {
    if (!enabled) {
        return null;
    }

    const parsed = Number(hours);

    if (!Number.isFinite(parsed) || parsed <= 0) {
        return null;
    }

    return Math.round(parsed * 60 * 60 * 1000);
}
