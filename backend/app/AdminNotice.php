<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminNotice extends Model
{
    use HasFactory;

    protected $table = 'admin_notices';
    protected $fillable = ['title','description','notice_type','notice_for', 'expire_date', 'status'];
}
