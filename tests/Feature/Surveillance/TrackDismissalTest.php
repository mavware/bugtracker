<?php

use App\Models\BugTrack;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('dismissing a track excludes it from the analytics and the report payload', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create();
    $kept = BugTrack::factory()->for($session, 'session')->create();
    $dismissed = BugTrack::factory()->for($session, 'session')->create();

    $component = Livewire::actingAs($user)
        ->test('pages::surveillance.report', ['session' => $session])
        ->call('toggleDismissed', $dismissed->id);

    expect($dismissed->refresh()->dismissed_at)->not->toBeNull()
        ->and($session->refresh()->analytics['track_count'])->toBe(1);

    $trackIds = array_column($component->instance()->reportPayload()['tracks'], 'id');
    expect($trackIds)->toBe([$kept->id]);
});

test('restoring a dismissed track brings it back into the analytics', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create();
    $track = BugTrack::factory()->for($session, 'session')->dismissed()->create();

    Livewire::actingAs($user)
        ->test('pages::surveillance.report', ['session' => $session])
        ->call('toggleDismissed', $track->id);

    expect($track->refresh()->dismissed_at)->toBeNull()
        ->and($session->refresh()->analytics['track_count'])->toBe(1);
});

test('a track from a different session cannot be toggled', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create();
    $foreignTrack = BugTrack::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::surveillance.report', ['session' => $session])
        ->call('toggleDismissed', $foreignTrack->id);
})->throws(ModelNotFoundException::class);
