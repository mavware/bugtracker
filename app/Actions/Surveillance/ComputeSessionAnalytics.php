<?php

namespace App\Actions\Surveillance;

use App\Models\SurveillanceSession;

class ComputeSessionAnalytics
{
    private const int EDGE_BINS = 10;

    /**
     * Aggregate the session's tracks into the analytics summary stored on the session.
     */
    public function handle(SurveillanceSession $session): void
    {
        $tracks = $session->tracks()->confirmed()->get(['id', 'point_count', 'points', 'entry_edge', 'exit_edge', 'start_offset_ms', 'end_offset_ms']);

        $width = $session->frame_width ?? 0;
        $height = $session->frame_height ?? 0;

        $entryPoints = [];
        $exitPoints = [];

        foreach ($tracks as $track) {
            $points = $track->points;

            if ($points === []) {
                continue;
            }

            $entryPoints[] = [$points[0][1], $points[0][2]];
            $last = end($points);
            $exitPoints[] = [$last[1], $last[2]];
        }

        $session->update([
            'analytics' => [
                'track_count' => $tracks->count(),
                'total_points' => (int) $tracks->sum('point_count'),
                'duration_ms' => $session->started_at !== null && $session->ended_at !== null
                    ? max(0, (int) $session->started_at->diffInMilliseconds($session->ended_at))
                    : 0,
                'entry_zones' => self::clusterEdgePoints($entryPoints, $width, $height),
                'exit_zones' => self::clusterEdgePoints($exitPoints, $width, $height),
            ],
        ]);
    }

    /**
     * Classify a point as belonging to a frame edge or the interior.
     *
     * @param  array{0: int, 1: int}  $point  [x, y]
     */
    public static function classifyEdge(array $point, int $width, int $height): string
    {
        [$x, $y] = $point;

        $marginX = max(8, (int) round($width * 0.05));
        $marginY = max(8, (int) round($height * 0.05));

        $distances = [
            'left' => $x <= $marginX ? $x : null,
            'right' => $width - $x <= $marginX ? $width - $x : null,
            'top' => $y <= $marginY ? $y : null,
            'bottom' => $height - $y <= $marginY ? $height - $y : null,
        ];

        $distances = array_filter($distances, fn (?int $distance) => $distance !== null);

        if ($distances === []) {
            return 'interior';
        }

        asort($distances);

        return array_key_first($distances);
    }

    /**
     * Cluster edge points into zones by bucketing positions along each edge axis
     * and merging adjacent non-empty buckets.
     *
     * @param  array<int, array{0: int, 1: int}>  $points  [x, y] pairs
     * @return array<int, array{edge: string, from: int, to: int, center: array{0: int, 1: int}, count: int}>
     */
    public static function clusterEdgePoints(array $points, int $width, int $height): array
    {
        if ($width < 1 || $height < 1) {
            return [];
        }

        $byEdge = [];

        foreach ($points as $point) {
            $edge = self::classifyEdge($point, $width, $height);

            if ($edge === 'interior') {
                continue;
            }

            $axisPosition = in_array($edge, ['top', 'bottom'], true) ? $point[0] : $point[1];
            $byEdge[$edge][] = $axisPosition;
        }

        $zones = [];

        foreach ($byEdge as $edge => $positions) {
            $axisLength = in_array($edge, ['top', 'bottom'], true) ? $width : $height;
            $binSize = $axisLength / self::EDGE_BINS;

            $bins = array_fill(0, self::EDGE_BINS, 0);

            foreach ($positions as $position) {
                $bin = min(self::EDGE_BINS - 1, (int) floor($position / $binSize));
                $bins[$bin]++;
            }

            foreach (self::mergeAdjacentBins($bins) as [$fromBin, $toBin, $count]) {
                $from = (int) round($fromBin * $binSize);
                $to = (int) round(($toBin + 1) * $binSize);
                $centerAlongAxis = (int) round(($from + $to) / 2);

                $zones[] = [
                    'edge' => $edge,
                    'from' => $from,
                    'to' => $to,
                    'center' => match ($edge) {
                        'top' => [$centerAlongAxis, 0],
                        'bottom' => [$centerAlongAxis, $height],
                        'left' => [0, $centerAlongAxis],
                        default => [$width, $centerAlongAxis],
                    },
                    'count' => $count,
                ];
            }
        }

        usort($zones, fn (array $a, array $b) => $b['count'] <=> $a['count']);

        return $zones;
    }

    /**
     * Merge runs of adjacent non-empty bins into [fromBin, toBin, totalCount] triples.
     *
     * @param  array<int, int>  $bins
     * @return array<int, array{0: int, 1: int, 2: int}>
     */
    private static function mergeAdjacentBins(array $bins): array
    {
        $runs = [];
        $current = null;

        foreach ($bins as $index => $count) {
            if ($count === 0) {
                if ($current !== null) {
                    $runs[] = $current;
                    $current = null;
                }

                continue;
            }

            if ($current === null) {
                $current = [$index, $index, $count];
            } else {
                $current[1] = $index;
                $current[2] += $count;
            }
        }

        if ($current !== null) {
            $runs[] = $current;
        }

        return $runs;
    }
}
