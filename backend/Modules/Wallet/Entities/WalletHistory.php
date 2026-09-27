<?php

namespace Modules\Wallet\Entities;

use Illuminate\Database\Eloquent\Model;
use App\User;
use App\Admin;

class WalletHistory extends Model
{
    protected $table = 'wallet_histories';

    protected $fillable = [
        'wallet_id',
        'user_id',
        'buyer_id',
        'entry_type',
        'amount',
        'balance_before',
        'balance_after',
        'payment_gateway',
        'payment_status',
        'status',
        'reference_type',
        'reference_id',
        'transaction_id',
        'description_en',
        'description_ar',
        'manual_payment_image',
        'admin_id',
        'admin_note',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'float',
        'balance_before' => 'float',
        'balance_after' => 'float',
        'status' => 'integer',
        'metadata' => 'array',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
