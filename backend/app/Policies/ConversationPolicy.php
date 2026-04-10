<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * User must be a participant (user_a or user_b) to view/update a conversation.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user->id);
    }

    /**
     * Alias for view — used for sending messages, marking read, etc.
     */
    public function participate(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user->id);
    }

    /**
     * User must be a participant to delete (soft delete) a conversation.
     */
    public function delete(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user->id);
    }
}
