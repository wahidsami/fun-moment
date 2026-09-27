<?php

namespace Modules\JobPost\Entities;

use App\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobRequest extends Model
{
    use HasFactory;

    protected $table = 'job_requests';

    protected $fillable = [
        'job_post_id',
        'buyer_id',
        'seller_id',
        'expected_salary',
        'cover_letter',
        'is_hired',
        'is_rejected',
        'status',
    ];

    protected $casts = [
        'is_hired' => 'integer',
        'is_rejected' => 'integer',
        'status' => 'integer',
        'expected_salary' => 'decimal:2',
    ];

    public function job()
    {
        return $this->belongsTo(BuyerJob::class, 'job_post_id', 'id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id', 'id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }

    public function conversations()
    {
        return $this->hasMany(JobRequestConversation::class, 'job_request_id', 'id');
    }
}
