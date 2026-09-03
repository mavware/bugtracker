<?php

namespace App\Actions\Surveillance;

use App\Models\Customer;
use App\Models\SurveillanceSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A room is a label on a session rather than a table of its own, so editing one
 * means rewriting every session that carries it. Labels are grouped by owner and
 * property: "Kitchen" in two homes is two different rooms.
 */
class RoomLabels
{
    /**
     * @param  User|null  $user  Limit to one account; null spans every account, for admins.
     * @return Collection<int, RoomLabel>
     */
    public function groups(?User $user = null): Collection
    {
        $groups = SurveillanceSession::query()
            ->whereNotNull('room')
            ->when($user !== null, fn (Builder $query) => $query->where('user_id', $user->id))
            ->selectRaw('user_id, customer_id, room, COUNT(*) as sessions_count')
            ->groupBy('user_id', 'customer_id', 'room')
            ->orderBy('room')
            ->get();

        $owners = $user !== null
            ? collect([$user->id => $user->email])
            : User::whereIn('id', $groups->pluck('user_id')->unique())->pluck('email', 'id');

        $customers = Customer::whereIn('id', $groups->pluck('customer_id')->filter()->unique())->pluck('name', 'id');

        $labels = [];

        foreach ($groups as $group) {
            $customerId = $group->customer_id !== null ? (int) $group->customer_id : null;

            $labels[] = new RoomLabel(
                key: self::keyFor((int) $group->user_id, $customerId, (string) $group->room),
                userId: (int) $group->user_id,
                customerId: $customerId,
                owner: (string) ($owners[$group->user_id] ?? '—'),
                customer: $customerId !== null ? (string) ($customers[$customerId] ?? '—') : null,
                room: (string) $group->room,
                sessionsCount: (int) $group->getAttribute('sessions_count'),
            );
        }

        return collect($labels);
    }

    /**
     * Rename every session in the group. Renaming onto a label that already
     * exists merges the two, which is how a typo gets cleaned up.
     */
    public function rename(RoomLabel $label, string $room): void
    {
        $this->sessionsFor($label)->update(['room' => trim($room)]);
    }

    /**
     * Drop the label without touching the recordings themselves.
     */
    public function clear(RoomLabel $label): void
    {
        $this->sessionsFor($label)->update(['room' => null]);
    }

    private static function keyFor(int $userId, ?int $customerId, string $room): string
    {
        return sha1($userId.'|'.$customerId.'|'.$room);
    }

    /**
     * @return Builder<SurveillanceSession>
     */
    private function sessionsFor(RoomLabel $label): Builder
    {
        return SurveillanceSession::query()
            ->where('user_id', $label->userId)
            ->where('room', $label->room)
            ->when(
                $label->customerId === null,
                fn (Builder $query) => $query->whereNull('customer_id'),
                fn (Builder $query) => $query->where('customer_id', $label->customerId),
            );
    }
}
