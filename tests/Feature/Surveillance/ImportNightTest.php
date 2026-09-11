<?php

use App\Actions\Surveillance\ComputeNightlyTrend;
use App\Enums\SurveillanceSessionStatus;
use App\Models\Customer;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * A night as the browser sends it from its own store: real timestamps, the
 * reference photo inline, and tracks in the ingest shape.
 *
 * @return array<string, mixed>
 */
function importPayload(array $overrides = []): array
{
    return array_merge([
        'local_id' => '4f1a9d0e-7b7d-4c1e-9d5e-3f0a1b2c3d4e',
        // 00:30 on the 9th belongs to the night of the 8th: the 6am rule.
        'started_at' => '2026-09-09T00:30:00.000Z',
        'ended_at' => '2026-09-09T05:30:00.000Z',
        'aborted' => false,
        'room' => 'Kitchen',
        'frame_width' => 1280,
        'frame_height' => 720,
        'settings' => ['procWidth' => 320],
        'reference_image' => base64_encode("\xFF\xD8\xFF\xE0".str_repeat('r', 300)),
        'tracks' => [
            importTrack('track-1', ['start_crop' => base64_encode("\xFF\xD8\xFF\xE0".str_repeat('a', 200))]),
        ],
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function importTrack(string $clientTrackId, array $overrides = []): array
{
    return array_merge([
        'client_track_id' => $clientTrackId,
        'start_offset_ms' => 1000,
        'end_offset_ms' => 3000,
        'points' => [[1000, 5, 360], [2000, 640, 360], [3000, 640, 715]],
        'start_crop' => null,
        'end_crop' => null,
        'dismissed' => false,
    ], $overrides);
}

test('importing a night creates a finished session dated when it really happened, with its photo and tracks', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('surveillance.import'), importPayload());

    $response->assertOk()->assertJson(['created' => true, 'accepted' => ['track-1'], 'duplicate' => []]);

    $session = SurveillanceSession::query()->sole();
    expect($response->json('session_id'))->toBe($session->id)
        ->and($response->json('report_url'))->toBe(route('surveillance.report', $session))
        ->and($session->user_id)->toBe($user->id)
        ->and($session->imported_local_id)->toBe('4f1a9d0e-7b7d-4c1e-9d5e-3f0a1b2c3d4e')
        ->and($session->name)->toBe('Night of Sep 8')
        ->and($session->room)->toBe('Kitchen')
        ->and($session->status)->toBe(SurveillanceSessionStatus::Completed)
        ->and($session->started_at->toIso8601ZuluString())->toBe('2026-09-09T00:30:00Z')
        ->and($session->ended_at->toIso8601ZuluString())->toBe('2026-09-09T05:30:00Z')
        ->and($session->last_heartbeat_at->equalTo($session->ended_at))->toBeTrue()
        ->and($session->frame_width)->toBe(1280)
        ->and($session->settings)->toBe(['procWidth' => 320])
        ->and($session->reference_image_path)->toBe("surveillance/$session->id/reference.jpg");
    Storage::disk('local')->assertExists($session->reference_image_path);

    $track = $session->tracks()->sole();
    expect($track->entry_edge)->toBe('left')
        ->and($track->exit_edge)->toBe('bottom')
        ->and($track->point_count)->toBe(3)
        ->and($track->dismissed_at)->toBeNull()
        ->and($track->start_crop_path)->toBe("surveillance/$session->id/crops/track-1-start.jpg");
    Storage::disk('local')->assertExists($track->start_crop_path);

    expect($session->analytics['track_count'])->toBe(1)
        ->and($session->analytics['duration_ms'])->toBe(5 * 60 * 60 * 1000)
        ->and($session->analytics['entry_zones'][0]['edge'])->toBe('left');
});

test('a sighting the user had already marked as not a bug stays dismissed and out of the count', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('surveillance.import'), importPayload([
        'tracks' => [importTrack('real'), importTrack('false-positive', ['dismissed' => true])],
    ]))->assertOk();

    $session = SurveillanceSession::query()->sole();
    expect($session->tracks()->where('client_track_id', 'false-positive')->sole()->dismissed_at)->not->toBeNull()
        ->and($session->analytics['track_count'])->toBe(1);
});

