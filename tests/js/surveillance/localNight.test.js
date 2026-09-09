import { describe, expect, test } from 'vitest';
import {
    buildLocalNight,
    buildLocalReportPayload,
    finalizeInterruptedNight,
    LOCAL_ID_PLACEHOLDER,
    localTrackFromClosed,
    nightDateFor,
    nightName,
    nightRows,
    referenceBlobKey,
    reportHeader,
    reportUrlFor,
    sightingRows,
    statTiles,
} from '../../../resources/js/surveillance/localNight.js';

// 2026-09-08 22:30 local time, and 01:30 the following morning.
const EVENING = new Date(2026, 8, 8, 22, 30).getTime();
const AFTER_MIDNIGHT = new Date(2026, 8, 9, 1, 30).getTime();

const closedTrack = (id, startOffsetMs = 1000) => ({
    client_track_id: id,
    start_offset_ms: startOffsetMs,
    end_offset_ms: startOffsetMs + 2500,
    points: [[startOffsetMs, 5, 360], [startOffsetMs + 1000, 640, 360], [startOffsetMs + 2500, 640, 715]],
    start_crop: 'c3RhcnQ=',
    end_crop: null,
});

describe('nightDateFor and nightName', () => {
    test('a night begun after midnight still belongs to the evening before', () => {
        expect(nightDateFor(AFTER_MIDNIGHT).getDate()).toBe(8);
        expect(nightName(AFTER_MIDNIGHT)).toBe('Night of Sep 8');
    });

    test('an evening start is named for its own date', () => {
        expect(nightName(EVENING)).toBe('Night of Sep 8');
    });
});

describe('buildLocalNight', () => {
    test('starts a night active, named, unclaimed, and with its heartbeat at the start', () => {
        const night = buildLocalNight({ id: 'n1', startedAt: EVENING, frameWidth: 1280, frameHeight: 720, settings: { procWidth: 320 } });

        expect(night).toMatchObject({
            id: 'n1',
            name: 'Night of Sep 8',
            status: 'active',
            startedAt: EVENING,
            endedAt: null,
            lastHeartbeatAt: EVENING,
            frameWidth: 1280,
            analytics: null,
            claimedSessionId: null,
        });
    });
});

describe('reportUrlFor and referenceBlobKey', () => {
    test('swaps the placeholder id in a route the server generated', () => {
        expect(reportUrlFor(`https://bugtracker.test/watch/${LOCAL_ID_PLACEHOLDER}/report`, 'abc')).toBe(
            'https://bugtracker.test/watch/abc/report',
        );
    });

    test('keys the reference photo by night', () => {
        expect(referenceBlobKey('n1')).toBe('n1/reference');
    });
});

describe('localTrackFromClosed', () => {
    test('stores the tracker payload with its edges classified, as the server does on insert', () => {
        const track = localTrackFromClosed(closedTrack('t1'), 'n1', 1280, 720);

        expect(track).toEqual({
            nightId: 'n1',
            clientTrackId: 't1',
            startOffsetMs: 1000,
            endOffsetMs: 3500,
            pointCount: 3,
            points: closedTrack('t1').points,
            entryEdge: 'left',
            exitEdge: 'bottom',
            startCrop: 'c3RhcnQ=',
            endCrop: null,
            dismissedAt: null,
        });
    });
});

describe('buildLocalReportPayload', () => {
    const night = { ...buildLocalNight({ id: 'n1', startedAt: EVENING, frameWidth: 1280, frameHeight: 720, settings: {} }), endedAt: EVENING + 60000 };
    const tracks = [
        localTrackFromClosed(closedTrack('later', 5000), 'n1', 1280, 720),
        localTrackFromClosed(closedTrack('first', 1000), 'n1', 1280, 720),
        { ...localTrackFromClosed(closedTrack('gone', 3000), 'n1', 1280, 720), dismissedAt: 1 },
    ];

    test('is the shape replay.js reads, confirmed tracks only, in start order', () => {
        const payload = buildLocalReportPayload(night, tracks);

        expect(payload.frameWidth).toBe(1280);
        expect(payload.tracks.map((track) => track.id)).toEqual(['first', 'later']);
        expect(payload.tracks[0]).toEqual({
            id: 'first',
            startOffsetMs: 1000,
            endOffsetMs: 3500,
            points: tracks[1].points,
            entryEdge: 'left',
            exitEdge: 'bottom',
        });
    });

    test('computes analytics when the night has none stored yet, and prefers stored ones', () => {
        expect(buildLocalReportPayload(night, tracks).analytics.track_count).toBe(2);
        expect(buildLocalReportPayload({ ...night, analytics: { track_count: 9 } }, tracks).analytics.track_count).toBe(9);
    });
});

