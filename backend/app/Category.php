<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    use HasFactory;
    
    protected $table = 'categories';
    protected $fillable = ['name', 'name_ar', 'slug', 'icon', 'image', 'status', 'mobile_icon', 'description', 'sort_order'];

    public function subcategories(){
        return $this->hasMany(Subcategory::class,'category_id','id');
    }

    public function services(){
        return $this->hasMany(Service::class,'category_id','id')->where('status',1)->where('is_service_on',1);
    }

    public function metaData(){
        return $this->morphOne(MetaData::class,'meta_taggable');
    }

    public function providers(){
        return $this->belongsToMany(User::class, 'provider_categories', 'category_id', 'provider_id')->withTimestamps();
    }
}