test('a second chunk for the same night merges into the session and re-sent tracks are duplicates', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('surveillance.import'), importPayload())->assertOk();

    $response = $this->actingAs($user)->postJson(route('surveillance.import'), importPayload([
        'reference_image' => null,
        'tracks' => [importTrack('track-1'), importTrack('track-2')],
    ]));

    $response->assertOk()->assertJson(['created' => false, 'accepted' => ['track-2'], 'duplicate' => ['track-1']]);

    expect(SurveillanceSession::query()->count())->toBe(1);
    $session = SurveillanceSession::query()->sole();
    expect($session->tracks()->count())->toBe(2)
        ->and($session->analytics['track_count'])->toBe(2)
        ->and($session->reference_image_path)->not->toBeNull();
});

test('a discarded night is imported as aborted, and a night with no sightings still counts', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('surveillance.import'), importPayload([
        'aborted' => true,
        'room' => null,
        'reference_image' => null,
        'tracks' => [],
    ]))->assertOk()->assertJson(['created' => true, 'accepted' => [], 'duplicate' => []]);

    $session = SurveillanceSession::query()->sole();
    expect($session->status)->toBe(SurveillanceSessionStatus::Aborted)
        ->and($session->room)->toBeNull()
        ->and($session->reference_image_path)->toBeNull()
        ->and($session->analytics['track_count'])->toBe(0);
});

test('an imported night shows up in the nightly trend under its real date', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('surveillance.import'), importPayload())->assertOk();

    $trend = app(ComputeNightlyTrend::class)->handle($user, null, null);

    expect($trend['nights'])->toHaveCount(1)
        ->and($trend['nights'][0]['date'])->toBe('2026-09-08')
        ->and($trend['nights'][0]['count'])->toBe(1);
});

test('the same local id under another account is a different night', function () {
    $this->actingAs(User::factory()->create())->postJson(route('surveillance.import'), importPayload())->assertOk();
    $this->actingAs(User::factory()->create())->postJson(route('surveillance.import'), importPayload())->assertOk();

    expect(SurveillanceSession::query()->count())->toBe(2);
});

test('a reference that is not a jpeg is dropped, the rest of the night still imports', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('surveillance.import'), importPayload([
        'reference_image' => base64_encode('not a jpeg at all'),
    ]))->assertOk();

    expect(SurveillanceSession::query()->sole()->reference_image_path)->toBeNull();
    Storage::disk('local')->assertMissing('surveillance/1/reference.jpg');
});

test('the import is validated like the live upload', function (array $overrides, string $field) {
    $this->actingAs(User::factory()->create())
        ->postJson(route('surveillance.import'), importPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing local id' => [['local_id' => null], 'local_id'],
    'local id that is not a uuid' => [['local_id' => 'night-1'], 'local_id'],
    'ended before it started' => [['ended_at' => '2026-09-08T23:00:00.000Z'], 'ended_at'],
    'too many tracks' => [['tracks' => array_map(fn (int $i) => importTrack("t$i"), range(1, 51))], 'tracks'],
    'too many points' => [['tracks' => [importTrack('t', ['points' => array_fill(0, 5001, [0, 1, 1])])]], 'tracks.0.points'],
    'a point outside the frame' => [['tracks' => [importTrack('t', ['points' => [[0, 1, 1], [1, 1281, 1]]])]], 'tracks.0.points.1.1'],
    'a frame too small to be real' => [['frame_width' => 10], 'frame_width'],
]);

test('a guest cannot import a night', function () {
    $this->postJson(route('surveillance.import'), importPayload())->assertUnauthorized();
});

// Each night is filed under a customer as it is imported, so a technician's
// nights land on the right property without a second visit to the report.
test('an imported night can be filed under one of the user\'s customers', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('surveillance.import'), importPayload(['customer_id' => $customer->id]))
        ->assertOk();

    expect(SurveillanceSession::query()->sole()->customer_id)->toBe($customer->id);
});

test('an imported night cannot be filed under another user\'s customer', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $strangersCustomer = Customer::factory()->create();

    $this->actingAs($user)
        ->postJson(route('surveillance.import'), importPayload(['customer_id' => $strangersCustomer->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_id']);

    expect(SurveillanceSession::query()->count())->toBe(0);
});

test('an imported night with no customer, or an empty one, is filed under none', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('surveillance.import'), importPayload(['customer_id' => null]))
        ->assertOk();

    expect(SurveillanceSession::query()->sole()->customer_id)->toBeNull();
});
