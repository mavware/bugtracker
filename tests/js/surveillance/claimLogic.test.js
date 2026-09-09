import { describe, expect, test } from 'vitest';
import {
    buildImportChunks,
    bytesToBase64,
    CLAIM_AUTH_LOST_MESSAGE,
    claimSummary,
    IMPORT_CHUNK_SIZE,
    importFailureMessage,
} from '../../../resources/js/surveillance/claimLogic.js';

const night = {
    id: 'n1',
    status: 'completed',
    startedAt: Date.UTC(2026, 8, 8, 21, 30),
    endedAt: Date.UTC(2026, 8, 9, 5, 0),
    frameWidth: 1280,
    frameHeight: 720,
    settings: { procWidth: 320 },
};

const track = (id, dismissedAt = null) => ({
    clientTrackId: id,
    startOffsetMs: 1000,
    endOffsetMs: 3500,
    points: [[1000, 5, 360], [3500, 640, 715]],
    startCrop: 'c3RhcnQ=',
    endCrop: null,
    dismissedAt,
});

describe('buildImportChunks', () => {
    test('sends the night metadata with every chunk and the photo with the first only', () => {
        const tracks = Array.from({ length: IMPORT_CHUNK_SIZE + 1 }, (_, index) => track(`t${index}`));

        const chunks = buildImportChunks(night, tracks, 'cGhvdG8=', { room: ' Kitchen ' });

        expect(chunks).toHaveLength(2);
        expect(chunks[0]).toMatchObject({
            local_id: 'n1',
            started_at: '2026-09-08T21:30:00.000Z',
            ended_at: '2026-09-09T05:00:00.000Z',
            aborted: false,
            room: 'Kitchen',
            frame_width: 1280,
            frame_height: 720,
            settings: { procWidth: 320 },
            reference_image: 'cGhvdG8=',
        });
        expect(chunks[0].tracks).toHaveLength(IMPORT_CHUNK_SIZE);
        expect(chunks[1]).toMatchObject({ local_id: 'n1', reference_image: null });
        expect(chunks[1].tracks).toHaveLength(1);
    });

    test('shapes each track the way the server ingests them, with the dismissal carried over', () => {
        const [chunk] = buildImportChunks(night, [track('t1', 5)], null);

        expect(chunk.tracks[0]).toEqual({
            client_track_id: 't1',
            start_offset_ms: 1000,
            end_offset_ms: 3500,
            points: [[1000, 5, 360], [3500, 640, 715]],
            start_crop: 'c3RhcnQ=',
            end_crop: null,
            dismissed: true,
        });
    });

    test('an empty night is still one chunk, a discarded one is flagged, and no room means null', () => {
        const [chunk] = buildImportChunks({ ...night, status: 'aborted' }, [], null, { room: '  ' });

        expect(chunk.tracks).toEqual([]);
        expect(chunk.aborted).toBe(true);
        expect(chunk.room).toBeNull();
    });

    test('an interrupted night that never ended is closed at its start for the import', () => {
        const [chunk] = buildImportChunks({ ...night, endedAt: null }, [], null);

        expect(chunk.ended_at).toBe(chunk.started_at);
    });
});

describe('bytesToBase64', () => {
    test('encodes bytes without a data prefix, in one piece or many', () => {
        expect(bytesToBase64(new Uint8Array([255, 216, 255]).buffer)).toBe('/9j/');

        const big = new Uint8Array(0x8000 + 3).fill(65);
        expect(bytesToBase64(big.buffer)).toBe(btoa('A'.repeat(0x8000 + 3)));
    });
});

describe('importFailureMessage', () => {
    test('tells a logged-out user to log back in, and everyone else the status', () => {
        expect(importFailureMessage(401)).toBe(CLAIM_AUTH_LOST_MESSAGE);
        expect(importFailureMessage(419)).toBe(CLAIM_AUTH_LOST_MESSAGE);
        expect(importFailureMessage(500)).toContain('HTTP 500');
    });
});

describe('claimSummary', () => {
    test('reads as a sentence whatever happened', () => {
        expect(claimSummary({ imported: 1, skipped: 0, failed: 0 })).toBe('1 night saved to your account.');
        expect(claimSummary({ imported: 2, skipped: 1, failed: 1 })).toBe('2 nights saved to your account, 1 already saved, 1 could not be saved.');
        expect(claimSummary({ imported: 0, skipped: 0, failed: 0 })).toBe('Nothing to import.');
    });
});
