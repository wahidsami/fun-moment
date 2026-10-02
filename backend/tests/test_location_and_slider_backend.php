<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\Country;
use App\ServiceCity;
use App\ServiceArea;
use App\User;
use App\SellerVerify;
use App\Http\Controllers\Api\SliderController;
use App\Http\Controllers\FrontendUserManageController;
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

echo "========================================================\n";
echo "FUN MOMENT — SLIDER RESILIENCE & LOCATION PERSISTENCE TESTS\n";
echo "========================================================\n\n";

// --------------------------------------------------------------------------
// TEST 1: SLIDER CONTROLLER SAFE IMAGE RESOLUTION
// --------------------------------------------------------------------------
echo "--- 1. SLIDER CONTROLLER SAFE IMAGE RESOLUTION ---\n";

$sliderController = $app->make(SliderController::class);
$sliderRes = $sliderController->slider();
assertTest("Slider API returns HTTP 200 or 201", in_array($sliderRes->getStatusCode(), [200, 201], true));

$sliderData = json_decode($sliderRes->getContent(), true);
assertTest("Slider response contains slider-details", isset($sliderData['slider-details']));
assertTest("Slider response contains image_url list", isset($sliderData['image_url']) && is_array($sliderData['image_url']));

if (!empty($sliderData['image_url'])) {
    $firstImg = $sliderData['image_url'][0];
    assertTest("First image is a structured object, not an empty array [[]]", is_array($firstImg) && !empty(array_keys($firstImg)));
    assertTest("Image object contains path or img_url keys", array_key_exists('path', $firstImg) && array_key_exists('img_url', $firstImg));
}

// --------------------------------------------------------------------------
// TEST 2: AUTHORITATIVE LOCATION RECORDS
// --------------------------------------------------------------------------
echo "\n--- 2. AUTHORITATIVE LOCATION RECORDS ---\n";

$saudi = Country::where('id', 2)->where('status', 1)->first();
assertTest("Saudi Arabia exists as Country ID 2 with status 1", $saudi !== null && $saudi->country === 'Saudi Arabia');

$riyadh = ServiceCity::where('id', 2)->where('country_id', 2)->first();
assertTest("Riyadh exists as City ID 2 under Saudi Arabia (country_id 2)", $riyadh !== null && $riyadh->service_city === 'Riyadh');

$olaya = ServiceArea::where('id', 2)->where('service_city_id', 2)->first();
assertTest("Olaya exists as Area ID 2 under Riyadh (service_city_id 2)", $olaya !== null && $olaya->service_area === 'Olaya');

// --------------------------------------------------------------------------
// TEST 3: REGISTRATION PERSISTENCE (INTEGER FOREIGN KEYS)
// --------------------------------------------------------------------------
echo "\n--- 3. REGISTRATION PERSISTENCE AS INTEGER FOREIGN KEYS ---\n";

DB::beginTransaction();
try {
    $testUser = User::create([
        'name' => 'Forensic Provider Test',
        'email' => 'forensic_provider_' . time() . '@test.com',
        'username' => 'forensic_provider_' . time(),
        'phone' => '+966555123456',
        'password' => bcrypt('password123'),
        'service_city' => 2,
        'state' => 2,
        'service_area' => 2,
        'country_code' => 'SA',
        'country_id' => 2,
        'user_type' => User::USER_TYPE_SELLER,
        'seller_type' => User::SELLER_TYPE_INDIVIDUAL,
        'user_status' => 1,
        'terms_condition' => 1,
    ]);

    SellerVerify::create([
        'seller_id' => $testUser->id,
        'status' => SellerVerify::STATUS_PENDING,
        'national_id_number' => '1029384756',
    ]);

    assertTest("User record created with country_id = 2", (int)$testUser->country_id === 2);
    assertTest("User record created with service_city = 2", (int)$testUser->service_city === 2);
    assertTest("User record created with service_area = 2", (int)$testUser->service_area === 2);

    // --------------------------------------------------------------------------
    // TEST 4: ADMIN RESOLUTION OF LOCATION RELATIONSHIPS
    // --------------------------------------------------------------------------
    echo "\n--- 4. ADMIN DOSSIER LOCATION RESOLUTION ---\n";

    $admin = Admin::first();
    Auth::guard('admin')->setUser($admin);

    $adminController = $app->make(FrontendUserManageController::class);
    $verifRes = $adminController->apiGetVerification(new Request(), $testUser->id);

    assertTest("Admin apiGetVerification returns HTTP 200", $verifRes->getStatusCode() === 200);

    $verifData = json_decode($verifRes->getContent(), true);
    $verif = $verifData['verification'] ?? [];

    assertTest("Admin verification resolves country to 'Saudi Arabia'", ($verif['country'] ?? '') === 'Saudi Arabia');
    assertTest("Admin verification preserves country_id = 2", (int)($verif['country_id'] ?? 0) === 2);
    assertTest("Admin verification resolves city to 'Riyadh'", ($verif['city'] ?? '') === 'Riyadh');
    assertTest("Admin verification resolves area to 'Olaya'", ($verif['area'] ?? '') === 'Olaya');

} finally {
    DB::rollBack();
}

echo "\n========================================================\n";
echo "SUMMARY: Passed: $passed, Failed: $failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
