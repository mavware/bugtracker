<?php

use App\Actions\Surveillance\DescribeNightInProgress;
use App\Models\BugTrack;
use App\Models\SurveillanceSession;
use Illuminate\Support\Carbon;

test('the last sighting is the latest confirmed track, measured from the start of the night', function () {
    $session = SurveillanceSession::factory()->active()->create([
        'started_at' => Carbon::parse('2026-09-02 23:00'),
    ]);
    BugTrack::factory()->for($session, 'session')->create(['end_offset_ms' => 60000]);
    BugTrack::factory()->for($session, 'session')->create(['end_offset_ms' => 9000000]);
    BugTrack::factory()->for($session, 'session')->dismissed()->create(['end_offset_ms' => 20000000]);

    $tonight = app(DescribeNightInProgress::class)->handle($session);

    expect($tonight['sightings'])->toBe(2)
        ->and($tonight['last_sighting_at']?->toDateTimeString())->toBe('2026-09-03 01:30:00');
});

test('a night with no confirmed sightings has no last sighting', function () {
    $session = SurveillanceSession::factory()->active()->create();
    BugTrack::factory()->for($session, 'session')->dismissed()->create(['end_offset_ms' => 5000]);

    $tonight = app(DescribeNightInProgress::class)->handle($session);

    expect($tonight['sightings'])->toBe(0)
        ->and($tonight['last_sighting_at'])->toBeNull();
});
