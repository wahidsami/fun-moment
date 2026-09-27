<?php

namespace Modules\Subscription\Entities;

use App\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionHistory extends Model
{
    use HasFactory;

    protected $table = 'subscription_histories';

    protected $fillable = [
        'seller_id',
        'subscription_id',
        'type',
        'price',
        'connect',
        'service',
        'job',
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
}
