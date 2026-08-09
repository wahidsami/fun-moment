<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceCity extends Model
{
    use HasFactory;
    protected $table = 'service_cities';
    protected $fillable = ['service_city','status','country_id'];

    public function countryy()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function service_area()
    {
        return $this->hasOne(ServiceArea::class, 'country_id', 'id');
    }

    public function service() {
        return $this->hasMany(Service::class,'service_city_id', 'id');
    }

    /*
     *
     *
     *  return [
            'service_city' => $this->faker->name,
            'status' => 1,
            'country_id' => Country::inRandomOrder()->first()->id,
        ];
     * */


}
