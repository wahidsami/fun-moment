<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subcategory extends Model
{
    use HasFactory;
    protected $table = 'subcategories';
    protected $fillable = ['name', 'name_ar', 'slug', 'category_id', 'status', 'image', 'description'];

    public function category(){
        return $this->belongsTo('App\Category');
    }

    public function childcategories(){
        return $this->hasMany(ChildCategory::class, 'sub_category_id', 'id');
    }

    public function services(){
        return $this->hasMany('App\Service');
    }
    public function metaData(){
        return $this->morphOne(MetaData::class,'meta_taggable');
    }
}
