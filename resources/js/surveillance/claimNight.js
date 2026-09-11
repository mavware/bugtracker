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
 * Once the account has the night, the local copy is removed: it was only ever
 * a stand-in for the account, and keeping it would list the same night twice.
 * A page still showing that copy passes keepLocalCopy, and the night is marked
 * claimed instead so it can link to the account's report.
 *
 * @returns {Promise<{ sessionId: number, reportUrl: string }>}
 * @throws {ClaimError} with the message to show, and the HTTP status.
 */
export async function claimNight(store, nightId, { importUrl, csrfToken, room = null, customerId = null, keepLocalCopy = false, fetch = globalThis.fetch }) {
    const night = await store.getNight(nightId);

    if (night === null) {
        throw new ClaimError('This night is no longer on this device.', 0);
    }

    const tracks = await store.listTracks(nightId);
    const reference = await store.getBlob(referenceBlobKey(nightId));
    const referenceBase64 = reference === null ? null : bytesToBase64(reference.bytes);

    let result = null;

    for (const chunk of buildImportChunks(night, tracks, referenceBase64, { room, customerId })) {
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

    if (keepLocalCopy) {
        await store.patchNight(nightId, { claimedSessionId: result.session_id, claimedAt: Date.now() });
    } else {
        await store.deleteNight(nightId);
    }

    return { sessionId: result.session_id, reportUrl: result.report_url };
}
