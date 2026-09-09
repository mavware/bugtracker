<?php

namespace App\Actions\Surveillance;

use App\Models\SurveillanceSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ValidatedInput;

/**
 * Insert a validated batch of closed tracks into a session. Idempotent per
 * client_track_id: a track already stored is reported as a duplicate and left
 * alone, so a retried upload or a re-sent import chunk is always safe.
 * Shared by the live capture endpoint and the import of a device-local night.
 */
class StoreClosedTracks
{
    /**
     * @param  ValidatedInput  $validated  Holding a "$key" array of tracks in the ingest shape.
     * @return array{accepted: list<string>, duplicate: list<string>}
     */
    public function handle(SurveillanceSession $session, ValidatedInput $validated, string $key = 'tracks'): array
    {
        $existing = $session->tracks()
            ->whereIn('client_track_id', $validated->collect($key)->pluck('client_track_id'))
            ->pluck('client_track_id')
            ->all();

        $width = $session->frame_width ?? 0;
        $height = $session->frame_height ?? 0;

        $accepted = [];
        $duplicate = [];

        foreach ($validated->collect($key)->keys() as $index) {
            $clientTrackId = $validated->string("$key.$index.client_track_id")->toString();

            if (in_array($clientTrackId, $existing, true)) {
                $duplicate[] = $clientTrackId;

                continue;
            }

            $points = $validated->array("$key.$index.points");
            $first = "$key.$index.points.".array_key_first($points);
            $last = "$key.$index.points.".array_key_last($points);

            $session->tracks()->create([
                'client_track_id' => $clientTrackId,
                'start_offset_ms' => $validated->integer("$key.$index.start_offset_ms"),
                'end_offset_ms' => $validated->integer("$key.$index.end_offset_ms"),
                'point_count' => count($points),
                'points' => $points,
                'entry_edge' => ComputeSessionAnalytics::classifyEdge(
                    [$validated->integer("$first.1"), $validated->integer("$first.2")], $width, $height
                ),
                'exit_edge' => ComputeSessionAnalytics::classifyEdge(
                    [$validated->integer("$last.1"), $validated->integer("$last.2")], $width, $height
                ),
                'start_crop_path' => $this->storeCrop($session, $clientTrackId, $validated->string("$key.$index.start_crop")->toString(), 'start'),
                'end_crop_path' => $this->storeCrop($session, $clientTrackId, $validated->string("$key.$index.end_crop")->toString(), 'end'),
                'dismissed_at' => $validated->boolean("$key.$index.dismissed") ? now() : null,
            ]);

            $accepted[] = $clientTrackId;
        }

        return ['accepted' => $accepted, 'duplicate' => $duplicate];
    }

    /**
     * Decode and persist an inline base64 JPEG crop, rejecting oversized or invalid payloads.
     */
    private function storeCrop(SurveillanceSession $session, string $clientTrackId, string $encoded, string $position): ?string
    {
        if ($encoded === '') {
            return null;
        }

        $binary = base64_decode($encoded, true);

        if ($binary === false || strlen($binary) > 20480 || ! str_starts_with($binary, "\xFF\xD8\xFF")) {
            return null;
        }

        $path = $session->storageDirectory()."/crops/$clientTrackId-$position.jpg";

        Storage::disk('local')->put($path, $binary);

        return $path;
    }
}
