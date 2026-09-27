<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $table = 'schedules';
    protected $fillable = ['day_id', 'seller_id', 'service_id', 'schedule', 'status', 'allow_multiple_schedule'];

    public function days()
    {
        return $this->belongsTo(Day::class, 'day_id', 'id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }
}
