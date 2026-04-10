<?php

namespace App\Policies;

use App\Models\DateInvitation;
use App\Models\User;

class DateInvitationPolicy
{
    /**
     * User must be the inviter or invitee to view an invitation.
     */
    public function view(User $user, DateInvitation $invitation): bool
    {
        return $invitation->inviter_id === $user->id
            || $invitation->invitee_id === $user->id;
    }

    /**
     * Only the invitee can respond to an invitation.
     */
    public function respond(User $user, DateInvitation $invitation): bool
    {
        return $invitation->invitee_id === $user->id;
    }

    /**
     * Either inviter or invitee can participate in verification.
     */
    public function verify(User $user, DateInvitation $invitation): bool
    {
        return $invitation->inviter_id === $user->id
            || $invitation->invitee_id === $user->id;
    }
}
