<?php

namespace App\Actions\Surveillance;

use App\Enums\SurveillanceSessionStatus;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ComputeEntryPointHeatmap
{
    /**
     * Aggregate confirmed track endpoints from the user's finished sessions into
     * entry/exit zones projected onto a single backdrop frame. Sessions may have
     * been recorded at different resolutions, so endpoints are scaled into the
     * backdrop session's frame before clustering. Pass a room to compare only
     * sessions recorded with the camera in the same place.
     *
     * @return array{
     *     session_count: int,
     *     track_count: int,
     *     backdrop: SurveillanceSession|null,
     *     entry_zones: array<int, array{edge: string, from: int, to: int, center: array{0: int, 1: int}, count: int}>,
     *     exit_zones: array<int, array{edge: string, from: int, to: int, center: array{0: int, 1: int}, count: int}>,
     * }
     */
    public function handle(User $user, ?string $room = null): array
    {
        $sessions = $user->surveillanceSessions()
            ->whereIn('status', [SurveillanceSessionStatus::Completed, SurveillanceSessionStatus::Aborted])
            ->whereNotNull('frame_width')
            ->whereNotNull('frame_height')
            ->when($room !== null, fn (Builder $query) => $query->where('room', $room))
            ->orderByDesc('started_at')
            ->get();

        $backdrop = $sessions->first(fn (SurveillanceSession $session) => $session->reference_image_path !== null)
            ?? $sessions->first();

        if ($backdrop === null) {
            return [
                'session_count' => 0,
                'track_count' => 0,
                'backdrop' => null,
                'entry_zones' => [],
                'exit_zones' => [],
            ];
        }

        $targetWidth = (int) $backdrop->frame_width;
        $targetHeight = (int) $backdrop->frame_height;

        $entryPoints = [];
        $exitPoints = [];
        $trackCount = 0;

        foreach ($sessions as $session) {
            $scaleX = $targetWidth / $session->frame_width;
            $scaleY = $targetHeight / $session->frame_height;

            $tracks = $session->tracks()->confirmed()->get(['id', 'points']);

            foreach ($tracks as $track) {
                $points = $track->points;

                if ($points === []) {
                    continue;
                }

                $trackCount++;

                $entryPoints[] = [(int) round($points[0][1] * $scaleX), (int) round($points[0][2] * $scaleY)];
                $last = end($points);
                $exitPoints[] = [(int) round($last[1] * $scaleX), (int) round($last[2] * $scaleY)];
            }
        }

        return [
            'session_count' => $sessions->count(),
            'track_count' => $trackCount,
            'backdrop' => $backdrop,
            'entry_zones' => ComputeSessionAnalytics::clusterEdgePoints($entryPoints, $targetWidth, $targetHeight),
            'exit_zones' => ComputeSessionAnalytics::clusterEdgePoints($exitPoints, $targetWidth, $targetHeight),
        ];
    }
}
