<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderBookingDateTimeChange extends Model
{
    use HasFactory;

    protected $table = 'order_booking_date_time_changes';
    protected $fillable = ['order_id','date','schedule','rejection_reason','status'];
}