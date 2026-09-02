<?php

use App\Actions\Surveillance\ComputeSessionAnalytics;

test('classifyEdge identifies each edge, the interior, and prefers the nearest edge in corners', function (array $point, string $expected) {
    expect(ComputeSessionAnalytics::classifyEdge($point, 1280, 720))->toBe($expected);
})->with([
    'left edge' => [[3, 360], 'left'],
    'right edge' => [[1278, 360], 'right'],
    'top edge' => [[640, 10], 'top'],
    'bottom edge' => [[640, 715], 'bottom'],
    'interior' => [[640, 360], 'interior'],
    'corner nearer to top' => [[60, 5], 'top'],
    'just inside the margin' => [[64, 360], 'left'],
    'just outside the margin' => [[65, 360], 'interior'],
]);

test('clusterEdgePoints merges adjacent bins into zones sorted by count', function () {
    $points = [
        [10, 0],   // top, bin 0
        [140, 0],  // top, bin 1 (adjacent -> merges with bin 0)
        [1270, 300], // right
        [640, 360],  // interior, ignored
        [20, 0],   // top, bin 0
    ];

    $zones = ComputeSessionAnalytics::clusterEdgePoints($points, 1280, 720);

    expect($zones)->toHaveCount(2)
        ->and($zones[0]['edge'])->toBe('top')
        ->and($zones[0]['count'])->toBe(3)
        ->and($zones[0]['from'])->toBe(0)
        ->and($zones[0]['to'])->toBe(256)
        ->and($zones[0]['center'])->toBe([128, 0])
        ->and($zones[1]['edge'])->toBe('right')
        ->and($zones[1]['count'])->toBe(1);
});

test('clusterEdgePoints keeps non-adjacent groups on the same edge as separate zones', function () {
    $points = [
        [10, 5],   // top, bin 0
        [1200, 5], // top, bin 9
    ];

    $zones = ComputeSessionAnalytics::clusterEdgePoints($points, 1280, 720);

    expect($zones)->toHaveCount(2)
        ->and($zones[0]['edge'])->toBe('top')
        ->and($zones[1]['edge'])->toBe('top');
});

test('clusterEdgePoints returns no zones for an empty frame or empty input', function () {
    expect(ComputeSessionAnalytics::clusterEdgePoints([], 1280, 720))->toBe([])
        ->and(ComputeSessionAnalytics::clusterEdgePoints([[1, 1]], 0, 0))->toBe([]);
});
