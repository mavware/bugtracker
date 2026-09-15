<?php

namespace App\Actions\Surveillance;

use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The one implementation behind every rooms page: the account's own, the
 * client portal's and the admin's. Each page lists only the rooms it may see
 * and looks a room up within that list, so authorisation is by construction —
 * another account's room id is simply not found there.
 */
class ManageRooms
{
    /**
     * Every room an account has recorded in, its own and its customers', with
     * how many nights are filed in each.
     *
     * @return Collection<int, Room>
     */
    public function ownedBy(User $user): Collection
    {
        return $this->listing()->where('user_id', $user->id)->get();
    }

    /**
     * Every room on the site, for the admin page.
     *
     * @return Collection<int, Room>
     */
    public function everywhere(): Collection
    {
        return $this->listing()->get();
    }

    /**
     * The rooms of the properties a client can see in the portal, whoever
     * recorded them. These are the same rows the professional's page lists,
     * so a rename on either side is the same rename.
     *
     * @return Collection<int, Room>
     */
    public function linkedTo(User $client): Collection
    {
        return $this->listing()
            ->whereIn('customer_id', $client->clientProperties()->select('id'))
            ->get();
    }

    /**
     * @return Builder<Room>
     */
    private function listing(): Builder
    {
        return Room::query()
            ->with(['user:id,email', 'customer:id,name'])
            ->withCount('surveillanceSessions')
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * Give the room a new name. Renaming onto a room that already exists at
     * the same property merges the two, which is how a typo gets cleaned up:
     * the nights and interventions move over and the misspelt room goes.
     */
    public function rename(Room $room, string $name): void
    {
        $name = trim($name);

        $existing = Room::query()
            ->where('user_id', $room->user_id)
            ->atProperty($room->customer_id)
            ->where('name', $name)
            ->whereKeyNot($room->id)
            ->first();

        if ($existing === null) {
            $room->update(['name' => $name]);

            return;
        }

        DB::transaction(function () use ($room, $existing) {
            $room->surveillanceSessions()->update(['room_id' => $existing->id]);
            $room->interventions()->update(['room_id' => $existing->id]);
            $room->delete();
        });
    }

    /**
     * Drop the room. Its nights and interventions are kept, just no longer
     * filed under any room.
     */
    public function remove(Room $room): void
    {
        $room->delete();
    }
}
