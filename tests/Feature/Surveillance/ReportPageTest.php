<?php

use App\Enums\SurveillanceSessionStatus;
use App\Models\BugTrack;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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

test('a discarded night says why it is missing from the trends', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create([
        'status' => SurveillanceSessionStatus::Aborted,
    ]);

    $this->actingAs($user)
        ->get(route('surveillance.report', $session))
        ->assertSee('You discarded this night')
        ->assertSee('left out of trends and entry points')
        ->assertSee('Keep this night');
});

test('a completed night carries no discarded notice', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create();

    $this->actingAs($user)
        ->get(route('surveillance.report', $session))
        ->assertDontSee('You discarded this night')
        ->assertSee('Discard night');
});

// Discarding a night lives on its report now, not on the capture page: the call
// is about the setup, and the setup can only be judged from what it caught.
test('a night can be discarded from its report and kept again', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::surveillance.report', ['session' => $session])
        ->call('toggleDiscarded');

    expect($session->refresh()->status)->toBe(SurveillanceSessionStatus::Aborted);

    $component->call('toggleDiscarded');

    expect($session->refresh()->status)->toBe(SurveillanceSessionStatus::Completed);
});

test('the report computes analytics lazily when missing', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create(['analytics' => null]);
    BugTrack::factory()->for($session, 'session')->create();

    $this->actingAs($user)->get(route('surveillance.report', $session))->assertOk();

    expect($session->refresh()->analytics)->not->toBeNull()
        ->and($session->analytics['track_count'])->toBe(1);
});

test('a recording night shows how it is going instead of a report', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->active()->create([
        'started_at' => Carbon::parse('2026-09-02 23:00'),
        'last_heartbeat_at' => now(),
    ]);
    BugTrack::factory()->count(2)->for($session, 'session')->create(['end_offset_ms' => 60000]);
    BugTrack::factory()->for($session, 'session')->create(['end_offset_ms' => 9000000]);
    BugTrack::factory()->for($session, 'session')->dismissed()->create(['end_offset_ms' => 20000000]);

    $this->actingAs($user)
        ->get(route('surveillance.report', $session))
        ->assertSee('Recording now')
        ->assertSeeInOrder(['Sightings so far', '3', 'Last seen', '01:30'])
        ->assertDontSee('End night now')
        ->assertDontSee('id="report-data"', false);
});

test('a night that has not started points at the capture page', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('surveillance.report', $session))
        ->assertSee('This night has not started yet')
        ->assertSee(route('surveillance.capture', $session));
});

test('a recording night offers to be ended once its device goes quiet', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->active()->create([
        'last_heartbeat_at' => now()->subMinutes(10),
    ]);

    $this->actingAs($user)
        ->get(route('surveillance.report', $session))
        ->assertSee('The capture device has gone quiet')
        ->assertSee('End night now');
});

test('a night whose device went quiet can be ended at its last check-in', function () {
    $user = User::factory()->create();
    $lastHeartbeat = Carbon::parse('2026-09-03 03:15:00');
    $session = SurveillanceSession::factory()->for($user)->active()->create([
        'analytics' => null,
        'last_heartbeat_at' => $lastHeartbeat,
    ]);
    BugTrack::factory()->for($session, 'session')->create();

    Livewire::actingAs($user)
        ->test('pages::surveillance.report', ['session' => $session])
        ->call('endStuckNight')
        ->assertRedirect(route('surveillance.report', $session));

    $session->refresh();
    expect($session->status)->toBe(SurveillanceSessionStatus::Completed)
        ->and($session->ended_at->equalTo($lastHeartbeat))->toBeTrue()
        ->and($session->analytics['track_count'])->toBe(1);
});

test('a night cannot be ended from its page while the device is still checking in', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->active()->create(['last_heartbeat_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::surveillance.report', ['session' => $session])
        ->call('endStuckNight')
        ->assertNoRedirect();

    expect($session->refresh()->status)->toBe(SurveillanceSessionStatus::Active)
        ->and($session->ended_at)->toBeNull();
});

test('another user cannot end a quiet night', function () {
    $session = SurveillanceSession::factory()->active()->create(['last_heartbeat_at' => now()->subMinutes(10)]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::surveillance.report', ['session' => $session])
        ->assertForbidden();
});

test('the page reloads into the report once the device has ended the night', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->active()->create();

    $component = Livewire::actingAs($user)->test('pages::surveillance.report', ['session' => $session]);
    $component->call('refreshNightInProgress')->assertNoRedirect();

    $session->update(['status' => SurveillanceSessionStatus::Completed, 'ended_at' => now()]);

    $component->call('refreshNightInProgress')->assertRedirect(route('surveillance.report', $session));
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

// These URLs are keyed by id, so a browser that kept a copy would show a deleted
// session's photo against whichever session is handed that id next.
test('session images are never stored by the browser', function (string $route) {
    Storage::fake('local');
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create();
    $track = BugTrack::factory()->for($session, 'session')->create([
        'start_crop_path' => "surveillance/$session->id/crops/t1-start.jpg",
    ]);
    Storage::disk('local')->put($session->reference_image_path, 'jpeg-bytes');
    Storage::disk('local')->put($track->start_crop_path, 'jpeg-bytes');

    $url = $route === 'reference'
        ? route('surveillance.reference.show', $session)
        : route('surveillance.crop.show', [$session, $track, 'start']);

    $this->actingAs($user)
        ->get($url)
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');
})->with(['reference', 'crop']);

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
        'start_crop_path' => "surveillance/$session->id/crops/t1-start.jpg",
    ]);
    Storage::disk('local')->put($track->start_crop_path, 'jpeg-bytes');

    $this->actingAs($user)
        ->get(route('surveillance.crop.show', [$session, $track, 'start']))
        ->assertOk();
});
