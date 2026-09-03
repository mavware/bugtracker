<?php

use App\Enums\SurveillanceSessionStatus;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('storing the reference frame activates the session', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->create();

    $response = $this->actingAs($user)->postJson(route('surveillance.reference.store', $session), [
        'image' => UploadedFile::fake()->image('reference.jpg', 1280, 720),
        'frame_width' => 1280,
        'frame_height' => 720,
        'settings' => ['process_fps' => 6, 'diff_threshold' => 22],
    ]);

    $response->assertOk();

    $session->refresh();
    expect($session->status)->toBe(SurveillanceSessionStatus::Active)
        ->and($session->started_at)->not->toBeNull()
        ->and($session->last_heartbeat_at)->not->toBeNull()
        ->and($session->frame_width)->toBe(1280)
        ->and($session->frame_height)->toBe(720)
        ->and($session->settings)->toBe(['process_fps' => 6, 'diff_threshold' => 22])
        ->and($session->reference_image_path)->toBe("surveillance/$session->id/reference.jpg");
    Storage::disk('local')->assertExists($session->reference_image_path);
});

test('reference upload rejects a missing image and out-of-range dimensions', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->create();

    $response = $this->actingAs($user)->postJson(route('surveillance.reference.store', $session), [
        'frame_width' => 50,
        'frame_height' => 9999,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['image', 'frame_width', 'frame_height']);
    expect($session->refresh()->status)->toBe(SurveillanceSessionStatus::Pending);
});

test('reference upload returns 403 for a session owned by another user', function () {
    $session = SurveillanceSession::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('surveillance.reference.store', $session), [])
        ->assertForbidden();
});

test('reference upload returns 409 for a finished session', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->completed()->create();

    $this->actingAs($user)->postJson(route('surveillance.reference.store', $session), [
        'image' => UploadedFile::fake()->image('reference.jpg', 1280, 720),
        'frame_width' => 1280,
        'frame_height' => 720,
    ])->assertConflict();
});

test('reference upload returns 401 for guests', function () {
    $session = SurveillanceSession::factory()->create();

    $this->postJson(route('surveillance.reference.store', $session), [])
        ->assertUnauthorized();
});
