// Post a device-local night to the account import endpoint, chunk by chunk,
// and mark it claimed. Shared by the watch page, the local report and the
// dashboard; the decisions (chunking, messages) live in claimLogic.js.
import { buildImportChunks, bytesToBase64, importFailureMessage } from './claimLogic.js';
import { referenceBlobKey } from '@mavware/bug-surveillance';

export class ClaimError extends Error {
    constructor(message, status) {
        super(message);
        this.status = status;
    }
}

/**
 * @returns {Promise<{ sessionId: number, reportUrl: string }>}
 * @throws {ClaimError} with the message to show, and the HTTP status.
 */
export async function claimNight(store, nightId, { importUrl, csrfToken, room = null, fetch = globalThis.fetch }) {
    const night = await store.getNight(nightId);

    if (night === null) {
        throw new ClaimError('This night is no longer on this device.', 0);
    }

    const tracks = await store.listTracks(nightId);
    const reference = await store.getBlob(referenceBlobKey(nightId));
    const referenceBase64 = reference === null ? null : bytesToBase64(reference.bytes);

    let result = null;

    for (const chunk of buildImportChunks(night, tracks, referenceBase64, { room })) {
        const response = await fetch(importUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(chunk),
        });

        if (!response.ok) {
            throw new ClaimError(importFailureMessage(response.status), response.status);
        }

        result = await response.json();
    }

    await store.patchNight(nightId, { claimedSessionId: result.session_id, claimedAt: Date.now() });

    return { sessionId: result.session_id, reportUrl: result.report_url };
}
