<?php

namespace Modules\Subscription\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $table = 'subscriptions';

    protected $fillable = [
        'title',
        'type',
        'price',
        'connect',
        'service',
        'job',
        'description',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'connect' => 'integer',
        'service' => 'integer',
        'job' => 'integer',
        'status' => 'integer',
    ];

    public function sellerSubscriptions()
    {
        return $this->hasMany(SellerSubscription::class, 'subscription_id', 'id');
    }
}
