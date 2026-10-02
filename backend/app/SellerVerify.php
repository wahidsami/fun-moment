<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerVerify extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    protected $fillable = [
        'seller_id',
        'national_id',
        'address',
        'status',
        'national_id_number',
        'national_id_document',
        'license_number',
        'license_document',
        'is_band_or_group',
        'band_name',
        'band_members_count',
        'company_name',
        'cr_number',
        'cr_document',
        'contact_person_name',
        'contact_person_email',
        'contact_person_phone',
        'rejection_reason',
        'verified_by',
        'verified_at',
    ];
    
    protected $casts = [
        'seller_id' => 'integer',
        'status' => 'integer',
        'is_band_or_group' => 'boolean',
        'band_members_count' => 'integer',
        'verified_by' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }

    public function seller()
    {
        return $this->user();
    }

    public function verifiedBy()
    {
        return $this->belongsTo(Admin::class, 'verified_by', 'id');
    }

    public function isPending(): bool
    {
        return (int) $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return (int) $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return (int) $this->status === self::STATUS_REJECTED;
    }
}
