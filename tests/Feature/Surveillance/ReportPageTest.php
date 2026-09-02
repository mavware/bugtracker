<?php

use App\Models\BugTrack;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('the report renders the data island and sightings for a finished session', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create();
    BugTrack::factory()->for($session, 'session')->create(['entry_edge' => 'left']);

    $this->actingAs($user)
        ->get(route('surveillance.report', $session))
        ->assertOk()
        ->assertSee('id="report-data"', false)
        ->assertSee('"referenceImageUrl"', false)
        ->assertSee(__('Sightings'));
});

test('the report computes analytics lazily when missing', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create(['analytics' => null]);
    BugTrack::factory()->for($session, 'session')->create();

    $this->actingAs($user)->get(route('surveillance.report', $session))->assertOk();

    expect($session->refresh()->analytics)->not->toBeNull()
        ->and($session->analytics['track_count'])->toBe(1);
});

test('an unfinished session shows the still-recording state instead of a report', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->active()->create();

    $this->actingAs($user)
        ->get(route('surveillance.report', $session))
        ->assertOk()
        ->assertSee(__('This session is still recording'));
});

test('the report returns 403 for another user\'s session', function () {
    $session = SurveillanceSession::factory()->completed()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('surveillance.report', $session))
        ->assertForbidden();
});

test('the reference image streams for the owner', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create();
    Storage::disk('local')->put($session->reference_image_path, 'jpeg-bytes');

    $this->actingAs($user)
        ->get(route('surveillance.reference.show', $session))
        ->assertOk();
});

test('the reference image returns 403 for another user and 404 when missing', function () {
    Storage::fake('local');
    $session = SurveillanceSession::factory()->completed()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('surveillance.reference.show', $session))
        ->assertForbidden();

    $this->actingAs($session->user)
        ->get(route('surveillance.reference.show', $session))
        ->assertNotFound();
});

test('a crop from a different session 404s via scoped binding', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create();
    $foreignTrack = BugTrack::factory()->create();

    $this->actingAs($user)
        ->get(route('surveillance.crop.show', [$session, $foreignTrack, 'start']))
        ->assertNotFound();
});

test('a stored crop streams for the owner', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create();
    $track = BugTrack::factory()->for($session, 'session')->create([
        'start_crop_path' => "surveillance/{$session->id}/crops/t1-start.jpg",
    ]);
    Storage::disk('local')->put($track->start_crop_path, 'jpeg-bytes');

    $this->actingAs($user)
        ->get(route('surveillance.crop.show', [$session, $track, 'start']))
        ->assertOk();
});
