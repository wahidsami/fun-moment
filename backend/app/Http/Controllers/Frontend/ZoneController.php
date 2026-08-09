<?php

namespace App\Http\Controllers\Frontend;

use App\Country;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Stevebauman\Location\Facades\Location;

class ZoneController extends Controller
{

    public function sellerZone(Request $request){
        $seller_location = User::select('id','latitude', 'longitude', 'country_id', 'seller_address')->where('id', Auth::guard('web')->user()->id)->first();
        if(!empty($seller_location->latitude && $seller_location->longitude)){
            $location =$seller_location;
        }else{
            $location = (object) [
                'latitude' => 0,
                'longitude' => 0,
                'seller_address' => '',
            ];
        }

        return view('frontend.user.seller.services.service-zone.zone-create', compact('location'));
    }

    public function sellerzoneUpdate(Request $request)
    {

        $request->validate([
            'seller_address'=> 'required',
            'latitude'=> 'required',
            'longitude'=> 'required',
        ]);

        $seller_id = Auth::guard('web')->user()->id;
        $seller_zone = User::select('id','latitude', 'longitude', 'seller_address')->where('id', $seller_id)->first();

        if (!empty($seller_zone)){
            User::where('id', $seller_id)->update([
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'seller_address' => $request->seller_address
            ]);
        }

        toastr_success(__('Zone Updated Successfully'));
        return back();
    }

}
