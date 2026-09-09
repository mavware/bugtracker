// The decisions behind claiming a device-local night into an account: how a
// night is cut into import requests, and what to tell the user afterwards.
// Pure, so it is tested in node; claimNight.js is the plumbing that posts it.

/** The server accepts at most this many tracks per import request. */
export const IMPORT_CHUNK_SIZE = 50;

export const CLAIM_AUTH_LOST_MESSAGE =
    'Your login session expired — log in again in another tab, then try the import again. The night is still on this device.';

export const CLAIM_FAILED_MESSAGE = 'Could not save this night to your account';

/**
 * Cut a night into import bodies. Every chunk carries the night's metadata and
 * its local id, so any chunk can be sent again and the server merges it into
 * the same session; only the first carries the reference photo. A night with
 * no sightings still produces one chunk — an empty night is still a night.
 */
export function buildImportChunks(night, tracks, referenceBase64, { room = null } = {}) {
    const metadata = {
        local_id: night.id,
        started_at: new Date(night.startedAt).toISOString(),
        ended_at: new Date(night.endedAt ?? night.startedAt).toISOString(),
        aborted: night.status === 'aborted',
        room: room !== null && room.trim() !== '' ? room.trim() : null,
        frame_width: night.frameWidth,
        frame_height: night.frameHeight,
        settings: night.settings ?? {},
    };

    const importTracks = tracks.map((track) => ({
        client_track_id: track.clientTrackId,
        start_offset_ms: track.startOffsetMs,
        end_offset_ms: track.endOffsetMs,
        points: track.points,
        start_crop: track.startCrop ?? null,
        end_crop: track.endCrop ?? null,
        dismissed: track.dismissedAt != null,
    }));

    const chunks = [];

    for (let index = 0; index < Math.max(1, importTracks.length); index += IMPORT_CHUNK_SIZE) {
        chunks.push({
            ...metadata,
            reference_image: index === 0 ? referenceBase64 : null,
            tracks: importTracks.slice(index, index + IMPORT_CHUNK_SIZE),
        });
    }

    return chunks;
}

/** Raw base64 of a binary, without a data: prefix, in pieces btoa can take. */
export function bytesToBase64(arrayBuffer) {
    const bytes = new Uint8Array(arrayBuffer);
    let binary = '';

    for (let index = 0; index < bytes.length; index += 0x8000) {
        binary += String.fromCharCode(...bytes.subarray(index, index + 0x8000));
    }

    return btoa(binary);
}

/** Which message an import response deserves, or null when it went through. */
export function importFailureMessage(status) {
    if (status === 401 || status === 419) {
        return CLAIM_AUTH_LOST_MESSAGE;
    }

    return `${CLAIM_FAILED_MESSAGE} (HTTP ${status}). Nothing was lost — try again.`;
}

/** What the watch page says once a batch of nights has been imported. */
export function claimSummary({ imported, skipped, failed }) {
    const parts = [];

    if (imported > 0) {
        parts.push(`${imported} ${imported === 1 ? 'night' : 'nights'} saved to your account`);
    }

    if (skipped > 0) {
        parts.push(`${skipped} already saved`);
    }

    if (failed > 0) {
        parts.push(`${failed} could not be saved`);
    }

    return parts.length === 0 ? 'Nothing to import.' : `${parts.join(', ')}.`;
}
