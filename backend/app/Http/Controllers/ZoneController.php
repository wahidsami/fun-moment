<?php

namespace App\Http\Controllers;

use App\Country;
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class ZoneController extends Controller
{

    // service zone page
    public function serviceZone(Request $request){
        $all_country = Country::where('status', 1)->latest()->get();
        $all_zone = Country::where('status', 1)->where('latitude', '!=', null)->latest()->get();
        return view('backend.pages.services.zone.create', compact('all_country', 'all_zone'));
    }

    public function getCountryCoordinates(Request $request)
    {

        $this->validate($request,[
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        $country = Country::findOrFail($request->country_id);
        $apiKey = get_static_option('service_google_map_api_key');
        $client = new Client();
        $response = $client->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'query' => [
                'address' => $country->country, // Use the country name or appropriate address component
                'key' => $apiKey,
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        $coordinates = null;

        if ($data['status'] === 'OK') {
            $result = $data['results'][0];
            $coordinates = [
                'latitude' => $result['geometry']['location']['lat'],
                'longitude' => $result['geometry']['location']['lng'],
            ];
        }

        if ($coordinates) {
            Country::where('id', $request->country_id)->update([
                'latitude' => $coordinates['latitude'],
                'longitude' => $coordinates['longitude'],
            ]);

            toastr_success(__('Zone Updated Successfully'));
        } else {
            toastr_error(__('Failed to fetch coordinates for the country'));
        }

        return redirect()->route('admin.service.zone');
    }


    public function zoneStatusUpdate(Request $request, $id){

       $zone = Country::find($id);
       if ($zone->zone_status == 1){
           Country::where('id', $id)->update(['zone_status' => 0]);
       }else{
           Country::where('id', $id)->update(['zone_status' => 1]);
       }

        toastr_success(__('Zone Status Change Successfully'));
        return redirect()->back();
    }

    public function zoneDelete(Request $request, $id)
    {
        Country::where('id', $id)->update(['latitude' => null,'longitude' => null,]);
        toastr_error(__('Zone Deleted Successfully'));
        return back();
    }

}
