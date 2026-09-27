<?php

namespace App\Http\Controllers;

use App\AdminAuditLog;
use App\Country;
use App\ServiceCity;
use App\ServiceArea;
use App\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLocationApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function apiLocations(): JsonResponse
    {
        $countries = Country::orderBy('id')->get()->map(function (Country $c) {
            return [
                'id' => (string) $c->id,
                'countryCode' => $c->country_code ?? 'SA',
                'nameEn' => $c->country,
                'nameAr' => $c->country,
                'phoneCode' => '+966',
                'currency' => 'SAR',
                'status' => (int) $c->status === 1 ? 'active' : 'inactive',
            ];
        })->values();

        $cities = ServiceCity::with('countryy')->orderBy('id')->get()->map(function (ServiceCity $c) {
            return [
                'id' => (int) $c->id,
                'countryId' => (string) $c->country_id,
                'nameEn' => $c->service_city,
                'nameAr' => $c->service_city,
                'status' => (int) $c->status === 1 ? 'active' : 'inactive',
            ];
        })->values();

        $areas = ServiceArea::orderBy('id')->get()->map(function (ServiceArea $a) {
            return [
                'id' => (int) $a->id,
                'cityId' => (int) $a->service_city_id,
                'countryId' => (string) $a->country_id,
                'nameEn' => $a->service_area,
                'nameAr' => $a->service_area,
                'status' => (int) $a->status === 1 ? 'active' : 'inactive',
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'countries' => $countries,
            'cities' => $cities,
            'areas' => $areas,
        ]);
    }

    public function apiCreateLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'level' => 'required|in:country,city,area',
            'name_en' => 'required|string|max:191',
            'name_ar' => 'nullable|string|max:191',
            'code' => 'nullable|string|max:10',
            'country_id' => 'nullable|integer',
            'city_id' => 'nullable|integer',
        ]);

        $name = trim($validated['name_en']);

        if ($validated['level'] === 'country') {
            if (Country::where('country', $name)->exists()) {
                return response()->json(['status' => 'error', 'message' => __('Country already exists.')], 422);
            }

            $country = Country::create([
                'country' => $name,
                'country_code' => $validated['code'] ?? 'SA',
                'status' => 1,
            ]);

            AdminAuditLog::record([
                'action' => 'location_country_create',
                'resource_type' => 'Country',
                'resource_id' => (string) $country->id,
                'details_en' => "Created country '{$country->country}'",
                'details_ar' => "تم إنشاء الدولة '{$country->country}'",
                'new_values' => $country->toArray(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Country created successfully.'),
                'node' => [
                    'id' => (string) $country->id,
                    'countryCode' => $country->country_code,
                    'nameEn' => $country->country,
                    'nameAr' => $country->country,
                    'phoneCode' => '+966',
                    'currency' => 'SAR',
                    'status' => 'active',
                ],
            ]);
        }

        if ($validated['level'] === 'city') {
            if (empty($validated['country_id'])) {
                return response()->json(['status' => 'error', 'message' => __('Country selection is required.')], 422);
            }

            $country = Country::findOrFail($validated['country_id']);
            $city = ServiceCity::create([
                'service_city' => $name,
                'country_id' => $country->id,
                'status' => 1,
            ]);

            AdminAuditLog::record([
                'action' => 'location_city_create',
                'resource_type' => 'ServiceCity',
                'resource_id' => (string) $city->id,
                'details_en' => "Created city '{$city->service_city}' under country #{$country->id}",
                'details_ar' => "تم إنشاء المدينة '{$city->service_city}' تحت الدولة #{$country->id}",
                'new_values' => $city->toArray(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('City created successfully.'),
                'node' => [
                    'id' => (int) $city->id,
                    'countryId' => (string) $city->country_id,
                    'nameEn' => $city->service_city,
                    'nameAr' => $city->service_city,
                    'status' => 'active',
                ],
            ]);
        }

        // Area
        if (empty($validated['city_id'])) {
            return response()->json(['status' => 'error', 'message' => __('City selection is required.')], 422);
        }

        $city = ServiceCity::findOrFail($validated['city_id']);
        $area = ServiceArea::create([
            'service_area' => $name,
            'service_city_id' => $city->id,
            'country_id' => $city->country_id,
            'status' => 1,
        ]);

        AdminAuditLog::record([
            'action' => 'location_area_create',
            'resource_type' => 'ServiceArea',
            'resource_id' => (string) $area->id,
            'details_en' => "Created service area '{$area->service_area}' under city #{$city->id}",
            'details_ar' => "تم إنشاء منطقة الخدمة '{$area->service_area}' تحت المدينة #{$city->id}",
            'new_values' => $area->toArray(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Service area created successfully.'),
            'node' => [
                'id' => (int) $area->id,
                'cityId' => (int) $area->service_city_id,
                'countryId' => (string) $area->country_id,
                'nameEn' => $area->service_area,
                'nameAr' => $area->service_area,
                'status' => 'active',
            ],
        ]);
    }

    public function apiUpdateLocationStatus(Request $request, string $level, $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|in:active,inactive',
        ]);

        $statusValue = null;
        if (isset($validated['status'])) {
            $statusValue = $validated['status'] === 'active' ? 1 : 0;
        }

        if ($level === 'country') {
            $country = Country::findOrFail($id);
            $newStatus = $statusValue !== null ? $statusValue : ($country->status == 1 ? 0 : 1);
            $country->update(['status' => $newStatus]);
            AdminAuditLog::record([
                'action' => 'country_status',
                'resource_type' => 'Country',
                'resource_id' => (string) $id,
                'details_en' => "Changed country #{$id} status to {$newStatus}",
                'details_ar' => "تم تغيير حالة الدولة #{$id} إلى {$newStatus}",
            ]);
            return response()->json(['status' => 'success', 'message' => __('Status updated.'), 'new_status' => $newStatus == 1 ? 'active' : 'inactive']);
        }

        if ($level === 'city') {
            $city = ServiceCity::findOrFail($id);
            $newStatus = $statusValue !== null ? $statusValue : ($city->status == 1 ? 0 : 1);
            $city->update(['status' => $newStatus]);
            AdminAuditLog::record([
                'action' => 'city_status',
                'resource_type' => 'ServiceCity',
                'resource_id' => (string) $id,
                'details_en' => "Changed city #{$id} status to {$newStatus}",
                'details_ar' => "تم تغيير حالة المدينة #{$id} إلى {$newStatus}",
            ]);
            return response()->json(['status' => 'success', 'message' => __('Status updated.'), 'new_status' => $newStatus == 1 ? 'active' : 'inactive']);
        }

        $area = ServiceArea::findOrFail($id);
        $newStatus = $statusValue !== null ? $statusValue : ($area->status == 1 ? 0 : 1);
        $area->update(['status' => $newStatus]);
        AdminAuditLog::record([
            'action' => 'area_status',
            'resource_type' => 'ServiceArea',
            'resource_id' => (string) $id,
            'details_en' => "Changed service area #{$id} status to {$newStatus}",
            'details_ar' => "تم تغيير حالة المنطقة #{$id} إلى {$newStatus}",
        ]);
        return response()->json(['status' => 'success', 'message' => __('Status updated.'), 'new_status' => $newStatus == 1 ? 'active' : 'inactive']);
    }

    public function apiDeleteLocation(Request $request, string $level, $id): JsonResponse
    {
        if ($level === 'country') {
            $country = Country::findOrFail($id);
            if (ServiceCity::where('country_id', $id)->count() > 0) {
                return response()->json(['status' => 'error', 'message' => __('Cannot delete country with associated cities.')], 422);
            }
            $country->delete();
            AdminAuditLog::record([
                'action' => 'country_delete',
                'resource_type' => 'Country',
                'resource_id' => (string) $id,
                'details_en' => "Deleted country #{$id}",
                'details_ar' => "تم حذف الدولة #{$id}",
            ]);
            return response()->json(['status' => 'success', 'message' => __('Country deleted successfully.')]);
        }

        if ($level === 'city') {
            $city = ServiceCity::findOrFail($id);
            if (ServiceArea::where('service_city_id', $id)->count() > 0) {
                return response()->json(['status' => 'error', 'message' => __('Cannot delete city with associated areas.')], 422);
            }
            if (Service::where('service_city_id', $id)->count() > 0) {
                return response()->json(['status' => 'error', 'message' => __('Cannot delete city with linked services.')], 422);
            }
            $city->delete();
            AdminAuditLog::record([
                'action' => 'city_delete',
                'resource_type' => 'ServiceCity',
                'resource_id' => (string) $id,
                'details_en' => "Deleted city #{$id}",
                'details_ar' => "تم حذف المدينة #{$id}",
            ]);
            return response()->json(['status' => 'success', 'message' => __('City deleted successfully.')]);
        }

        $area = ServiceArea::findOrFail($id);
        if (Service::where('service_area_id', $id)->count() > 0) {
            return response()->json(['status' => 'error', 'message' => __('Cannot delete area with linked services.')], 422);
        }
        $area->delete();
        AdminAuditLog::record([
            'action' => 'area_delete',
            'resource_type' => 'ServiceArea',
            'resource_id' => (string) $id,
            'details_en' => "Deleted service area #{$id}",
            'details_ar' => "تم حذف منطقة الخدمة #{$id}",
        ]);
        return response()->json(['status' => 'success', 'message' => __('Service area deleted successfully.')]);
    }
}
