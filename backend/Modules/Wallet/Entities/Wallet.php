<?php

namespace Modules\Wallet\Entities;

use Illuminate\Database\Eloquent\Model;
use App\User;

class Wallet extends Model
{
    protected $table = 'wallets';

    protected $fillable = [
        'user_id',
        'buyer_id',
        'balance',
        'pending_balance',
        'total_earned',
        'total_spent',
        'status',
        'currency',
    ];

    protected $casts = [
        'balance' => 'float',
        'pending_balance' => 'float',
        'total_earned' => 'float',
        'total_spent' => 'float',
        'status' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function histories()
    {
        return $this->hasMany(WalletHistory::class, 'wallet_id')->orderBy('id', 'desc');
    }

    /**
     * Get or create a wallet for a given user.
     */
    public static function getOrCreateForUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'buyer_id' => $userId,
                'balance' => 0.00,
                'pending_balance' => 0.00,
                'total_earned' => 0.00,
                'total_spent' => 0.00,
                'status' => 1,
                'currency' => 'SAR',
            ]
        );
    }
}
