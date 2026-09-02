<?php

use App\Enums\SurveillanceSessionStatus;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * @return array<string, mixed>
 */
function validTrackPayload(array $overrides = []): array
{
    return array_merge([
        'client_track_id' => 'track-1',
        'start_offset_ms' => 1000,
        'end_offset_ms' => 3000,
        'points' => [
            [1000, 5, 360],
            [2000, 400, 380],
            [3000, 1275, 400],
        ],
    ], $overrides);
}

function fakeCropBase64(): string
{
    return base64_encode("\xFF\xD8\xFF\xE0".str_repeat('a', 200));
}

test('a batch of tracks is stored with edges classified and crops persisted', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->active()->create();

    $response = $this->actingAs($user)->postJson(route('surveillance.tracks.store', $session), [
        'tracks' => [
            validTrackPayload(['start_crop' => fakeCropBase64(), 'end_crop' => fakeCropBase64()]),
            validTrackPayload(['client_track_id' => 'track-2']),
        ],
    ]);

    $response->assertOk()->assertJson(['accepted' => ['track-1', 'track-2'], 'duplicate' => []]);

    expect($session->tracks()->count())->toBe(2);

    $track = $session->tracks()->where('client_track_id', 'track-1')->first();
    expect($track->point_count)->toBe(3)
        ->and($track->points)->toBe([[1000, 5, 360], [2000, 400, 380], [3000, 1275, 400]])
        ->and($track->entry_edge)->toBe('left')
        ->and($track->exit_edge)->toBe('right')
        ->and($track->start_crop_path)->toBe("surveillance/{$session->id}/crops/track-1-start.jpg")
        ->and($track->end_crop_path)->toBe("surveillance/{$session->id}/crops/track-1-end.jpg");
    Storage::disk('local')->assertExists($track->start_crop_path);
    Storage::disk('local')->assertExists($track->end_crop_path);
});

test('resubmitting a track with the same client_track_id is idempotent', function () {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->active()->create();

    $payload = ['tracks' => [validTrackPayload()]];

    $this->actingAs($user)->postJson(route('surveillance.tracks.store', $session), $payload)->assertOk();

    $response = $this->actingAs($user)->postJson(route('surveillance.tracks.store', $session), $payload);

    $response->assertOk()->assertJson(['accepted' => [], 'duplicate' => ['track-1']]);
    expect($session->tracks()->count())->toBe(1);
});

test('track ingestion rejects invalid payloads', function (array $track, string $errorKey) {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->active()->create();

    $this->actingAs($user)
        ->postJson(route('surveillance.tracks.store', $session), ['tracks' => [$track]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$errorKey]);

    expect($session->tracks()->count())->toBe(0);
})->with([
    'too many points' => [
        validTrackPayload(['points' => array_map(fn (int $i) => [$i, 1, 1], range(0, 5000))]),
        'tracks.0.points',
    ],
    'malformed triplet' => [
        validTrackPayload(['points' => [[1000, 5], [2000, 400, 380]]]),
        'tracks.0.points.0',
    ],
    'x beyond frame width' => [
        validTrackPayload(['points' => [[1000, 2000, 360], [2000, 400, 380]]]),
        'tracks.0.points.0.1',
    ],
    'end before start' => [
        validTrackPayload(['end_offset_ms' => 500]),
        'tracks.0.end_offset_ms',
    ],
    'oversized crop' => [
        validTrackPayload(['start_crop' => str_repeat('a', 40001)]),
        'tracks.0.start_crop',
    ],
]);

test('an invalid crop payload is discarded but the track is still stored', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->active()->create();

    $this->actingAs($user)->postJson(route('surveillance.tracks.store', $session), [
        'tracks' => [validTrackPayload(['start_crop' => base64_encode('not a jpeg')])],
    ])->assertOk();

    $track = $session->tracks()->first();
    expect($track)->not->toBeNull()
        ->and($track->start_crop_path)->toBeNull();
});

test('track ingestion returns 409 when the session is not active', function (SurveillanceSessionStatus $status) {
    $user = User::factory()->create();
    $session = SurveillanceSession::factory()->for($user)->create(['status' => $status]);

    $this->actingAs($user)
        ->postJson(route('surveillance.tracks.store', $session), ['tracks' => [validTrackPayload()]])
        ->assertConflict();
})->with([
    'pending session' => SurveillanceSessionStatus::Pending,
    'completed session' => SurveillanceSessionStatus::Completed,
]);

test('track ingestion returns 403 for a session owned by another user', function () {
    $session = SurveillanceSession::factory()->active()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('surveillance.tracks.store', $session), ['tracks' => [validTrackPayload()]])
        ->assertForbidden();
});
