<?php

namespace App\Http\Controllers\Surveillance;

use App\Actions\Surveillance\ComputeSessionAnalytics;
use App\Concerns\SurveillanceValidationRules;
use App\Enums\SurveillanceSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\SurveillanceSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CaptureController extends Controller
{
    use SurveillanceValidationRules;

    /**
     * Store the session's reference frame and activate the session.
     */
    public function storeReference(Request $request, SurveillanceSession $session): JsonResponse
    {
        Gate::authorize('update', $session);

        if ($session->status->isFinished()) {
            return response()->json(['message' => 'Session is already finished.'], 409);
        }

        $validated = $request->validate($this->referenceRules());

        $path = $request->file('image')->storeAs($session->storageDirectory(), 'reference.jpg', 'local');

        $session->update([
            'reference_image_path' => $path,
            'frame_width' => $validated['frame_width'],
            'frame_height' => $validated['frame_height'],
            'settings' => $validated['settings'] ?? null,
            'status' => SurveillanceSessionStatus::Active,
            'started_at' => now(),
            'last_heartbeat_at' => now(),
        ]);

        return response()->json(['status' => $session->status]);
    }

    /**
     * Store a batch of closed tracks. Idempotent per client_track_id.
     */
    public function storeTracks(Request $request, SurveillanceSession $session): JsonResponse
    {
        Gate::authorize('update', $session);

        if ($session->status !== SurveillanceSessionStatus::Active) {
            return response()->json(['message' => 'Session is not active.'], 409);
        }

        $validated = $request->validate($this->trackBatchRules($session));

        $existing = $session->tracks()
            ->whereIn('client_track_id', array_column($validated['tracks'], 'client_track_id'))
            ->pluck('client_track_id')
            ->all();

        $accepted = [];
        $duplicate = [];

        foreach ($validated['tracks'] as $track) {
            if (in_array($track['client_track_id'], $existing, true)) {
                $duplicate[] = $track['client_track_id'];

                continue;
            }

            $points = $track['points'];
            $first = $points[0];
            $last = end($points);

            $session->tracks()->create([
                'client_track_id' => $track['client_track_id'],
                'start_offset_ms' => $track['start_offset_ms'],
                'end_offset_ms' => $track['end_offset_ms'],
                'point_count' => count($points),
                'points' => $points,
                'entry_edge' => ComputeSessionAnalytics::classifyEdge([$first[1], $first[2]], $session->frame_width, $session->frame_height),
                'exit_edge' => ComputeSessionAnalytics::classifyEdge([$last[1], $last[2]], $session->frame_width, $session->frame_height),
                'start_crop_path' => $this->storeCrop($session, $track, 'start'),
                'end_crop_path' => $this->storeCrop($session, $track, 'end'),
            ]);

            $accepted[] = $track['client_track_id'];
        }

        return response()->json(['accepted' => $accepted, 'duplicate' => $duplicate]);
    }

    /**
     * Record that the capture page is still alive.
     */
    public function heartbeat(Request $request, SurveillanceSession $session): JsonResponse
    {
        Gate::authorize('update', $session);

        $session->update(['last_heartbeat_at' => now()]);

        return response()->json(['status' => $session->status]);
    }

    /**
     * Finish the session and compute its analytics.
     */
    public function end(Request $request, SurveillanceSession $session, ComputeSessionAnalytics $analytics): JsonResponse
    {
        Gate::authorize('update', $session);

        if ($session->status->isFinished()) {
            return response()->json(['message' => 'Session is already finished.'], 409);
        }

        $validated = $request->validate($this->endRules());

        $session->update([
            'status' => ($validated['aborted'] ?? false)
                ? SurveillanceSessionStatus::Aborted
                : SurveillanceSessionStatus::Completed,
            'ended_at' => ($session->started_at ?? now())->addMilliseconds($validated['ended_at_offset_ms']),
        ]);

        $analytics->handle($session);

        return response()->json([
            'status' => $session->status,
            'report_url' => route('surveillance.report', $session),
        ]);
    }

    /**
     * Decode and persist an inline base64 JPEG crop, rejecting oversized or invalid payloads.
     *
     * @param  array<string, mixed>  $track
     */
    private function storeCrop(SurveillanceSession $session, array $track, string $position): ?string
    {
        $encoded = $track[$position.'_crop'] ?? null;

        if ($encoded === null) {
            return null;
        }

        $binary = base64_decode($encoded, true);

        if ($binary === false || strlen($binary) > 20480 || ! str_starts_with($binary, "\xFF\xD8\xFF")) {
            return null;
        }

        $path = $session->storageDirectory()."/crops/{$track['client_track_id']}-{$position}.jpg";

        Storage::disk('local')->put($path, $binary);

        return $path;
    }
}
