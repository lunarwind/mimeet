<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'uuid',
        'user_a_id',
        'user_b_id',
        'last_message_id',
        'last_message_at',
        'unread_count_a',
        'unread_count_b',
        'deleted_by_a',
        'deleted_by_b',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'unread_count_a' => 'integer',
        'unread_count_b' => 'integer',
        'deleted_by_a' => 'boolean',
        'deleted_by_b' => 'boolean',
    ];

    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function getOtherUser(int $userId): User
    {
        return $this->user_a_id === $userId ? $this->userB : $this->userA;
    }

    public function getUnreadCount(int $userId): int
    {
        return $this->user_a_id === $userId
            ? $this->unread_count_a
            : $this->unread_count_b;
    }

    public function isParticipant(int $userId): bool
    {
        return $this->user_a_id === $userId || $this->user_b_id === $userId;
    }
}
