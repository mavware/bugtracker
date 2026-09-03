<?php

use App\Actions\Surveillance\ComputeEntryPointHeatmap;
use App\Enums\SurveillanceSessionStatus;
use App\Models\BugTrack;
use App\Models\Customer;
use App\Models\SurveillanceSession;
use App\Models\User;

test('it aggregates entry and exit zones across completed sessions at different resolutions', function () {
    $user = User::factory()->create();
    $recent = SurveillanceSession::factory()->for($user)->completed()->create(['started_at' => now()->subHours(9)]);
    $older = SurveillanceSession::factory()->for($user)->completed()->create([
        'started_at' => now()->subDays(2),
        'frame_width' => 640,
        'frame_height' => 360,
    ]);
    BugTrack::factory()->for($recent, 'session')->create([
        'points' => [[0, 5, 360], [1000, 640, 360], [2000, 640, 715]],
        'point_count' => 3,
    ]);
    BugTrack::factory()->for($older, 'session')->create([
        'points' => [[0, 2, 180], [1000, 320, 180], [2000, 320, 357]],
        'point_count' => 3,
    ]);

    $heatmap = app(ComputeEntryPointHeatmap::class)->handle($user);

    expect($heatmap['session_count'])->toBe(2)
        ->and($heatmap['track_count'])->toBe(2)
        ->and($heatmap['backdrop']->id)->toBe($recent->id)
        ->and($heatmap['entry_zones'])->toHaveCount(1)
        ->and($heatmap['entry_zones'][0]['edge'])->toBe('left')
        ->and($heatmap['entry_zones'][0]['count'])->toBe(2)
        ->and($heatmap['exit_zones'])->toHaveCount(1)
        ->and($heatmap['exit_zones'][0]['edge'])->toBe('bottom')
        ->and($heatmap['exit_zones'][0]['count'])->toBe(2);
});

test('it scopes the aggregate to one room so nights shot from different spots are not merged', function () {
    $user = User::factory()->create();
    $kitchen = SurveillanceSession::factory()->for($user)->completed()->create(['room' => 'Kitchen']);
    $bathroom = SurveillanceSession::factory()->for($user)->completed()->create(['room' => 'Bathroom']);
    BugTrack::factory()->for($kitchen, 'session')->create([
        'points' => [[0, 5, 360], [2000, 640, 715]],
        'point_count' => 2,
    ]);
    BugTrack::factory()->count(3)->for($bathroom, 'session')->create();

    $heatmap = app(ComputeEntryPointHeatmap::class)->handle($user, 'Kitchen');

    expect($heatmap['session_count'])->toBe(1)
        ->and($heatmap['track_count'])->toBe(1)
        ->and($heatmap['backdrop']->id)->toBe($kitchen->id)
        ->and($heatmap['entry_zones'][0]['edge'])->toBe('left');
});

test('it scopes the aggregate to one customer so two properties are not projected onto one backdrop', function () {
    $user = User::factory()->create();
    $alvarez = Customer::factory()->for($user)->create();
    $alvarezNight = SurveillanceSession::factory()->for($user)->completed()->create(['customer_id' => $alvarez->id]);
    $otherNight = SurveillanceSession::factory()->for($user)->completed()->create();
    BugTrack::factory()->for($alvarezNight, 'session')->create([
        'points' => [[0, 5, 360], [2000, 640, 715]],
        'point_count' => 2,
    ]);
    BugTrack::factory()->count(4)->for($otherNight, 'session')->create();

    $heatmap = app(ComputeEntryPointHeatmap::class)->handle($user, null, $alvarez->id);

    expect($heatmap['session_count'])->toBe(1)
        ->and($heatmap['track_count'])->toBe(1)
        ->and($heatmap['backdrop']->id)->toBe($alvarezNight->id);
});

test('a night the user discarded is left out of the map', function () {
    $user = User::factory()->create();
    $discarded = SurveillanceSession::factory()->for($user)->completed()->create([
        'status' => SurveillanceSessionStatus::Aborted,
    ]);
    BugTrack::factory()->count(3)->for($discarded, 'session')->create();

    $heatmap = app(ComputeEntryPointHeatmap::class)->handle($user);

    expect($heatmap['session_count'])->toBe(0)
        ->and($heatmap['track_count'])->toBe(0);
});

test('it excludes dismissed tracks and sessions that are not finished', function () {
    $user = User::factory()->create();
    $completed = SurveillanceSession::factory()->for($user)->completed()->create();
    BugTrack::factory()->for($completed, 'session')->create();
    BugTrack::factory()->for($completed, 'session')->dismissed()->create();
    $active = SurveillanceSession::factory()->for($user)->active()->create();
    BugTrack::factory()->for($active, 'session')->create();

    $heatmap = app(ComputeEntryPointHeatmap::class)->handle($user);

    expect($heatmap['session_count'])->toBe(1)
        ->and($heatmap['track_count'])->toBe(1);
});

test('it ignores other users\' sessions and returns an empty result without any', function () {
    $user = User::factory()->create();
    $otherSession = SurveillanceSession::factory()->completed()->create();
    BugTrack::factory()->for($otherSession, 'session')->create();

    $heatmap = app(ComputeEntryPointHeatmap::class)->handle($user);

    expect($heatmap['session_count'])->toBe(0)
        ->and($heatmap['track_count'])->toBe(0)
        ->and($heatmap['backdrop'])->toBeNull()
        ->and($heatmap['entry_zones'])->toBe([])
        ->and($heatmap['exit_zones'])->toBe([]);
});
