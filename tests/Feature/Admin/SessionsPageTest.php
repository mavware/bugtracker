<?php

use App\Enums\SurveillanceSessionStatus;
use App\Models\BugTrack;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('sessions can be filtered by status and searched by owner email', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create(['email' => 'dana@example.com']);
    SurveillanceSession::factory()->for($owner)->completed()->create(['name' => 'Alvarez night']);
    SurveillanceSession::factory()->active()->create(['name' => 'Someone else\'s night']);

    Livewire::actingAs($admin)
        ->test('pages::admin.sessions')
        ->assertSee('Alvarez night')
        ->assertSee('Someone else\'s night')
        ->set('status', SurveillanceSessionStatus::Completed->value)
        ->assertSee('Alvarez night')
        ->assertDontSee('Someone else\'s night')
        ->set('status', '')
        ->set('search', 'dana@example.com')
        ->assertSee('Alvarez night')
        ->assertDontSee('Someone else\'s night');
});

test('a session stuck recording can be closed out and gets its analytics', function () {
    $admin = User::factory()->admin()->create();
    $session = SurveillanceSession::factory()->active()->create([
        'last_heartbeat_at' => now()->subHours(3),
    ]);
    BugTrack::factory()->for($session, 'session')->create([
        'points' => [[0, 5, 360], [2000, 640, 715]],
        'point_count' => 2,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.sessions')
        ->call('endSession', $session->id);

    $session->refresh();
    expect($session->status)->toBe(SurveillanceSessionStatus::Completed)
        ->and($session->ended_at->format('H:i'))->toBe(now()->subHours(3)->format('H:i'))
        ->and($session->analytics['track_count'])->toBe(1);
});

test('a session that is not recording cannot be closed out', function () {
    $admin = User::factory()->admin()->create();
    $session = SurveillanceSession::factory()->completed()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.sessions')
        ->call('endSession', $session->id)
        ->assertStatus(409);
});

test('deleting a session removes its stored images', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $session = SurveillanceSession::factory()->completed()->create();
    Storage::disk('local')->put("surveillance/{$session->id}/reference.jpg", 'jpeg');

    Livewire::actingAs($admin)
        ->test('pages::admin.sessions')
        ->call('deleteSession', $session->id);

    expect(SurveillanceSession::find($session->id))->toBeNull();
    Storage::disk('local')->assertMissing("surveillance/{$session->id}/reference.jpg");
});
