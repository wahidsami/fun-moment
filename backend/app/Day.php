<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Day extends Model
{
    use HasFactory;

    protected $table = 'days';
    protected $fillable = ['day', 'status', 'seller_id', 'service_id', 'total_day'];

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }
}
