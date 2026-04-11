<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'conversation_id',
        'sender_id',
        'type',
        'content',
        'image_url',
        'meta',
        'is_read',
        'read_at',
        'is_recalled',
        'recalled_at',
        'is_deleted_by_sender',
        'is_deleted_by_receiver',
        'sent_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_read' => 'boolean',
        'is_recalled' => 'boolean',
        'is_deleted_by_sender' => 'boolean',
        'is_deleted_by_receiver' => 'boolean',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'recalled_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