describe('sightingRows', () => {
    test('describes each track the way the server-rendered table does, dismissed rows included', () => {
        const night = buildLocalNight({ id: 'n1', startedAt: EVENING, frameWidth: 1280, frameHeight: 720, settings: {} });
        const rows = sightingRows(night, [
            { ...localTrackFromClosed(closedTrack('b', 5000), 'n1', 1280, 720), dismissedAt: 1 },
            localTrackFromClosed(closedTrack('a', 1000), 'n1', 1280, 720),
        ]);

        expect(rows.map((row) => row.clientTrackId)).toEqual(['a', 'b']);
        expect(rows[0]).toMatchObject({
            time: '22:30:01',
            durationSeconds: 2.5,
            entered: 'Left',
            exited: 'Bottom',
            startCropSrc: 'data:image/jpeg;base64,c3RhcnQ=',
            endCropSrc: null,
            dismissed: false,
        });
        expect(rows[1].dismissed).toBe(true);
    });
});

describe('statTiles', () => {
    test('names the busiest edges and copes with an empty summary', () => {
        expect(statTiles({ track_count: 4, entry_zones: [{ edge: 'left' }], exit_zones: [] })).toEqual({
            trackCount: 4,
            topEntry: 'Left edge',
            topExit: 'None',
        });
        expect(statTiles(null)).toEqual({ trackCount: 0, topEntry: 'None', topExit: 'None' });
    });
});

describe('reportHeader', () => {
    test('shows the range once the night has ended and flags a discarded one', () => {
        const night = { ...buildLocalNight({ id: 'n1', startedAt: EVENING, frameWidth: 1, frameHeight: 1, settings: {} }), endedAt: AFTER_MIDNIGHT, status: 'aborted' };
        const header = reportHeader(night);

        expect(header.title).toBe('Night of Sep 8');
        expect(header.range).toBe('Sep 8, 22:30 – Sep 9, 01:30');
        expect(header.discarded).toBe(true);
    });
});

describe('nightRows', () => {
    test('lists nights with a report link, a status word and whether they are already claimed', () => {
        const night = buildLocalNight({ id: 'n1', startedAt: EVENING, frameWidth: 1, frameHeight: 1, settings: {} });
        const rows = nightRows(
            [
                { ...night, status: 'completed', analytics: { track_count: 3 }, claimedSessionId: 12 },
                { ...night, id: 'n2' },
            ],
            { reportUrlTemplate: `/watch/${LOCAL_ID_PLACEHOLDER}/report` },
        );

        expect(rows[0]).toMatchObject({ id: 'n1', status: 'Completed', sightings: 3, reportUrl: '/watch/n1/report', claimed: true });
        expect(rows[1]).toMatchObject({ id: 'n2', status: 'Interrupted', sightings: 0, claimed: false });
    });
});

describe('finalizeInterruptedNight', () => {
    test('closes a night whose tab died at its last heartbeat, with analytics', () => {
        const night = { ...buildLocalNight({ id: 'n1', startedAt: EVENING, frameWidth: 1280, frameHeight: 720, settings: {} }), lastHeartbeatAt: EVENING + 90000 };
        const patch = finalizeInterruptedNight(night, [localTrackFromClosed(closedTrack('t1'), 'n1', 1280, 720)]);

        expect(patch.status).toBe('completed');
        expect(patch.endedAt).toBe(EVENING + 90000);
        expect(patch.analytics.track_count).toBe(1);
        expect(patch.analytics.duration_ms).toBe(90000);
    });

    test('leaves a finished night alone', () => {
        const night = { ...buildLocalNight({ id: 'n1', startedAt: EVENING, frameWidth: 1, frameHeight: 1, settings: {} }), status: 'completed' };

        expect(finalizeInterruptedNight(night, [])).toBeNull();
    });
});
