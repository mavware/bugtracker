<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property Collection<int, UserRole> $roles
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
// roles is deliberately absent: keeping it out of mass assignment means no
// request payload can promote its own account. Grant and revoke explicitly.
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'roles' => '["homeowner"]',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'roles' => AsEnumCollection::of(UserRole::class),
            'password' => 'hashed',
        ];
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
     * Every room this account has recorded in, its own and its customers'.
     *
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Properties recorded on this account's behalf by a professional, reached
     * through the client portal.
     *
     * @return HasMany<Customer, $this>
     */
    public function clientProperties(): HasMany
    {
        return $this->hasMany(Customer::class, 'client_user_id');
    }

    /**
     * Whether a professional has linked at least one property to this account.
     * Portal access is a matter of record, not of role: a homeowner who is also
     * someone's customer sees both their own nights and the portal.
     */
    public function isPortalClient(): bool
    {
        return $this->clientProperties()->exists();
    }

    /**
     * Whether any of this account's roles grants a permission. Every
     * authorization decision that is about the roles, not about owning a
     * record, goes here.
     */
    public function hasPermission(Permission $permission): bool
    {
        return $this->roles->contains(fn (UserRole $role): bool => $role->grants($permission));
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->roles->contains($role);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin);
    }

    /**
     * Add a role the account does not already hold. Roles are kept in the
     * enum's own order so two accounts with the same roles read the same.
     */
    public function grantRole(UserRole $role): void
    {
        $this->setRoles($this->roles->push($role));
    }

    public function revokeRole(UserRole $role): void
    {
        $this->setRoles($this->roles->reject(fn (UserRole $held): bool => $held === $role));
    }

    /**
     * @param  iterable<int, UserRole>  $roles
     */
    public function setRoles(iterable $roles): void
    {
        $this->roles = collect(self::orderedRoles($roles));
    }

    /**
     * The given roles, each once, in the enum's own order.
     *
     * @param  iterable<int, UserRole>  $roles
     * @return list<UserRole>
     */
    private static function orderedRoles(iterable $roles): array
    {
        $held = collect($roles);
        $ordered = [];

        foreach (UserRole::cases() as $case) {
            if ($held->contains($case)) {
                $ordered[] = $case;
            }
        }

        return $ordered;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
