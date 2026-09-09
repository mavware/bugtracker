<?php

namespace App\Http\Controllers\Surveillance;

use App\Actions\Surveillance\ComputeSessionAnalytics;
use App\Actions\Surveillance\StoreClosedTracks;
use App\Concerns\SurveillanceValidationRules;
use App\Enums\SurveillanceSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\SurveillanceSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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
    public function storeTracks(Request $request, SurveillanceSession $session, StoreClosedTracks $storeClosedTracks): JsonResponse
    {
        Gate::authorize('update', $session);

        if ($session->status !== SurveillanceSessionStatus::Active) {
            return response()->json(['message' => 'Session is not active.'], 409);
        }

        $validated = $this->validatedInput($request, $this->trackBatchRules($session));

        return response()->json($storeClosedTracks->handle($session, $validated));
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
}
