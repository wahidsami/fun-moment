<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderCategory extends Model
{
    use HasFactory;

    protected $table = 'provider_categories';

    protected $fillable = [
        'provider_id',
        'category_id',
    ];

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }
}
