<?php

use App\Actions\Surveillance\ComputeNightlyTrend;
use App\Enums\SurveillanceSessionStatus;
use App\Models\BugTrack;
use App\Models\Intervention;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Support\Carbon;

test('it counts confirmed sightings per night and reports the headline nights', function () {
    $user = User::factory()->create();
    $first = SurveillanceSession::factory()->for($user)->completed()->create(['started_at' => Carbon::parse('2026-09-01 23:00')]);
    $second = SurveillanceSession::factory()->for($user)->completed()->create(['started_at' => Carbon::parse('2026-09-02 23:00')]);
    BugTrack::factory()->count(3)->for($first, 'session')->create();
    BugTrack::factory()->for($second, 'session')->create();

    $trend = app(ComputeNightlyTrend::class)->handle($user);

    expect($trend['nights'])->toHaveCount(2)
        ->and($trend['nights'][0])->toMatchArray(['date' => '2026-09-01', 'count' => 3, 'session_count' => 1])
        ->and($trend['nights'][1])->toMatchArray(['date' => '2026-09-02', 'count' => 1])
        ->and($trend['total_sightings'])->toBe(4)
        ->and($trend['busiest']['date'])->toBe('2026-09-01')
        ->and($trend['latest']['date'])->toBe('2026-09-02')
        ->and($trend['previous']['date'])->toBe('2026-09-01');
});

test('it merges sessions from the same night and ignores dismissed tracks and unfinished sessions', function () {
    $user = User::factory()->create();
    $early = SurveillanceSession::factory()->for($user)->completed()->create(['started_at' => Carbon::parse('2026-09-01 21:00')]);
    $late = SurveillanceSession::factory()->for($user)->completed()->create(['started_at' => Carbon::parse('2026-09-01 23:30')]);
    $stillRunning = SurveillanceSession::factory()->for($user)->active()->create(['started_at' => Carbon::parse('2026-09-02 22:00')]);
    BugTrack::factory()->count(2)->for($early, 'session')->create();
    BugTrack::factory()->for($early, 'session')->dismissed()->create();
    BugTrack::factory()->for($late, 'session')->create();
    BugTrack::factory()->count(5)->for($stillRunning, 'session')->create();

    $trend = app(ComputeNightlyTrend::class)->handle($user);

    expect($trend['nights'])->toHaveCount(1)
        ->and($trend['nights'][0])->toMatchArray(['date' => '2026-09-01', 'count' => 3, 'session_count' => 2]);
});

test('it counts nights from aborted sessions too', function () {
    $user = User::factory()->create();
    $aborted = SurveillanceSession::factory()->for($user)->completed()->create([
        'status' => SurveillanceSessionStatus::Aborted,
        'started_at' => Carbon::parse('2026-09-01 23:00'),
    ]);
    BugTrack::factory()->count(2)->for($aborted, 'session')->create();

    $trend = app(ComputeNightlyTrend::class)->handle($user);

    expect($trend['total_sightings'])->toBe(2);
});

test('it scopes nights to a room when one is given', function () {
    $user = User::factory()->create();
    $kitchen = SurveillanceSession::factory()->for($user)->completed()->create(['room' => 'Kitchen', 'started_at' => Carbon::parse('2026-09-01 23:00')]);
    $bathroom = SurveillanceSession::factory()->for($user)->completed()->create(['room' => 'Bathroom', 'started_at' => Carbon::parse('2026-09-02 23:00')]);
    BugTrack::factory()->count(2)->for($kitchen, 'session')->create();
    BugTrack::factory()->count(7)->for($bathroom, 'session')->create();

    $trend = app(ComputeNightlyTrend::class)->handle($user, 'Kitchen');

    expect($trend['nights'])->toHaveCount(1)
        ->and($trend['total_sightings'])->toBe(2);
});

test('it positions interventions by how many recorded nights came before them', function () {
    $user = User::factory()->create();
    foreach (['2026-09-01 23:00', '2026-09-03 23:00'] as $startedAt) {
        SurveillanceSession::factory()->for($user)->completed()->create(['started_at' => Carbon::parse($startedAt)]);
    }
    Intervention::factory()->for($user)->create(['performed_on' => '2026-08-31', 'description' => 'Before any night']);
    Intervention::factory()->for($user)->create(['performed_on' => '2026-09-02', 'description' => 'Between the nights']);
    Intervention::factory()->for($user)->create(['performed_on' => '2026-09-05', 'description' => 'After every night']);

    $trend = app(ComputeNightlyTrend::class)->handle($user);

    expect(array_column($trend['interventions'], 'position'))->toBe([0, 1, 2])
        ->and(array_column($trend['interventions'], 'marker'))->toBe([1, 2, 3])
        ->and($trend['interventions'][1]['description'])->toBe('Between the nights');
});

test('a room filter keeps that room\'s interventions and the ones that apply everywhere', function () {
    $user = User::factory()->create();
    Intervention::factory()->for($user)->create(['room' => 'Kitchen', 'performed_on' => '2026-09-01', 'description' => 'Kitchen bait']);
    Intervention::factory()->for($user)->create(['room' => null, 'performed_on' => '2026-09-02', 'description' => 'Sealed the front door']);
    Intervention::factory()->for($user)->create(['room' => 'Bathroom', 'performed_on' => '2026-09-03', 'description' => 'Bathroom bait']);

    $trend = app(ComputeNightlyTrend::class)->handle($user, 'Kitchen');

    expect(array_column($trend['interventions'], 'description'))
        ->toBe(['Kitchen bait', 'Sealed the front door']);
});

test('it reports an empty trend and ignores other users\' nights', function () {
    $user = User::factory()->create();
    $otherSession = SurveillanceSession::factory()->completed()->create();
    BugTrack::factory()->for($otherSession, 'session')->create();

    $trend = app(ComputeNightlyTrend::class)->handle($user);

    expect($trend['nights'])->toBe([])
        ->and($trend['total_sightings'])->toBe(0)
        ->and($trend['busiest'])->toBeNull()
        ->and($trend['latest'])->toBeNull();
});

test('it lists the distinct rooms the user has recorded', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->for($user)->create(['room' => 'Kitchen']);
    SurveillanceSession::factory()->for($user)->create(['room' => 'Kitchen']);
    SurveillanceSession::factory()->for($user)->create(['room' => 'Bathroom']);
    SurveillanceSession::factory()->for($user)->create(['room' => null]);
    SurveillanceSession::factory()->create(['room' => 'Someone else\'s garage']);

    expect(app(ComputeNightlyTrend::class)->rooms($user))->toBe(['Bathroom', 'Kitchen']);
});
