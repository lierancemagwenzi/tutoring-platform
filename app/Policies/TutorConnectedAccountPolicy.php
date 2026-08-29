<?php

namespace App\Policies;

use App\Models\TutorConnectedAccount;
use App\Models\User;

class TutorConnectedAccountPolicy
{
    /**
     * Determine whether the user can disconnect the connected account.
     */
    public function delete(User $user, TutorConnectedAccount $account): bool
    {
        return $user->id === $account->tutorProfile->user_id;
    }
}
