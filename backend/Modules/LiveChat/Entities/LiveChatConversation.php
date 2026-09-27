<?php

namespace Modules\LiveChat\Entities;

use App\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveChatConversation extends Model
{
    use HasFactory;

    protected $table = 'live_chat_conversations';

    protected $fillable = [
        'buyer_id',
        'seller_id',
        'last_message',
        'last_message_at',
        'buyer_unread_count',
        'seller_unread_count',
        'status',
    ];

    protected $casts = [
        'buyer_id' => 'integer',
        'seller_id' => 'integer',
        'buyer_unread_count' => 'integer',
        'seller_unread_count' => 'integer',
        'status' => 'integer',
        'last_message_at' => 'datetime',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(LiveChatMessage::class, 'conversation_id')->orderBy('created_at', 'asc');
    }

    public static function getOrCreate(int $buyerId, int $sellerId): self
    {
        return self::firstOrCreate(
            ['buyer_id' => $buyerId, 'seller_id' => $sellerId],
            [
                'buyer_unread_count' => 0,
                'seller_unread_count' => 0,
                'status' => 1,
            ]
        );
    }
}
