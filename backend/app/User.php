<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Subscription\Entities\SellerSubscription;
use Modules\JobPost\Entities\BuyerJob;

class User extends Authenticatable
{
    use HasApiTokens,Notifiable;

    public const USER_TYPE_SELLER = 0;
    public const USER_TYPE_BUYER = 1;

    public const SELLER_TYPE_NOT_APPLICABLE = 0;
    public const SELLER_TYPE_INDIVIDUAL = 1;
    public const SELLER_TYPE_COMPANY = 2;

    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'phone',
        'otp_code',
        'otp_expire_at',
        'image',
        'profile_background',
        'service_city',
        'service_area',
        'user_type',
        'user_status',
        'terms_condition',
        'address',
        'state',
        'about',
        'post_code',
        'country_id',
        'email_verified',
        'email_verify_token',
        'fb_url',
        'tw_url',
        'go_url',
        'li_url',
        'yo_url',
        'in_url',
        'dr_url',
        'twi_url',
        'pi_url',
        'dr-url',
        'country_code',
        'google_id',
        'facebook_id',
        'apple_id',
        'zone_id',
        'latitude',
        'longitude',
        'seller_address',
        'change_password_date',
        'seller_type',
        'business_registration',
        'otp_verified',
    ];

    protected $hidden = [
        'password', 'remember_token', 'otp_code', 'otp_expire_at', 'email_verify_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'user_type' => 'integer',
        'email_verified' => 'integer',
    ];
    

    public function country(){
        return $this->belongsTo(Country::class,'country_id','id');
    }

    public function city(){
        return $this->belongsTo(ServiceCity::class,'service_city','id');
    }

    public function deviceTokens()
    {
        return $this->hasMany(UserDeviceToken::class, 'user_id', 'id');
    }

    public function area(){
        return $this->belongsTo(ServiceArea::class,'service_area','id');
    }
    public function jobs(){
        return $this->hasMany(BuyerJob::class,'buyer_id','id');
    }    
    public function order(){
        return $this->hasMany(Order::class,'seller_id','id');
    }
    public function review(){
        return $this->hasMany(Review::class,'seller_id','id');
    }

    public function blog(){
        return Blog::where(['user_id' => $this->attributes['id'],'created_by' => 'user'])->get();
    }
    public function media(){
        return MediaUpload::where(['user_id' => $this->attributes['id'],'type' => 'user'])->get();
    }

    public function sellerVerify(){
        return $this->hasOne(SellerVerify::class,'seller_id','id');
    }

    // delete seller account rel.
    public function account_status(){
        return $this->hasOne(Accountdeactive::class,'user_id','id');
    }

    public function subscribedSeller()
    {
        if(!moduleExists('Subscription')){
            return null;
        }
        return $this->hasOne(SellerSubscription::class,'seller_id','id')->select('id','seller_id','expire_date','subscription_id', 'connect', 'service', 'job');
    }

    public function user_unique_key(): HasOne
    {
        return $this->hasOne(UserUniqueKey::class, 'user_id', 'id');
    }

    public function buyer_jobs()
    {
        return $this->hasMany(\Modules\JobPost\Entities\BuyerJob::class, 'buyer_id', 'id');
    }

    public function job_requests()
    {
        return $this->hasMany(\Modules\JobPost\Entities\JobRequest::class, 'seller_id', 'id');
    }

    public function providerCategories()
    {
        return $this->hasMany(ProviderCategory::class, 'provider_id', 'id');
    }

    public function registeredCategories()
    {
        return $this->belongsToMany(Category::class, 'provider_categories', 'provider_id', 'category_id')->withTimestamps();
    }

    public function isProvider(): bool
    {
        return (int) $this->user_type === self::USER_TYPE_SELLER;
    }

    public function isCustomer(): bool
    {
        return (int) $this->user_type === self::USER_TYPE_BUYER;
    }

    public function isIndividualProvider(): bool
    {
        return $this->isProvider() && (int) $this->seller_type === self::SELLER_TYPE_INDIVIDUAL;
    }

    public function isCompanyProvider(): bool
    {
        return $this->isProvider() && (int) $this->seller_type === self::SELLER_TYPE_COMPANY;
    }
}
