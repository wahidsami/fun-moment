<?php

namespace Modules\LiveChat\Entities;

use App\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LiveChatMessage extends Model
{
    use HasFactory;

    protected $table = 'live_chat_messages';

    protected $fillable = [
        'conversation_id',
        'from_user',
        'to_user',
        'buyer_id',
        'seller_id',
        'message',
        'image',
        'attachment_type',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'conversation_id' => 'integer',
        'from_user' => 'integer',
        'to_user' => 'integer',
        'buyer_id' => 'integer',
        'seller_id' => 'integer',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'date_time_str',
        'date_human_readable',
        'image_url',
        'sender_profile_image',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(LiveChatConversation::class, 'conversation_id');
    }

    public function fromUser(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'from_user');
    }

    public function toUser(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'to_user');
    }

    public function sellerList(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'seller_id');
    }

    public function buyerList(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'buyer_id');
    }

    public function getDateTimeStrAttribute(): string
    {
        return $this->created_at ? $this->created_at->format('h:i A') : '';
    }

    public function getDateHumanReadableAttribute(): string
    {
        return $this->created_at ? $this->created_at->diffForHumans() : '';
    }

    public function getImageUrlAttribute(): string
    {
        if (empty($this->image)) {
            return '';
        }
        if (str_starts_with($this->image, 'http')) {
            return $this->image;
        }
        return url('assets/uploads/ticket/' . ltrim($this->image, '/'));
    }

    public function getSenderProfileImageAttribute(): string
    {
        $user = $this->fromUser;
        if (!$user) {
            return '';
        }
        if (!empty($user->image)) {
            $img = get_attachment_image_by_id($user->image);
            if (!empty($img['img_url'])) {
                return $img['img_url'];
            }
        }
        return '';
    }
}
