<?php

namespace App\Actions\Surveillance;

use App\Enums\SurveillanceSessionStatus;
use App\Models\BugTrack;
use App\Models\Intervention;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ComputeNightlyTrend
{
    /**
     * Count confirmed sightings per recorded night, with the user's interventions
     * positioned along the same timeline so their effect on the counts is visible.
     *
     * Nights are grouped by the calendar date the session started, matching the
     * "Night of ..." naming used when a session is created.
     *
     * @return array{
     *     nights: array<int, array{date: string, label: string, count: int, session_count: int}>,
     *     interventions: array<int, array{id: int, performed_on: string, label: string, description: string, marker: int, position: int}>,
     *     total_sightings: int,
     *     busiest: array{date: string, label: string, count: int, session_count: int}|null,
     *     latest: array{date: string, label: string, count: int, session_count: int}|null,
     *     previous: array{date: string, label: string, count: int, session_count: int}|null,
     * }
     */
    public function handle(User $user, ?string $room = null): array
    {
        $sessions = $user->surveillanceSessions()
            ->whereIn('status', [SurveillanceSessionStatus::Completed, SurveillanceSessionStatus::Aborted])
            ->whereNotNull('started_at')
            ->when($room !== null, fn (Builder $query) => $query->where('room', $room))
            ->withCount(['tracks as confirmed_tracks_count' => $this->onlyConfirmed(...)])
            ->orderBy('started_at')
            ->get();

        $nights = [];

        foreach ($sessions as $session) {
            $date = $session->started_at->toDateString();

            $nights[$date] ??= [
                'date' => $date,
                'label' => $session->started_at->format('M j'),
                'count' => 0,
                'session_count' => 0,
            ];

            $nights[$date]['count'] += (int) $session->getAttribute('confirmed_tracks_count');
            $nights[$date]['session_count']++;
        }

        ksort($nights);
        $nights = array_values($nights);

        return [
            'nights' => $nights,
            'interventions' => $this->positionInterventions($user, $room, $nights),
            'total_sightings' => array_sum(array_column($nights, 'count')),
            'busiest' => $this->busiestNight($nights),
            'latest' => $nights !== [] ? $nights[count($nights) - 1] : null,
            'previous' => count($nights) > 1 ? $nights[count($nights) - 2] : null,
        ];
    }

    /**
     * Dismissed tracks are false positives and never count towards a night.
     *
     * @param  Builder<BugTrack>  $query
     */
    private function onlyConfirmed(Builder $query): void
    {
        $query->confirmed();
    }

    /**
     * The rooms the user has recorded, for the room filter.
     *
     * @return array<int, string>
     */
    public function rooms(User $user): array
    {
        return $user->surveillanceSessions()
            ->whereNotNull('room')
            ->distinct()
            ->orderBy('room')
            ->pluck('room')
            ->all();
    }

    /**
     * Place each intervention on the night axis: its position is the number of
     * recorded nights that came before it, so the marker sits in the gap just
     * ahead of the first night it could have affected.
     *
     * @param  array<int, array{date: string, label: string, count: int, session_count: int}>  $nights
     * @return array<int, array{id: int, performed_on: string, label: string, description: string, marker: int, position: int}>
     */
    private function positionInterventions(User $user, ?string $room, array $nights): array
    {
        $interventions = $user->interventions()
            ->when($room !== null, fn (Builder $query) => $query->where(
                fn (Builder $scoped) => $scoped->where('room', $room)->orWhereNull('room')
            ))
            ->orderBy('performed_on')
            ->orderBy('id')
            ->get();

        $dates = array_column($nights, 'date');

        return $interventions->values()->map(function (Intervention $intervention, int $index) use ($dates) {
            $performedOn = $intervention->performed_on->toDateString();

            return [
                'id' => $intervention->id,
                'performed_on' => $performedOn,
                'label' => $intervention->performed_on->format('M j'),
                'description' => $intervention->description,
                'marker' => $index + 1,
                'position' => count(array_filter($dates, fn (string $date) => $date < $performedOn)),
            ];
        })->all();
    }

    /**
     * @param  array<int, array{date: string, label: string, count: int, session_count: int}>  $nights
     * @return array{date: string, label: string, count: int, session_count: int}|null
     */
    private function busiestNight(array $nights): ?array
    {
        $busiest = null;

        foreach ($nights as $night) {
            if ($busiest === null || $night['count'] > $busiest['count']) {
                $busiest = $night;
            }
        }

        return $busiest;
    }
}
