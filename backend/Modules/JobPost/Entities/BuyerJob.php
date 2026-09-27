<?php

namespace Modules\JobPost\Entities;

use App\Category;
use App\ChildCategory;
use App\Order;
use App\ServiceCity;
use App\Subcategory;
use App\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuyerJob extends Model
{
    use HasFactory;

    protected $table = 'buyer_jobs';

    protected $fillable = [
        'category_id',
        'subcategory_id',
        'child_category_id',
        'buyer_id',
        'country_id',
        'city_id',
        'title',
        'slug',
        'description',
        'image',
        'is_job_online',
        'price',
        'dead_line',
        'view',
        'is_job_on',
        'status',
    ];

    protected $casts = [
        'is_job_online' => 'integer',
        'is_job_on' => 'integer',
        'status' => 'integer',
        'price' => 'decimal:2',
        'view' => 'integer',
        'dead_line' => 'datetime',
    ];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id', 'id');
    }

    public function job_request()
    {
        return $this->hasMany(JobRequest::class, 'job_post_id', 'id');
    }

    public function job_requests()
    {
        return $this->hasMany(JobRequest::class, 'job_post_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class, 'subcategory_id', 'id');
    }

    public function child_category()
    {
        return $this->belongsTo(ChildCategory::class, 'child_category_id', 'id');
    }

    public function city()
    {
        return $this->belongsTo(ServiceCity::class, 'city_id', 'id');
    }

    public function sellerViewJobs()
    {
        return $this->hasMany(SellerViewJob::class, 'job_post_id', 'id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'job_post_id', 'id');
    }
}
