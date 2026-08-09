<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\JobPost\Entities\BuyerJob;

class AdminNotification extends Model
{
    use HasFactory;

    protected $table = 'admin_notifications';
    protected $fillable = ['order_id' ,'ticket_id', 'job_post_id', 'status'];

    public function buyerJob(){
        return $this->belongsTo( BuyerJob::class, 'job_post_id', 'id');
    }
}
