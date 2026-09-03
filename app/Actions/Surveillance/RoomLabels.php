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
        $query = SurveillanceSession::query()
            ->whereNotNull('room')
            ->selectRaw('user_id, customer_id, room, COUNT(*) as sessions_count')
            ->groupBy('user_id', 'customer_id', 'room')
            ->orderBy('room');

        if ($user !== null) {
            $query->where('user_id', $user->id);
        }

        $groups = $query->get();

        $owners = $user !== null
            ? collect([$user->id => $user->email])
            : User::whereIn('id', $groups->pluck('user_id')->unique())
                ->get(['id', 'email'])
                ->mapWithKeys(fn (User $owner) => [$owner->id => $owner->email]);

        $customers = Customer::whereIn('id', $groups->pluck('customer_id')->filter()->unique())
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Customer $customer) => [$customer->id => $customer->name]);

        $labels = [];

        foreach ($groups as $group) {
            $labels[] = new RoomLabel(
                key: self::keyFor($group->user_id, $group->customer_id, $group->room ?? ''),
                userId: $group->user_id,
                customerId: $group->customer_id,
                owner: $owners[$group->user_id] ?? '—',
                customer: $group->customer_id !== null ? ($customers[$group->customer_id] ?? '—') : null,
                room: $group->room ?? '',
                sessionsCount: $group->sessions_count ?? 0,
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
