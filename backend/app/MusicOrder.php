<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class MusicOrder extends Model
{
    use HasFactory;

    protected $table = 'music_orders';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';

    public const GATEWAY_PAYTABS = 'paytabs';
    public const GATEWAY_WALLET = 'wallet';
    public const GATEWAY_MANUAL = 'manual_payment';

    protected $fillable = [
        'invoice',
        'buyer_id',
        'seller_id',
        'seller_name',
        'seller_email',
        'admin_id',
        'buyer_name',
        'buyer_email',
        'track_id',
        'offer_id',
        'offer_type',
        'amount',
        'currency',
        'commission_rate',
        'commission_amount',
        'seller_earning',
        'payment_gateway',
        'payment_status',
        'transaction_id',
        'idempotency_key',
        'payment_details',
        'paid_at',
        'refunded_at',
        'refund_reason',
    ];

    protected $casts = [
        'amount' => 'float',
        'commission_rate' => 'float',
        'commission_amount' => 'float',
        'seller_earning' => 'float',
        'payment_details' => 'array',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function (MusicOrder $order) {
            if (empty($order->invoice)) {
                $order->invoice = self::generateInvoice();
            }

            if (empty($order->buyer_name) && !empty($order->buyer_id)) {
                $buyer = $order->buyer ?: User::find($order->buyer_id);
                if ($buyer) {
                    $order->buyer_name = $buyer->name ?? null;
                    $order->buyer_email = $buyer->email ?? null;
                }
            }

            if (empty($order->seller_name) && !empty($order->seller_id)) {
                $seller = $order->seller ?: User::find($order->seller_id);
                if ($seller) {
                    $order->seller_name = $seller->name ?? null;
                    $order->seller_email = $seller->email ?? null;
                }
            }
        });
    }

    public static function generateInvoice(): string
    {
        return 'FM-MUS-' . date('Ymd') . '-' . strtoupper(Str::random(6));
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id', 'id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(MusicTrack::class, 'track_id', 'id');
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(MusicOffer::class, 'offer_id', 'id');
    }

    public function entitlement(): HasOne
    {
        return $this->hasOne(MusicEntitlement::class, 'order_id', 'id');
    }

    public function isCompleted(): bool
    {
        return $this->payment_status === self::STATUS_COMPLETED;
    }

    public function isRefunded(): bool
    {
        return $this->payment_status === self::STATUS_REFUNDED;
    }

    public function isPending(): bool
    {
        return $this->payment_status === self::STATUS_PENDING;
    }
}
