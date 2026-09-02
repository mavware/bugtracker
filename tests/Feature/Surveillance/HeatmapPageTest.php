<?php

use App\Models\BugTrack;
use App\Models\SurveillanceSession;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('surveillance.heatmap'))->assertRedirect(route('login'));
});

test('the heatmap page renders the summary and the data island', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create();
    BugTrack::factory()->for($session, 'session')->create([
        'points' => [[0, 5, 360], [2000, 640, 715]],
        'point_count' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('surveillance.heatmap'))
        ->assertSee('Nights aggregated')
        ->assertSee('heatmap-data', false)
        ->assertSee('entryZones', false);
});

test('the heatmap page shows an empty state without completed sessions', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->for($user)->active()->create();

    $this->actingAs($user)
        ->get(route('surveillance.heatmap'))
        ->assertSee('No completed nights yet');
});
