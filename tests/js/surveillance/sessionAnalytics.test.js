// Mirrors tests/Unit/Actions/Surveillance/ComputeSessionAnalyticsTest.php and
// the analytics expectations in tests/Feature/Surveillance/EndSessionTest.php,
// case for case. If a case changes on one side it must change on the other:
// the report reads both summaries interchangeably.
import { describe, expect, test } from 'vitest';
import {
    classifyEdge,
    clusterEdgePoints,
    computeNightAnalytics,
    mergeAdjacentBins,
    trackEdges,
} from '../../../resources/js/surveillance/sessionAnalytics.js';

describe('classifyEdge', () => {
    test.each([
        ['left edge', [3, 360], 'left'],
        ['right edge', [1278, 360], 'right'],
        ['top edge', [640, 10], 'top'],
        ['bottom edge', [640, 715], 'bottom'],
        ['interior', [640, 360], 'interior'],
        ['corner nearer to top', [60, 5], 'top'],
        ['just inside the margin', [64, 360], 'left'],
        ['just outside the margin', [65, 360], 'interior'],
    ])('%s', (_, point, expected) => {
        expect(classifyEdge(point, 1280, 720)).toBe(expected);
    });

    test('never lets the margin shrink below 8px on a tiny frame', () => {
        expect(classifyEdge([7, 50], 100, 100)).toBe('left');
    });
});

describe('clusterEdgePoints', () => {
    test('merges adjacent bins into zones sorted by count', () => {
        const points = [
            [10, 0], // top, bin 0
            [140, 0], // top, bin 1 (adjacent -> merges with bin 0)
            [1270, 300], // right
            [640, 360], // interior, ignored
            [20, 0], // top, bin 0
        ];

        const zones = clusterEdgePoints(points, 1280, 720);

        expect(zones).toHaveLength(2);
        expect(zones[0]).toEqual({ edge: 'top', count: 3, from: 0, to: 256, center: [128, 0] });
        expect(zones[1]).toMatchObject({ edge: 'right', count: 1 });
    });

    test('keeps non-adjacent groups on the same edge as separate zones', () => {
        const zones = clusterEdgePoints([[10, 5], [1200, 5]], 1280, 720);

        expect(zones).toHaveLength(2);
        expect(zones[0].edge).toBe('top');
        expect(zones[1].edge).toBe('top');
    });

    test('returns no zones for an empty frame or empty input', () => {
        expect(clusterEdgePoints([], 1280, 720)).toEqual([]);
        expect(clusterEdgePoints([[1, 1]], 0, 0)).toEqual([]);
    });

    test('places centers on the far edge for right and bottom zones, at the middle of the bin', () => {
        const zones = clusterEdgePoints([[1275, 360], [640, 718]], 1280, 720);

        expect(zones.find((zone) => zone.edge === 'right').center).toEqual([1280, 396]);
        expect(zones.find((zone) => zone.edge === 'bottom').center).toEqual([704, 720]);
    });
});

describe('mergeAdjacentBins', () => {
    test('turns runs of non-empty bins into from/to/count triples', () => {
        expect(mergeAdjacentBins([2, 1, 0, 0, 3, 0, 1])).toEqual([[0, 1, 3], [4, 4, 3], [6, 6, 1]]);
    });
});

describe('trackEdges', () => {
    test('classifies the first and last point of a track', () => {
        const points = [[0, 5, 360], [1000, 640, 360], [2000, 640, 715]];

        expect(trackEdges(points, 1280, 720)).toEqual({ entryEdge: 'left', exitEdge: 'bottom' });
    });

    test('has nothing to say about an empty track', () => {
        expect(trackEdges([], 1280, 720)).toEqual({ entryEdge: null, exitEdge: null });
    });
});

describe('computeNightAnalytics', () => {
    const track = {
        points: [[0, 5, 360], [1000, 640, 360], [2000, 640, 715]],
        pointCount: 3,
        dismissedAt: null,
    };

    test('summarises a night the way the server does when it ends a session', () => {
        const analytics = computeNightAnalytics({
            tracks: [track],
            startedAt: 1_000_000,
            endedAt: 1_060_000,
            frameWidth: 1280,
            frameHeight: 720,
        });

        expect(analytics.track_count).toBe(1);
        expect(analytics.total_points).toBe(3);
        expect(analytics.duration_ms).toBe(60000);
        expect(analytics.entry_zones[0].edge).toBe('left');
        expect(analytics.exit_zones[0].edge).toBe('bottom');
    });

    test('leaves dismissed tracks out of every figure', () => {
        const analytics = computeNightAnalytics({
            tracks: [{ ...track, dismissedAt: 5 }],
            frameWidth: 1280,
            frameHeight: 720,
        });

        expect(analytics).toEqual({ track_count: 0, total_points: 0, duration_ms: 0, entry_zones: [], exit_zones: [] });
    });

    test('reports no duration until the night has both ends', () => {
        expect(computeNightAnalytics({ tracks: [], startedAt: 5 }).duration_ms).toBe(0);
        expect(computeNightAnalytics({ tracks: [], startedAt: 10, endedAt: 5 }).duration_ms).toBe(0);
    });

    test('counts points from the array when no point count is stored', () => {
        expect(computeNightAnalytics({ tracks: [{ points: [[0, 1, 1], [1, 2, 2]] }] }).total_points).toBe(2);
    });
});
