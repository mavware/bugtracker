<?php

namespace App\Policies;

use App\Models\SurveillanceSession;
use App\Models\User;

class SurveillanceSessionPolicy
{
    /**
     * Determine whether the user can view the session.
     */
    public function view(User $user, SurveillanceSession $session): bool
    {
        return $session->user_id === $user->id;
    }

    /**
     * Determine whether the user can update the session.
     */
    public function update(User $user, SurveillanceSession $session): bool
    {
        return $session->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the session.
     */
    public function delete(User $user, SurveillanceSession $session): bool
    {
        return $session->user_id === $user->id;
    }
}
