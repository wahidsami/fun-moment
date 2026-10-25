<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MusicRefundAttempt extends Model
{
    use HasFactory;

    public const STATUS_INITIATED = 'initiated';
    public const STATUS_PENDING_VERIFICATION = 'pending_verification';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DECLINED = 'declined';

    protected $table = 'music_refund_attempts';

    protected $fillable = [
        'attempt_uuid',
        'order_id',
        'buyer_id',
        'admin_id',
        'destination',
        'payment_gateway',
        'amount',
        'currency',
        'subtotal_amount',
        'vat_amount',
        'commission_amount',
        'seller_earning_amount',
        'is_full_refund',
        'status',
        'idempotency_key',
        'original_transaction_ref',
        'provider_refund_ref',
        'cart_id',
        'reason',
        'error_code',
        'error_message',
        'reconciliation_notes',
        'reconciliation_attempts',
        'last_reconciled_at',
        'completed_at',
        'failed_at',
        'raw_request',
        'raw_response',
    ];

    protected $casts = [
        'amount' => 'float',
        'subtotal_amount' => 'float',
        'vat_amount' => 'float',
        'commission_amount' => 'float',
        'seller_earning_amount' => 'float',
        'is_full_refund' => 'boolean',
        'reconciliation_attempts' => 'integer',
        'last_reconciled_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'raw_request' => 'array',
        'raw_response' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(MusicOrder::class, 'order_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function isPendingVerification(): bool
    {
        return $this->status === self::STATUS_PENDING_VERIFICATION;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isDeclined(): bool
    {
        return $this->status === self::STATUS_DECLINED;
    }

    public function isUnresolved(): bool
    {
        return in_array($this->status, [self::STATUS_INITIATED, self::STATUS_PENDING_VERIFICATION], true);
    }
}
