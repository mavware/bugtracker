<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A property the user watches on someone else's behalf. Grouping by customer keeps
 * one household's nights from being merged with another's — the frames, entry
 * points and nightly counts only mean anything within a single property.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $client_user_id The account the property's owner uses in the client portal.
 * @property string $name
 * @property string|null $address
 * @property string|null $notes
 * @property string|null $portal_invite_token An open invitation to the portal; null once accepted or revoked.
 * @property Carbon|null $portal_invited_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
// client_user_id and the invitation stay out of mass assignment: the link to
// a client's account is made only by accepting an invitation.
#[Fillable(['name', 'address', 'notes'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'portal_invited_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The property owner's own account, once they have accepted an invitation
     * to the client portal.
     *
     * @return BelongsTo<User, $this>
     */
    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    /**
     * The rooms of this property that have been watched.
     *
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
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
}
