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

        $validated = $this->validatedInput($request, $this->referenceRules());

        $path = $request->file('image')->storeAs($session->storageDirectory(), 'reference.jpg', 'local');

        $session->update([
            'reference_image_path' => $path,
            'frame_width' => $validated->integer('frame_width'),
            'frame_height' => $validated->integer('frame_height'),
            'settings' => $validated->has('settings') ? $validated->array('settings') : null,
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

        $validated = $this->validatedInput($request, $this->trackBatchRules($session));

        $existing = $session->tracks()
            ->whereIn('client_track_id', $validated->collect('tracks')->pluck('client_track_id'))
            ->pluck('client_track_id')
            ->all();

        $width = $session->frame_width ?? 0;
        $height = $session->frame_height ?? 0;

        $accepted = [];
        $duplicate = [];

        foreach ($validated->collect('tracks')->keys() as $index) {
            $clientTrackId = $validated->string("tracks.$index.client_track_id")->toString();

            if (in_array($clientTrackId, $existing, true)) {
                $duplicate[] = $clientTrackId;

                continue;
            }

            $points = $validated->array("tracks.$index.points");
            $first = "tracks.$index.points.".array_key_first($points);
            $last = "tracks.$index.points.".array_key_last($points);

            $session->tracks()->create([
                'client_track_id' => $clientTrackId,
                'start_offset_ms' => $validated->integer("tracks.$index.start_offset_ms"),
                'end_offset_ms' => $validated->integer("tracks.$index.end_offset_ms"),
                'point_count' => count($points),
                'points' => $points,
                'entry_edge' => ComputeSessionAnalytics::classifyEdge(
                    [$validated->integer("$first.1"), $validated->integer("$first.2")], $width, $height
                ),
                'exit_edge' => ComputeSessionAnalytics::classifyEdge(
                    [$validated->integer("$last.1"), $validated->integer("$last.2")], $width, $height
                ),
                'start_crop_path' => $this->storeCrop($session, $clientTrackId, $validated->string("tracks.$index.start_crop")->toString(), 'start'),
                'end_crop_path' => $this->storeCrop($session, $clientTrackId, $validated->string("tracks.$index.end_crop")->toString(), 'end'),
            ]);

            $accepted[] = $clientTrackId;
        }

        return response()->json(['accepted' => $accepted, 'duplicate' => $duplicate]);
    }

    /**
     * Record that the capture page is still alive.
     */
    public function heartbeat(SurveillanceSession $session): JsonResponse
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

        $validated = $this->validatedInput($request, $this->endRules());

        $session->update([
            'status' => $validated->boolean('aborted')
                ? SurveillanceSessionStatus::Aborted
                : SurveillanceSessionStatus::Completed,
            'ended_at' => ($session->started_at ?? now())->addMilliseconds($validated->integer('ended_at_offset_ms')),
        ]);

        $analytics->handle($session);

        return response()->json([
            'status' => $session->status,
            'report_url' => route('surveillance.report', $session),
        ]);
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
