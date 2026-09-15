<?php

namespace App\Models;

use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The place a camera was pointed. Nights shot from one room are merged into one
 * entry point map, so a room is scoped to a property: it belongs to the account
 * that recorded it and, when the camera was at a customer's home, to that
 * customer. A room with no customer is one of the account's own. "Kitchen" in
 * two homes is two rooms.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $customer_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $surveillance_sessions_count Only loaded by queries that withCount() the sessions.
 * @property-read int|null $interventions_count Only loaded by queries that withCount() the interventions.
 */
#[Fillable(['customer_id', 'name'])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    /**
     * The longest a room name can be. Shared by every input that accepts one.
     */
    public const int NAME_MAX_LENGTH = 80;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<SurveillanceSession, $this>
     */
    public function surveillanceSessions(): HasMany
    {
        return $this->hasMany(SurveillanceSession::class);
    }

    /**
     * @return HasMany<Intervention, $this>
     */
    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    /**
     * The room's name with the property it is in, for a list that spans more
     * than one property, where "Kitchen" on its own would be ambiguous.
     */
    public function label(): string
    {
        $customer = $this->customer;

        return $customer !== null ? "{$this->name} · {$customer->name}" : $this->name;
    }

    /**
     * Narrow to one property: the given customer's rooms, or with null the
     * account's own rooms (the ones with no customer at all).
     *
     * @param  Builder<Room>  $query
     */
    public function scopeAtProperty(Builder $query, ?int $customerId): void
    {
        if ($customerId === null) {
            $query->whereNull('customer_id');
        } else {
            $query->where('customer_id', $customerId);
        }
    }

    /**
     * The room called this within one account and property, created if it is
     * new. Rooms are typed in by name wherever a night is filed, so this is the
     * one place a name becomes a row. A blank name is no room at all.
     */
    public static function resolve(int $userId, ?int $customerId, ?string $name): ?self
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $room = self::query()
            ->where('user_id', $userId)
            ->atProperty($customerId)
            ->where('name', $name)
            ->first();

        if ($room !== null) {
            return $room;
        }

        $room = new self(['customer_id' => $customerId, 'name' => $name]);
        $room->user_id = $userId;
        $room->save();

        return $room;
    }

    /**
     * A room exists because a night or an intervention is filed in it. Once the
     * last of those has moved elsewhere, the room goes too, so a corrected typo
     * does not leave an empty room on the list.
     */
    public function deleteIfUnused(): void
    {
        if (! $this->surveillanceSessions()->exists() && ! $this->interventions()->exists()) {
            $this->delete();
        }
    }
}
