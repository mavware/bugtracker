<?php

namespace App\Actions\Portal;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * How a property's owner gets into the client portal. The professional makes an
 * invitation link for the customer record and hands it over however they like;
 * whoever opens it while signed in becomes the property's client. The link is a
 * bearer secret, so it expires and can be revoked, and accepting it uses it up.
 */
class CustomerPortalAccess
{
    /** How long an invitation link stays open. */
    public const int INVITATION_DAYS = 7;

    /**
     * Open (or reopen) an invitation. A fresh token replaces any earlier one, so
     * a link that was sent to the wrong person stops working the moment a new
     * one is made.
     */
    public function invite(Customer $customer): void
    {
        $customer->forceFill([
            'portal_invite_token' => Str::random(48),
            'portal_invited_at' => now(),
        ])->save();
    }

    public function revoke(Customer $customer): void
    {
        $customer->forceFill([
            'portal_invite_token' => null,
            'portal_invited_at' => null,
        ])->save();
    }

    /**
     * Take the client's access away. Their account is untouched; the property
     * simply no longer appears in their portal.
     */
    public function unlink(Customer $customer): void
    {
        $customer->forceFill(['client_user_id' => null])->save();
    }

    /**
     * The customer an open, unexpired invitation belongs to.
     */
    public function findByToken(string $token): ?Customer
    {
        if ($token === '') {
            return null;
        }

        return Customer::query()
            ->where('portal_invite_token', $token)
            ->where('portal_invited_at', '>=', now()->subDays(self::INVITATION_DAYS))
            ->first();
    }

    /**
     * Link the signed-in account to the property and spend the invitation.
     *
     * @throws ValidationException when the professional opens their own link
     */
    public function accept(Customer $customer, User $user): void
    {
        if ($customer->user_id === $user->id) {
            throw ValidationException::withMessages([
                'invitation' => __('This is your own customer. Send the link to them instead.'),
            ]);
        }

        $customer->forceFill([
            'client_user_id' => $user->id,
            'portal_invite_token' => null,
            'portal_invited_at' => null,
        ])->save();
    }

    public function invitationUrl(Customer $customer): ?string
    {
        if ($customer->portal_invite_token === null || $this->invitationExpired($customer)) {
            return null;
        }

        return route('portal.invitations.show', ['token' => $customer->portal_invite_token]);
    }

    public function invitationExpired(Customer $customer): bool
    {
        return $customer->portal_invited_at === null
            || $customer->portal_invited_at->lt(now()->subDays(self::INVITATION_DAYS));
    }
}
