<?php

namespace App\Actions\Surveillance;

use App\Models\SurveillanceSession;
use Illuminate\Support\Carbon;

/**
 * A read-only glance at a night in progress, for checking on it from a device
 * other than the one recording. Shared by the dashboard's tonight panel and the
 * night's own page so the two never disagree about what "quiet" means.
 */
class DescribeNightInProgress
{
    /**
     * The capture device heartbeats every 60s; past this it has probably slept.
     */
    public const int STALE_HEARTBEAT_MINUTES = 3;

    /**
     * A night that was told to end itself and has not is `overdue`: the device
     * kept the deadline, so passing it with the session still active means the
     * device never got to act on it — asleep, or the tab gone.
     *
     * @return array{sightings: int, last_sighting_at: Carbon|null, heartbeat_stale: bool, overdue: bool}
     */
    public function handle(SurveillanceSession $session): array
    {
        $lastOffsetMs = $session->tracks()->confirmed()->max('end_offset_ms');

        return [
            'sightings' => $session->tracks()->confirmed()->count(),
            'last_sighting_at' => $lastOffsetMs !== null && $session->started_at !== null
                ? $session->started_at->copy()->addMilliseconds((int) $lastOffsetMs)
                : null,
            'heartbeat_stale' => $session->last_heartbeat_at === null
                || $session->last_heartbeat_at->lt(now()->subMinutes(self::STALE_HEARTBEAT_MINUTES)),
            'overdue' => $session->planned_end_at !== null && $session->planned_end_at->isPast(),
        ];
    }
}
