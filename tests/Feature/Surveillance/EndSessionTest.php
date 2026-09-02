<?php

use App\Enums\SurveillanceSessionStatus;
use App\Models\BugTrack;
use App\Models\SurveillanceSession;
use App\Models\User;

test('ending a session sets ended_at from the offset and computes analytics', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->active()->create();
    BugTrack::factory()->for($session, 'session')->create([
        'points' => [[0, 5, 360], [1000, 640, 360], [2000, 640, 715]],
        'point_count' => 3,
    ]);

    $response = $this->actingAs($user)->postJson(route('surveillance.end', $session), [
        'ended_at_offset_ms' => 60000,
    ]);

    $response->assertOk()->assertJson([
        'status' => 'completed',
        'report_url' => route('surveillance.report', $session),
    ]);

    $session->refresh();
    expect($session->status)->toBe(SurveillanceSessionStatus::Completed)
        ->and($session->ended_at->getTimestampMs() - $session->started_at->getTimestampMs())->toBe(60000)
        ->and($session->analytics['track_count'])->toBe(1)
        ->and($session->analytics['total_points'])->toBe(3)
        ->and($session->analytics['entry_zones'][0]['edge'])->toBe('left')
        ->and($session->analytics['exit_zones'][0]['edge'])->toBe('bottom');
});

test('ending a session with the aborted flag marks it aborted', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->active()->create();

    $this->actingAs($user)->postJson(route('surveillance.end', $session), [
        'ended_at_offset_ms' => 1000,
        'aborted' => true,
    ])->assertOk();

    expect($session->refresh()->status)->toBe(SurveillanceSessionStatus::Aborted);
});

test('a session cannot be ended twice', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create();

    $this->actingAs($user)->postJson(route('surveillance.end', $session), [
        'ended_at_offset_ms' => 1000,
    ])->assertConflict();
});

test('ending a session returns 403 for a session owned by another user', function () {
    $session = SurveillanceSession::factory()->active()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('surveillance.end', $session), ['ended_at_offset_ms' => 1000])
        ->assertForbidden();
});

test('a heartbeat updates last_heartbeat_at', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->active()->create([
        'last_heartbeat_at' => now()->subMinutes(10),
    ]);

    $this->actingAs($user)->postJson(route('surveillance.heartbeat', $session))->assertOk();

    expect($session->refresh()->last_heartbeat_at->greaterThan(now()->subMinute()))->toBeTrue();
});
