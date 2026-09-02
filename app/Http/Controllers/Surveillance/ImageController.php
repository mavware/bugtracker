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
     * Stream the session's reference frame to its owner.
     */
    public function showReference(SurveillanceSession $session): StreamedResponse
    {
        Gate::authorize('view', $session);

        abort_if($session->reference_image_path === null, 404);
        abort_unless(Storage::disk('local')->exists($session->reference_image_path), 404);

        return Storage::disk('local')->response($session->reference_image_path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
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

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
