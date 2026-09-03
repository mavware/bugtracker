<?php

use App\Models\BugTrack;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

test('the panel reports the sightings so far tonight, ignoring dismissed ones', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->active()->create([
        'name' => 'Night of Sep 2',
        'started_at' => Carbon::parse('2026-09-02 23:00'),
    ]);
    BugTrack::factory()->count(2)->for($session, 'session')->create(['end_offset_ms' => 60000]);
    BugTrack::factory()->for($session, 'session')->create(['end_offset_ms' => 9000000]);
    BugTrack::factory()->for($session, 'session')->dismissed()->create(['end_offset_ms' => 20000000]);

    $component = Livewire::actingAs($user)->test('surveillance.tonight')->assertSee('Night of Sep 2');

    $tonight = $component->instance()->tonight;
    expect($tonight['sightings'])->toBe(3)
        ->and($tonight['last_sighting_at']->format('H:i'))->toBe('01:30');
});

test('the panel warns when the capture device stops checking in', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->for($user)->active()->create([
        'last_heartbeat_at' => now()->subMinutes(10),
    ]);

    Livewire::actingAs($user)
        ->test('surveillance.tonight')
        ->assertSee('The capture device has gone quiet');
});

test('the panel stays quiet while the capture device is checking in', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->for($user)->active()->create(['last_heartbeat_at' => now()]);

    Livewire::actingAs($user)
        ->test('surveillance.tonight')
        ->assertSee('Recording now')
        ->assertDontSee('The capture device has gone quiet');
});

test('the panel shows nothing when no session is recording', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->for($user)->completed()->create();

    Livewire::actingAs($user)
        ->test('surveillance.tonight')
        ->assertDontSee('Recording now');
});

test('the panel never shows another user\'s recording session', function () {
    SurveillanceSession::factory()->active()->create(['name' => 'Someone else\'s night']);

    Livewire::actingAs(User::factory()->create())
        ->test('surveillance.tonight')
        ->assertDontSee('Someone else\'s night')
        ->assertDontSee('Recording now');
});

test('the dashboard shows the panel while a session is recording', function () {
    $user = User::factory()->create();
    SurveillanceSession::factory()->for($user)->active()->create(['name' => 'Night of Sep 2']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSeeLivewire('surveillance.tonight')
        ->assertSee('Recording now');
});
