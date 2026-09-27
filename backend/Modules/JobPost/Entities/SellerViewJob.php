<?php

namespace Modules\JobPost\Entities;

use App\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerViewJob extends Model
{
    use HasFactory;

    protected $table = 'seller_view_jobs';

    protected $fillable = [
        'job_post_id',
        'seller_id',
    ];

    public function job()
    {
        return $this->belongsTo(BuyerJob::class, 'job_post_id', 'id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }
}
