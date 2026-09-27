<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Category;
use App\Service;
use App\ServiceCity;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function assertTest($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] $description\n";
        $passed++;
    } else {
        echo " [FAIL] $description\n";
        $failed++;
    }
}

function resetAuth() {
    Auth::forgetGuards();
}

echo "========================================================\n";
echo "FUN MOMENT — SERVICE CREATION FIX AUTOMATED TESTS\n";
echo "========================================================\n\n";

// Setup prerequisites
$category = Category::firstOrCreate(['name' => 'Test Service Creation Category'], [
    'status' => 1,
    'icon' => 'las la-tools',
    'slug' => 'test-service-creation-category',
]);

$city = ServiceCity::firstOrCreate(['service_city' => 'Riyadh'], [
    'status' => 1,
    'country_id' => 1,
]);

// 1. Seller without service_city
$sellerWithoutCity = User::firstOrCreate(['email' => 'seller_nocity@funmoments.test'], [
    'name' => 'Seller Without City',
    'username' => 'seller_nocity',
    'password' => bcrypt('password123'),
    'user_type' => 0,
    'service_city' => null,
    'country_id' => null,
]);
$sellerWithoutCity->service_city = null;
$sellerWithoutCity->save();
$tokenNoCity = $sellerWithoutCity->createToken('test')->plainTextToken;

// 2. Seller with complete service_city and country
$sellerWithCity = User::firstOrCreate(['email' => 'seller_withcity@funmoments.test'], [
    'name' => 'Seller With City',
    'username' => 'seller_withcity',
    'password' => bcrypt('password123'),
    'user_type' => 0,
    'service_city' => $city->id,
    'country_id' => 1,
]);
$sellerWithCity->service_city = $city->id;
$sellerWithCity->save();
$tokenWithCity = $sellerWithCity->createToken('test')->plainTextToken;

echo "--- 1. City & Profile Location Validation ---\n";
// Test A: Seller without city receives clean HTTP 422
resetAuth();
$req1 = Request::create('/api/v1/seller/service/add-service', 'POST', [
    'title' => 'Service Without City Test Title ' . time(),
    'description' => str_repeat('Detailed service description test string with enough length. ', 5),
    'category_id' => $category->id,
    'price' => 150.00,
]);
$req1->headers->set('Accept', 'application/json');
$req1->headers->set('Authorization', 'Bearer ' . $tokenNoCity);
$res1 = $app->handle($req1);

assertTest("Seller with null service_city receives HTTP 422 (Not 500 DB error)", $res1->getStatusCode() === 422);
$body1 = json_decode($res1->getContent(), true);
assertTest("HTTP 422 contains actionable profile location error message", 
    strpos($body1['message'] ?? '', 'service city/location') !== false ||
    isset($body1['errors']['service_city'])
);

echo "\n--- 2. Price Validation & Persistence ---\n";
// Test B: Missing price rejected with 422
resetAuth();
$req2 = Request::create('/api/v1/seller/service/add-service', 'POST', [
    'title' => 'Service Missing Price Test ' . time(),
    'description' => str_repeat('Detailed service description test string with enough length. ', 5),
    'category_id' => $category->id,
]);
$req2->headers->set('Accept', 'application/json');
$req2->headers->set('Authorization', 'Bearer ' . $tokenWithCity);
$res2 = $app->handle($req2);
assertTest("Missing price rejected with HTTP 422", $res2->getStatusCode() === 422);
$body2 = json_decode($res2->getContent(), true);
assertTest("Error contains price validation error", isset($body2['errors']['price']));

// Test C: Valid service created with price and NO image
resetAuth();
$uniqueTitle = 'Pro Service With Price No Image ' . time();
$req3 = Request::create('/api/v1/seller/service/add-service', 'POST', [
    'title' => $uniqueTitle,
    'description' => str_repeat('Detailed service description test string with enough length. ', 5),
    'category_id' => $category->id,
    'price' => 250.75,
]);
$req3->headers->set('Accept', 'application/json');
$req3->headers->set('Authorization', 'Bearer ' . $tokenWithCity);
$res3 = $app->handle($req3);

assertTest("Service creation with valid price and NO image succeeds (HTTP 201)", $res3->getStatusCode() === 201);
$body3 = json_decode($res3->getContent(), true);
$createdServiceId = $body3['id'] ?? null;
assertTest("Response contains created service ID", !empty($createdServiceId));

$createdService = Service::find($createdServiceId);
assertTest("Service persisted in database", !empty($createdService));
assertTest("Service price correctly persisted (250.75)", $createdService && (float)$createdService->price === 250.75);
assertTest("Service image safely null when not uploaded (no uninitialized variable crash)", $createdService && $createdService->image === null);
assertTest("Service seller_id correctly set", $createdService && $createdService->seller_id == $sellerWithCity->id);
assertTest("Service service_city_id correctly set to provider city", $createdService && $createdService->service_city_id == $city->id);

echo "\n--- 3. Currency Configuration & Fallback ---\n";
// Test D: Currency endpoint returns SAR
$miscController = $app->make(\App\Http\Controllers\Api\MiscellaneousController::class);
$currencyRes = $miscController->currencyInfo();
assertTest("Currency API returns HTTP 201", $currencyRes->getStatusCode() === 201);
$currBody = json_decode($currencyRes->getContent(), true);
$currencyData = $currBody['currency'] ?? [];
assertTest("Currency code is 'SAR'", ($currencyData['code'] ?? '') === 'SAR');
assertTest("Currency symbol is valid ('SAR' or 'SR'), never '$' or '@'", 
    in_array($currencyData['symbol'] ?? '', ['SAR', 'SR', 'ر.س']) && 
    $currencyData['symbol'] !== '$' && 
    $currencyData['symbol'] !== '@'
);

echo "\n--- 4. Existing Service Listings & Flow Integrity ---\n";
// Test E: Seller my-services listing includes new service
$sellerServiceController = $app->make(\App\Http\Controllers\Api\SellerServiceController::class);
// Authenticate as seller
resetAuth();
Auth::guard('sanctum')->setUser($sellerWithCity);
$myServicesRes = $sellerServiceController->myService();
assertTest("Seller myService API succeeds (HTTP 201)", $myServicesRes->getStatusCode() === 201);
$myServicesBody = json_decode($myServicesRes->getContent(), true);
$myServicesList = $myServicesBody['my_services']['data'] ?? [];
$foundInListing = false;
$foundServiceStatus = null;
foreach ($myServicesList as $srv) {
    if ($srv['id'] == $createdServiceId) {
        $foundInListing = true;
        $foundServiceStatus = $srv['status'] ?? null;
        break;
    }
}
assertTest("Newly created service appears in seller's service listing", $foundInListing);
assertTest("Newly created service exposes status = 0 (Pending approval) to provider", $foundServiceStatus === 0 || $foundServiceStatus === "0");

// Clean up test records
if ($createdService) {
    $createdService->metaData()->delete();
    $createdService->delete();
}
$sellerWithoutCity->tokens()->delete();
$sellerWithCity->tokens()->delete();

echo "\n========================================================\n";
echo "SUMMARY: $passed PASSED, $failed FAILED\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
