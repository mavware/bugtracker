<?php

namespace App\Http\Controllers\Surveillance;

use App\Http\Controllers\Controller;
use App\Models\BugTrack;
use App\Models\SurveillanceSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImageController extends Controller
{
    /**
     * These URLs are keyed by session and track id, not by content, so the same
     * URL can legitimately serve a different photo later — after a delete, or once
     * a reset database hands the id out again. A cached copy would then outlive the
     * session it belonged to and be shown against the next one, which is what
     * "no-store" prevents. It also keeps photographs of the inside of someone's
     * home out of the browser's on-disk cache.
     */
    private const CACHE_HEADERS = ['Cache-Control' => 'private, no-store'];

    /**
     * Stream the session's reference frame to its owner.
     */
    public function showReference(SurveillanceSession $session): StreamedResponse
    {
        Gate::authorize('view', $session);

        abort_if($session->reference_image_path === null, 404);
        abort_unless(Storage::disk('local')->exists($session->reference_image_path), 404);

        return Storage::disk('local')->response($session->reference_image_path, null, self::CACHE_HEADERS);
    }

    /**
     * Stream a track's start or end verification crop to the session owner.
     */
    public function showCrop(SurveillanceSession $session, BugTrack $track, string $position): StreamedResponse
    {
        Gate::authorize('view', $session);

        $path = $position === 'start' ? $track->start_crop_path : $track->end_crop_path;

        abort_if($path === null, 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, self::CACHE_HEADERS);
    }
}
