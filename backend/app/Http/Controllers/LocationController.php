<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\FlashMsg;
use App\ServiceCity;
use App\ServiceArea;
use App\Country;
use Illuminate\Support\Facades\Http;

class LocationController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:city-list|city-create|city-status|city-edit|city-delete',['only' => ['service_city']]);
        $this->middleware('permission:city-create',['only' => ['add_city']]);
        $this->middleware('permission:city-edit',['only' => ['edit_city']]);
        $this->middleware('permission:city-status',['only' => ['change_status_city']]);
        $this->middleware('permission:city-delete',['only' => ['delete_city','bulk_action_city']]);

        $this->middleware('permission:area-list|area-create|area-status|area-edit|area-delete',['only' => ['area']]);
        $this->middleware('permission:area-create',['only' => ['add_area']]);
        $this->middleware('permission:area-edit',['only' => ['edit_area']]);
        $this->middleware('permission:area-status',['only' => ['change_status_area']]);
        $this->middleware('permission:area-delete',['only' => ['delete_area','bulk_action_area']]);

        $this->middleware('permission:country-list|country-create|country-status|country-edit|country-delete',['only' => ['country']]);
        $this->middleware('permission:country-create',['only' => ['add_country']]);
        $this->middleware('permission:country-edit',['only' => ['edit_country']]);
        $this->middleware('permission:country-status',['only' => ['change_status_country']]);
        $this->middleware('permission:country-delete',['only' => ['delete_country','bulk_action_country']]);
    }
   
    public function service_city(){
        $service_cities = ServiceCity::with('countryy')->latest()->paginate(10);
        $countries = Country::where('status',1)->get();
        return view('backend.pages.location.city',compact('service_cities','countries'));
    }

    public function service_city_search(Request $request){
        $service_cities = ServiceCity::where('service_city', 'like', "%{$request->city_search}%")->paginate(10);
        $countries = Country::all();
        return view('backend.pages.location.city',compact('service_cities','countries'));
    }

    // 5555
    public function service_area_search(Request $request){
        $service_areas = ServiceArea::with('city','country')->where('service_area', 'like', "%{$request->area_search}%")->paginate(10);
        $cities = ServiceCity::all();
        $countries = Country::all();
        return view('backend.pages.location.area',compact('service_areas','cities','countries'));
    }

    public function add_city(Request $request)
    {
        if($request->isMethod('post')){
            $request->validate([
                'service_city'=> 'required|max:191|unique:service_cities',
                'country_id'=> 'required',
            ]);
    
            ServiceCity::create([
               'service_city' => $request->service_city,
               'country_id' => $request->country_id,
           ]);
    
           return redirect()->back()->with(FlashMsg::item_new('New City Added'));
        }

        $countries = Country::select('id','country')->where('status',1)->get();
        return view('backend.pages.location.add_city',compact('countries'));
    }

    public function edit_city(Request $request)
    {
        $request->validate([
            'up_service_city'=> 'required|max:191|unique:service_cities,service_city,'.$request->up_id,
            'up_country_id'=> 'required',
        ]);

        ServiceCity::where('id',$request->up_id)->update([
            'service_city'=>$request->up_service_city,
            'country_id'=>$request->up_country_id,
        ]);
        return redirect()->back()->with(FlashMsg::item_new('City Update Success'));
    }

    public function change_status_city($id)
    {
        $city = ServiceCity::select('status')->where('id',$id)->first();
        if($city->status==1){
            $status = 0;
        }else{
         $status = 1;
        }
        ServiceCity::where('id',$id)->update(['status'=>$status]);
        return redirect()->back()->with(FlashMsg::item_new('Status Change Success'));
    }

     public function delete_city($id)
     {
        ServiceCity::find($id)->delete();
        return redirect()->back()->with(FlashMsg::item_new('City Deleted Success'));
    }

    public function bulk_action_city(Request $request)
    {
        ServiceCity::whereIn('id',$request->ids)->delete();
        return response()->json(['status' => 'ok']);
    }


    public function area()
    {
        $service_areas = ServiceArea::with('city','country')->paginate(10);
        $cities = ServiceCity::all();
        $countries = Country::all();
        return view('backend.pages.location.area',compact('service_areas','cities','countries'));
    }

    public function add_area(Request $request)
    {
        if($request->isMethod('post')){
            $request->validate([
                'service_area'=> 'required|max:191|unique:service_areas',
                'service_city_id'=> 'required',
                'country_id'=> 'required',
            ]);
    
            ServiceArea::create([
               'service_area' => $request->service_area,
               'service_city_id' => $request->service_city_id,
               'country_id' => $request->country_id,
           ]);
    
           return redirect()->back()->with(FlashMsg::item_new('New Service Area Added'));
        }
        $cities = ServiceCity::all();
        $countries = Country::all();
        return view('backend.pages.location.add_area',compact('cities','countries'));
    }

    public function edit_area(Request $request)
    {
        $request->validate([
            'up_service_area'=> 'required|max:191|unique:service_areas,service_area,'.$request->up_id,
            'up_service_city_id'=> 'required',
            'up_country_id'=> 'required',
        ]);

       ServiceArea::where('id',$request->up_id)->update([
            'service_area'=>$request->up_service_area,
            'service_city_id'=>$request->up_service_city_id,
            'country_id'=>$request->up_country_id,
        ]);

        
        return redirect()->back()->with(FlashMsg::item_new('Service Area Update Success'));
    }

    public function change_status_area($id)
    {
        $location = ServiceArea::select('status')->where('id',$id)->first();
        if($location->status==1){
            $status = 0;
        }else{
         $status = 1;
        }
        ServiceArea::where('id',$id)->update(['status'=>$status]);
        return redirect()->back()->with(FlashMsg::item_new('Status Change Success'));
    }

    public function delete_area($id)
    {
        ServiceArea::find($id)->delete();
        return redirect()->back()->with(FlashMsg::item_new('Service Area Deleted Success'));
    }

    public function bulk_action_area(Request $request)
    {
        ServiceArea::whereIn('id',$request->ids)->delete();
        return response()->json(['status' => 'ok']);
    }

    public function country(){
        $countries = Country::latest()->get();
        return view('backend.pages.location.country',compact('countries'));
    }

    public function add_country(Request $request)
    {
        if($request->isMethod('post')){
            $request->validate([
                'country'=> 'required|unique:countries|max:191',
            ]);

            $country = Country::create([
                'country' => $request->country,
            ]);


            if (!empty($country->country)) {
                $response = Http::get('https://restcountries.com/v3.1/name/'.$country->country);
                if ($response->ok()) {
                    $countryData = $response->json()[0];
                    $country->update([
                        'country_code' => $countryData['cca2'] ?? null,
                        'flag_url' =>  $countryData['flags']['png'] ?? null,
                        'latitude' => $countryData['latlng'][0] ?? null,
                        'longitude' => $countryData['latlng'][1] ?? null,
                    ]);
                }
            }


            return redirect()->back()->with(FlashMsg::item_new('New Country Added'));
        }

        return view('backend.pages.location.add_country');
    }

    public function edit_country(Request $request)
    {
        $request->validate([
            'up_country'=> 'required|max:191|unique:countries,country,'.$request->up_id,
        ]);

        try {
            Country::where('id',$request->up_id)->update([
                'country'=>$request->up_country,
                'country_code'=>$request->country_code,
                'flag_url'=>$request->flag_url,
                'latitude'=>$request->latitude,
                'longitude'=>$request->longitude
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'An error occurred: ' . $e->getMessage()]);
        }

        return redirect()->back()->with(FlashMsg::item_new('Country Update Success'));
    }

    public function change_status_country($id)
    {
        $country = Country::select('status')->where('id',$id)->first(); 
        $country->status==1 ? $status=0 : $status=1;
        Country::where('id',$id)->update(['status'=>$status]);
        return redirect()->back()->with(FlashMsg::item_new('Status Change Success'));
    }

    public function delete_country($id)
    {
        Country::find($id)->delete();
        return redirect()->back()->with(FlashMsg::item_new('Country Deleted Success'));
    }

    public function bulk_action_country(Request $request){
        Country::whereIn('id',$request->ids)->delete();
        return response()->json(['status' => 'ok']);
    }


    public function country_settings(Request $request)
    {
        $countries = Country::all();
        $countries_count = $countries->where('status', 1)->count();
        return view('backend.pages.location.setup_default_country', compact('countries', 'countries_count'));
    }

    public function country_settings_update(Request $request)
    {
        $request->validate([
            'country_id' => 'required',
        ]);

        // Set status to 0 for all other countries in Country, ServiceCity, and ServiceArea
        $selectedCountryId = $request->country_id;
        Country::where('id', '!=', $selectedCountryId)
            ->update(['status' => 0]);

        ServiceCity::where('country_id', '!=', $selectedCountryId)
            ->update(['status' => 0]);

        ServiceArea::where('country_id', '!=', $selectedCountryId)
            ->update(['status' => 0]);

        // Set status to 1 for the selected country in Country, ServiceCity, and ServiceArea
        Country::where('id', $selectedCountryId)
            ->update(['status' => 1]);

        ServiceCity::where('country_id', $selectedCountryId)
            ->update(['status' => 1]);

        ServiceArea::where('country_id', $selectedCountryId)
            ->update(['status' => 1]);

        return redirect()->back()->with(FlashMsg::item_new('Setup Default Country Success'));
    }


}