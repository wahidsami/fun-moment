<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserUniqueKey extends Model
{
    use HasFactory;

    protected  $fillable = [
        'user_id',
        'unique_key'
    ];

    public function user(){
        return $this->belongsTo(User::class,'user_id','id');
    }

}
