<?php

namespace Modules\Subscription\Entities;

use App\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerSubscription extends Model
{
    use HasFactory;

    protected $table = 'seller_subscriptions';

    protected $fillable = [
        'seller_id',
        'subscription_id',
        'type',
        'price',
        'connect',
        'service',
        'job',
        'initial_connect',
        'initial_service',
        'initial_job',
        'expire_date',
        'payment_gateway',
        'payment_status',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'connect' => 'integer',
        'service' => 'integer',
        'job' => 'integer',
        'initial_connect' => 'integer',
        'initial_service' => 'integer',
        'initial_job' => 'integer',
        'status' => 'integer',
        'expire_date' => 'datetime',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id');
    }

    public function isExpired(): bool
    {
        if ($this->type === 'lifetime') {
            return false;
        }
        return $this->expire_date !== null && $this->expire_date->isPast();
    }
}
