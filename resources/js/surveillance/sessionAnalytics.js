// The night's analytics summary, computed in the browser for nights that never
// reach the server. This is a line-for-line port of
// App\Actions\Surveillance\ComputeSessionAnalytics: same margins, same bins,
// same output shape (snake_case keys), so replay.js and the report read both
// the server's and the browser's summaries without knowing which they got.
// Keep the two in step — tests/js/surveillance/sessionAnalytics.test.js pins
// the same cases as the PHP unit test.

export const EDGE_BINS = 10;

/**
 * Classify a full-frame point as belonging to a frame edge or the interior.
 * The margin is 5% of the dimension, never less than 8px. In a corner the
 * nearer edge wins; on a tie the first of left, right, top, bottom wins, which
 * is the order PHP's stable asort leaves them in.
 */
export function classifyEdge([x, y], width, height) {
    const marginX = Math.max(8, Math.round(width * 0.05));
    const marginY = Math.max(8, Math.round(height * 0.05));

    const distances = [
        ['left', x <= marginX ? x : null],
        ['right', width - x <= marginX ? width - x : null],
        ['top', y <= marginY ? y : null],
        ['bottom', height - y <= marginY ? height - y : null],
    ].filter(([, distance]) => distance !== null);

    if (distances.length === 0) {
        return 'interior';
    }

    let [nearestEdge, nearestDistance] = distances[0];

    for (const [edge, distance] of distances.slice(1)) {
        if (distance < nearestDistance) {
            nearestEdge = edge;
            nearestDistance = distance;
        }
    }

    return nearestEdge;
}

/**
 * Cluster edge points into zones by bucketing positions along each edge axis
 * and merging adjacent non-empty buckets.
 *
 * @returns {Array<{edge: string, from: number, to: number, center: [number, number], count: number}>}
 */
export function clusterEdgePoints(points, width, height) {
    if (width < 1 || height < 1) {
        return [];
    }

    const byEdge = new Map();

    for (const point of points) {
        const edge = classifyEdge(point, width, height);

        if (edge === 'interior') {
            continue;
        }

        const axisPosition = edge === 'top' || edge === 'bottom' ? point[0] : point[1];

        if (!byEdge.has(edge)) {
            byEdge.set(edge, []);
        }

        byEdge.get(edge).push(axisPosition);
    }

    const zones = [];

    for (const [edge, positions] of byEdge) {
        const axisLength = edge === 'top' || edge === 'bottom' ? width : height;
        const binSize = axisLength / EDGE_BINS;
        const bins = new Array(EDGE_BINS).fill(0);

        for (const position of positions) {
            bins[Math.min(EDGE_BINS - 1, Math.floor(position / binSize))]++;
        }

        for (const [fromBin, toBin, count] of mergeAdjacentBins(bins)) {
            const from = Math.round(fromBin * binSize);
            const to = Math.round((toBin + 1) * binSize);
            const centerAlongAxis = Math.round((from + to) / 2);

            zones.push({
                edge,
                from,
                to,
                center: zoneCenter(edge, centerAlongAxis, width, height),
                count,
            });
        }
    }

    // Array.prototype.sort is stable, as PHP 8's usort is, so equal counts keep
    // their edge order.
    return zones.sort((a, b) => b.count - a.count);
}

function zoneCenter(edge, centerAlongAxis, width, height) {
    switch (edge) {
        case 'top':
            return [centerAlongAxis, 0];
        case 'bottom':
            return [centerAlongAxis, height];
        case 'left':
            return [0, centerAlongAxis];
        default:
            return [width, centerAlongAxis];
    }
}

/** Merge runs of adjacent non-empty bins into [fromBin, toBin, totalCount] triples. */
export function mergeAdjacentBins(bins) {
    const runs = [];
    let current = null;

    bins.forEach((count, index) => {
        if (count === 0) {
            if (current !== null) {
                runs.push(current);
                current = null;
            }

            return;
        }

        if (current === null) {
            current = [index, index, count];
        } else {
            current[1] = index;
            current[2] += count;
        }
    });

    if (current !== null) {
        runs.push(current);
    }

    return runs;
}

/**
 * Where a track came in and went out, from its first and last point. The
 * server does this at insert time; the local sink does it here.
 */
export function trackEdges(points, width, height) {
    if (points.length === 0) {
        return { entryEdge: null, exitEdge: null };
    }

    const first = points[0];
    const last = points[points.length - 1];

    return {
        entryEdge: classifyEdge([first[1], first[2]], width, height),
        exitEdge: classifyEdge([last[1], last[2]], width, height),
    };
}

/**
 * The summary stored on a night. Dismissed tracks never count — this is the
 * one place that rule lives for local nights, matching the confirmed() scope
 * the server applies.
 */
export function computeNightAnalytics({ tracks, startedAt = null, endedAt = null, frameWidth = 0, frameHeight = 0 }) {
    const confirmed = tracks.filter((track) => track.dismissedAt == null);
    const entryPoints = [];
    const exitPoints = [];

    for (const track of confirmed) {
        const points = track.points;

        if (points.length === 0) {
            continue;
        }

        entryPoints.push([points[0][1], points[0][2]]);
        const last = points[points.length - 1];
        exitPoints.push([last[1], last[2]]);
    }

    return {
        track_count: confirmed.length,
        total_points: confirmed.reduce((sum, track) => sum + (track.pointCount ?? track.points.length), 0),
        duration_ms: startedAt !== null && endedAt !== null ? Math.max(0, endedAt - startedAt) : 0,
        entry_zones: clusterEdgePoints(entryPoints, frameWidth, frameHeight),
        exit_zones: clusterEdgePoints(exitPoints, frameWidth, frameHeight),
    };
}
