<?php

namespace App\Actions\Surveillance;

use App\Enums\SurveillanceSessionStatus;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ValidatedInput;
use InvalidArgumentException;

/**
 * Bring a night recorded in a browser without an account into the user's
 * account. The night arrives in chunks (the browser sends at most fifty tracks
 * per request), every chunk carrying the night's metadata and the uuid the
 * browser gave it. The first chunk creates the session, already finished and
 * dated when the night really happened; every chunk merges its tracks into that
 * session, so any chunk can be sent again. The live capture endpoints cannot do
 * this: the reference upload stamps started_at with the current time, and a
 * half-imported night would sit on the dashboard as "recording now".
 */
class ImportLocalNight
{
    public function __construct(
        private readonly StoreClosedTracks $storeClosedTracks,
        private readonly ComputeSessionAnalytics $computeSessionAnalytics,
    ) {}

    /**
     * @return array{session: SurveillanceSession, created: bool, accepted: list<string>, duplicate: list<string>}
     */
    public function handle(User $user, ValidatedInput $validated): array
    {
        return DB::transaction(function () use ($user, $validated): array {
            $startedAt = $validated->date('started_at');
            $endedAt = $validated->date('ended_at');

            if ($startedAt === null || $endedAt === null) {
                throw new InvalidArgumentException('An imported night needs both of its timestamps.');
            }

            $room = $validated->string('room')->trim()->toString();

            $session = $user->surveillanceSessions()->firstOrCreate(
                ['imported_local_id' => $validated->string('local_id')->toString()],
                [
                    'name' => __('Night of :date', ['date' => SurveillanceSession::nightDateFor($startedAt)->format('M j')]),
                    'room' => $room !== '' ? $room : null,
                    'status' => $validated->boolean('aborted')
                        ? SurveillanceSessionStatus::Aborted
                        : SurveillanceSessionStatus::Completed,
                    'started_at' => $startedAt,
                    'ended_at' => $endedAt,
                    'last_heartbeat_at' => $endedAt,
                    'frame_width' => $validated->integer('frame_width'),
                    'frame_height' => $validated->integer('frame_height'),
                    'settings' => $validated->has('settings') ? $validated->array('settings') : null,
                ],
            );

            $created = $session->wasRecentlyCreated;

            if ($session->reference_image_path === null) {
                $this->storeReference($session, $validated->string('reference_image')->toString());
            }

            $stored = $this->storeClosedTracks->handle($session, $validated);

            $this->computeSessionAnalytics->handle($session);

            return [
                'session' => $session,
                'created' => $created,
                'accepted' => $stored['accepted'],
                'duplicate' => $stored['duplicate'],
            ];
        });
    }

    /**
     * Keep the reference photo, subject to the same checks the live upload
     * applies: a real JPEG, no larger than two megabytes.
     */
    private function storeReference(SurveillanceSession $session, string $encoded): void
    {
        if ($encoded === '') {
            return;
        }

        $binary = base64_decode($encoded, true);

        if ($binary === false || strlen($binary) > 2048 * 1024 || ! str_starts_with($binary, "\xFF\xD8\xFF")) {
            return;
        }

        $path = $session->storageDirectory().'/reference.jpg';

        Storage::disk('local')->put($path, $binary);

        $session->update(['reference_image_path' => $path]);
    }
}
