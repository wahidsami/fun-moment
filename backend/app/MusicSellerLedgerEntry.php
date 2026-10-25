<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MusicSellerLedgerEntry extends Model
{
    use HasFactory;

    protected $table = 'music_seller_ledger_entries';

    public const TYPE_EARNING = 'earning';
    public const TYPE_REFUND_REVERSAL = 'refund_reversal';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_PAYOUT_HOLD = 'payout_hold';

    public const STATUS_PAYABLE = 'payable';
    public const STATUS_PENDING = 'pending';
    public const STATUS_HELD = 'held';
    public const STATUS_REVERSED = 'reversed';
    public const STATUS_SETTLED = 'settled';

    protected $fillable = [
        'seller_id',
        'order_id',
        'track_id',
        'entry_type',
        'gross_amount',
        'commission_deducted',
        'vat_deducted',
        'net_earning',
        'currency',
        'status',
        'metadata',
    ];

    protected $casts = [
        'gross_amount' => 'float',
        'commission_deducted' => 'float',
        'vat_deducted' => 'float',
        'net_earning' => 'float',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MusicOrder::class, 'order_id', 'id');
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(MusicTrack::class, 'track_id', 'id');
    }

    public function isEarning(): bool
    {
        return $this->entry_type === self::TYPE_EARNING;
    }

    public function isReversal(): bool
    {
        return $this->entry_type === self::TYPE_REFUND_REVERSAL;
    }
}
