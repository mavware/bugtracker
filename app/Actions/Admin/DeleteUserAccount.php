<?php

namespace App\Actions\Admin;

use App\Models\SurveillanceSession;
use App\Models\User;

class DeleteUserAccount
{
    /**
     * Delete a user and everything they own.
     *
     * Sessions are deleted one at a time through Eloquent on purpose: the database
     * cascade would remove the rows without firing SurveillanceSession's deleting
     * hook, leaving every reference frame and crop stranded on disk. Customers and
     * interventions hold no files, so their cascade is fine.
     */
    public function handle(User $user): void
    {
        $user->surveillanceSessions()->each(function (SurveillanceSession $session) {
            $session->delete();
        });

        $user->delete();
    }
}
