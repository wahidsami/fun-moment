<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Country;
use App\ServiceCity;
use App\ServiceArea;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ServiceController;

echo "==================================================\n";
echo "SAUDI ARABIA LOCATION MASTER DATA EXPANSION TEST\n";
echo "==================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest($condition, $message) {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] $message\n";
        $passed++;
    } else {
        echo "![FAIL] $message\n";
        $failed++;
    }
}

// 1. Country Audit
$saudi = Country::find(2);
assertTest($saudi !== null && $saudi->country === 'Saudi Arabia', 'Saudi Arabia country record exists with ID 2');
assertTest($saudi->status == 1, 'Saudi Arabia status is 1 (active)');

// 2. City Count and Preservation
$totalCities = ServiceCity::where('country_id', 2)->count();
assertTest($totalCities === 152, "Total Saudi cities count is exactly 152 (Found: $totalCities)");

$riyadh = ServiceCity::find(2);
assertTest($riyadh !== null && $riyadh->service_city === 'Riyadh', 'Riyadh city record preserved with ID 2');
assertTest($riyadh->country_id == 2, 'Riyadh is linked to Saudi Arabia (country_id 2)');

// 3. Area Count and Preservation
$totalAreas = ServiceArea::where('country_id', 2)->count();
assertTest($totalAreas === 3720, "Total Saudi areas count is exactly 3,720 (Found: $totalAreas)");

$olaya = ServiceArea::find(2);
assertTest($olaya !== null && $olaya->service_area === 'Olaya', 'Olaya area record preserved with ID 2');
assertTest($olaya->service_city_id == 2 && $olaya->country_id == 2, 'Olaya linked to Riyadh (city_id 2) and Saudi Arabia (country_id 2)');

// 4. Data Quality: No duplicates, No orphans
$orphanAreas = ServiceArea::whereNotExists(function($q) {
    $q->select('id')->from('service_cities')->whereRaw('service_cities.id = service_areas.service_city_id');
})->count();
assertTest($orphanAreas === 0, "Zero orphan areas in database (Found: $orphanAreas)");

$wrongCountryAreas = ServiceArea::where('country_id', '!=', 2)->count();
assertTest($wrongCountryAreas === 0, "Zero areas with wrong country_id (Found: $wrongCountryAreas)");

$wrongCountryCities = ServiceCity::where('country_id', '!=', 2)->count();
assertTest($wrongCountryCities === 0, "Zero cities with wrong country_id (Found: $wrongCountryCities)");

$duplicateCities = ServiceCity::select('country_id', 'service_city', \DB::raw('count(*) as count'))
    ->groupBy('country_id', 'service_city')
    ->havingRaw('count(*) > 1')
    ->count();
assertTest($duplicateCities === 0, "Zero duplicate cities found (Found: $duplicateCities)");

$duplicateAreas = ServiceArea::select('service_city_id', 'service_area', \DB::raw('count(*) as count'))
    ->groupBy('service_city_id', 'service_area')
    ->havingRaw('count(*) > 1')
    ->count();
assertTest($duplicateAreas === 0, "Zero duplicate areas under same city found (Found: $duplicateAreas)");

// 5. Nationwide Regional City Samples
$samples = [
    'Riyadh' => ['id' => 2, 'min_areas' => 150],
    'Jeddah' => ['id' => 83, 'min_areas' => 150],
    'Makkah' => ['id' => 86, 'min_areas' => 50],
    'Madinah' => ['id' => 72, 'min_areas' => 80],
    'Dammam' => ['id' => 39, 'min_areas' => 70],
    'Al Khobar' => ['id' => 31, 'min_areas' => 30],
    'Abha' => ['id' => 3, 'min_areas' => 30],
    'Tabuk' => ['id' => 151, 'min_areas' => 40],
    'Jazan' => ['id' => 65, 'min_areas' => 10],
    'Najran' => ['id' => 92, 'min_areas' => 50],
];

foreach ($samples as $name => $spec) {
    $city = ServiceCity::find($spec['id']);
    $areasCount = ServiceArea::where('service_city_id', $spec['id'])->count();
    assertTest(
        $city !== null && $city->service_city === $name && $areasCount >= $spec['min_areas'],
        "Sample City '$name' [ID {$spec['id']}] has $areasCount areas (Expected >= {$spec['min_areas']})"
    );
}

// 6. API Controller Endpoint Tests
$userController = new UserController();

// A. Country API
$countryReq = Request::create('/api/v1/country', 'GET', ['per_page' => 50]);
app()->instance('request', $countryReq);
$countryRes = $userController->country($countryReq);
$countryData = json_decode($countryRes->getContent(), true);
assertTest(isset($countryData['countries']['data'][0]['country']) && $countryData['countries']['data'][0]['country'] === 'Saudi Arabia', 'API /country returns Saudi Arabia');

// B. City API with pagination support
$cityReq = Request::create('/api/v1/country/service-city/2', 'GET', ['per_page' => 200]);
app()->instance('request', $cityReq);
$cityRes = $userController->serviceCity(2);
$cityData = json_decode($cityRes->getContent(), true);
$apiCityCount = count($cityData['service_cities']['data'] ?? []);
assertTest($apiCityCount === 152, "API /country/service-city/2 with per_page=200 returns all 152 cities in single response (Got: $apiCityCount)");

// C. Area API for Riyadh with pagination
$areaReq = Request::create('/api/v1/country/service-city/service-area/2/2', 'GET', ['per_page' => 300]);
app()->instance('request', $areaReq);
$areaRes = $userController->serviceArea(2, 2);
$areaData = json_decode($areaRes->getContent(), true);
$apiRiyadhAreas = count($areaData['service_areas']['data'] ?? []);
assertTest($apiRiyadhAreas === 189, "API /service-area/2/2 returns all 189 Riyadh areas with per_page=300 (Got: $apiRiyadhAreas)");

// D. Area API for Jeddah
$jeddahReq = Request::create('/api/v1/country/service-city/service-area/2/83', 'GET', ['per_page' => 300]);
app()->instance('request', $jeddahReq);
$jeddahRes = $userController->serviceArea(2, 83);
$jeddahData = json_decode($jeddahRes->getContent(), true);
$apiJeddahAreas = count($jeddahData['service_areas']['data'] ?? []);
assertTest($apiJeddahAreas === 167, "API /service-area/2/83 returns all 167 Jeddah areas (Got: $apiJeddahAreas)");

// E. ServiceController serviceCity list
$serviceController = new ServiceController();
$exploreCityRes = $serviceController->serviceCity();
$exploreCityData = json_decode($exploreCityRes->getContent(), true);
$exploreCityCount = count($exploreCityData['service_city'] ?? []);
assertTest($exploreCityCount === 152, "ServiceController::serviceCity returns all 152 cities (Got: $exploreCityCount)");

echo "\n==================================================\n";
echo "TEST RESULTS: $passed PASSED, $failed FAILED\n";
echo "==================================================\n";

exit($failed > 0 ? 1 : 0);
