<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Accountdeactive extends Model
{
    use HasFactory;
    protected $fillable = ['user_id','reason','description','status','account_status'];

    public static function boot()
    {
        parent::boot();

        static::saved(function (Accountdeactive $record) {
            if (!empty($record->user_id)) {
                MusicTrack::suspendForDeactivatedSeller(
                    (int) $record->user_id,
                    $record->reason ?: 'Seller account deactivated'
                );
            }
        });
    }
    
    public function user(){
        return $this->belongsTo(User::class,'user_id');
    }
}
