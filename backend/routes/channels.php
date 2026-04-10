<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Private chat channel — only participants can subscribe
Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);
    if (!$conversation) {
        return false;
    }
    return $conversation->user_a_id === $user->id || $conversation->user_b_id === $user->id;
});

// Private user notification channel
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (string) $user->id === (string) $userId;
});

// Presence channel — only return id + nickname
Broadcast::channel('presence.chat.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);
    if (!$conversation) {
        return false;
    }
    if ($conversation->user_a_id !== $user->id && $conversation->user_b_id !== $user->id) {
        return false;
    }
    return ['id' => $user->id, 'nickname' => $user->nickname];
});
