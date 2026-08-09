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
    protected $fillable = ['name','slug','icon','image','status','mobile_icon', 'description'];

    public function subcategories(){
        return $this->hasMany(Subcategory::class,'category_id','id');
    }

    public function services(){
        return $this->hasMany(Service::class,'category_id','id')->where('status',1)->where('is_service_on',1);
    }

    public function metaData(){
        return $this->morphOne(MetaData::class,'meta_taggable');
    }


}
