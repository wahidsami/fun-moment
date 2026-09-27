<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Category;
use App\Http\Controllers\Api\SellerServiceController;
use App\Http\Controllers\Api\ServiceController as CustomerServiceController;
use App\Http\Controllers\ServiceController as AdminServiceController;
use App\Service;
use App\ServiceCity;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
echo "FUN MOMENT — COMPLETE SERVICE LIFECYCLE AUDIT TESTS\n";
echo "========================================================\n\n";

// --- SETUP PREREQUISITES ---
$category = Category::firstOrCreate(['name' => 'Home Cleaning'], [
    'status' => 1,
    'icon' => 'las la-broom',
    'slug' => 'home-cleaning',
]);

$city = ServiceCity::firstOrCreate(['service_city' => 'Riyadh'], [
    'status' => 1,
    'country_id' => 1,
]);

// Provider 1
$provider1 = User::firstOrCreate(['email' => 'provider_lifecycle_1@funmoments.test'], [
    'name' => 'Provider One',
    'username' => 'provider_one',
    'password' => bcrypt('password123'),
    'user_type' => 0,
    'service_city' => $city->id,
    'country_id' => 1,
]);
$provider1->user_type = 0;
$provider1->service_city = $city->id;
$provider1->user_status = 1;
$provider1->save();
$tokenProvider1 = $provider1->createToken('test_p1')->plainTextToken;

// Provider 2
$provider2 = User::firstOrCreate(['email' => 'provider_lifecycle_2@funmoments.test'], [
    'name' => 'Provider Two',
    'username' => 'provider_two',
    'password' => bcrypt('password123'),
    'user_type' => 0,
    'service_city' => $city->id,
    'country_id' => 1,
]);
$provider2->user_type = 0;
$provider2->service_city = $city->id;
$provider2->user_status = 1;
$provider2->save();
$tokenProvider2 = $provider2->createToken('test_p2')->plainTextToken;

// Customer
$customer = User::firstOrCreate(['email' => 'customer_lifecycle@funmoments.test'], [
    'name' => 'Customer Lifecycle',
    'username' => 'customer_lifecycle',
    'password' => bcrypt('password123'),
    'user_type' => 1,
    'service_city' => $city->id,
    'country_id' => 1,
]);
$customer->user_type = 1;
$customer->save();
$tokenCustomer = $customer->createToken('test_cust')->plainTextToken;

echo "--- A. PROVIDER SERVICE CREATION & SECURITY ---\n";

// Test 1: Provider creates service successfully
resetAuth();
$serviceTitle = 'Lifecycle Test Service ' . time();
$createReq = Request::create('/api/v1/seller/service/add-service', 'POST', [
    'title' => $serviceTitle,
    'description' => str_repeat('Complete detailed description of the service conforming to platform standards. ', 5),
    'category_id' => $category->id,
    'price' => 299.50,
    'seller_id' => $provider2->id, // Intentionally pass provider 2's ID to test ownership spoof prevention
]);
$createReq->headers->set('Accept', 'application/json');
$createReq->headers->set('Authorization', 'Bearer ' . $tokenProvider1);

$resCreate = $app->handle($createReq);
assertTest("Provider service creation succeeds (HTTP 201)", $resCreate->getStatusCode() === 201);
$bodyCreate = json_decode($resCreate->getContent(), true);
$createdId = $bodyCreate['id'] ?? null;
assertTest("Response includes deterministic created service ID", !empty($createdId));

// Test 2: Check persistence and ownership enforcement (Prevention of Seller Spoofing)
$serviceRow = Service::find($createdId);
assertTest("Service is persisted in database", !empty($serviceRow));
assertTest("Service seller_id strictly set to authenticated provider (NOT spoofed seller_id)", $serviceRow && (int)$serviceRow->seller_id === $provider1->id);
assertTest("Service price correctly persisted (299.50)", $serviceRow && (float)$serviceRow->price === 299.50);
assertTest("Service title correctly persisted", $serviceRow && $serviceRow->title === $serviceTitle);
assertTest("Service status is 0 (Pending Admin Approval)", $serviceRow && (int)$serviceRow->status === 0);
assertTest("Service is_service_on is 1", $serviceRow && (int)$serviceRow->is_service_on === 1);
assertTest("Service service_city_id matches provider city", $serviceRow && (int)$serviceRow->service_city_id === $city->id);

echo "\n--- B. PROVIDER RETRIEVES OWN PENDING SERVICE ---\n";

// Test 3: Provider 1 can see own pending service in my-services
resetAuth();
$myServicesReq = Request::create('/api/v1/seller/service/my-services', 'GET');
$myServicesReq->headers->set('Accept', 'application/json');
$myServicesReq->headers->set('Authorization', 'Bearer ' . $tokenProvider1);
$resMyServices = $app->handle($myServicesReq);

assertTest("Provider my-services returns HTTP 201", $resMyServices->getStatusCode() === 201);
$myServicesBody = json_decode($resMyServices->getContent(), true);
$myServicesList = $myServicesBody['my_services']['data'] ?? [];
$foundInProviderList = false;
$foundStatus = null;
foreach ($myServicesList as $item) {
    if ($item['id'] == $createdId) {
        $foundInProviderList = true;
        $foundStatus = (int) $item['status'];
        break;
    }
}
assertTest("Newly created pending service appears in Provider 1's My Services", $foundInProviderList);
assertTest("Pending service in My Services has status = 0", $foundStatus === 0);

// Test 4: Provider 2 does NOT see Provider 1's service
resetAuth();
$p2Req = Request::create('/api/v1/seller/service/my-services', 'GET');
$p2Req->headers->set('Accept', 'application/json');
$p2Req->headers->set('Authorization', 'Bearer ' . $tokenProvider2);
$resP2 = $app->handle($p2Req);
$p2Body = json_decode($resP2->getContent(), true);
$p2List = $p2Body['my_services']['data'] ?? [];
$foundInP2 = false;
foreach ($p2List as $item) {
    if ($item['id'] == $createdId) {
        $foundInP2 = true;
        break;
    }
}
assertTest("Provider 2 cannot see Provider 1's service in My Services", !$foundInP2);

echo "\n--- C. CUSTOMER VISIBILITY RESTRICTIONS (PENDING SERVICE MUST BE HIDDEN) ---\n";

// Test 5: Customer cannot see pending service in all-services
resetAuth();
$allServicesReq = Request::create('/api/v1/service-list/all-services', 'GET');
$allServicesReq->headers->set('Accept', 'application/json');
$resAllServices = $app->handle($allServicesReq);
$allServicesBody = json_decode($resAllServices->getContent(), true);
$allServicesList = $allServicesBody['all_services']['data'] ?? [];
$foundByCustomer = false;
foreach ($allServicesList as $srv) {
    if ($srv['id'] == $createdId) {
        $foundByCustomer = true;
        break;
    }
}
assertTest("Customer CANNOT see pending service in /api/v1/service-list/all-services", !$foundByCustomer);

// Test 6: Customer cannot see pending service details
resetAuth();
$detailsReq = Request::create('/api/v1/service-details/' . $createdId, 'GET');
$detailsReq->headers->set('Accept', 'application/json');
$resDetails = $app->handle($detailsReq);
assertTest("Customer cannot access pending service details (returns error/500)", $resDetails->getStatusCode() !== 200);

echo "\n--- D. ADMIN DASHBOARD & APPROVAL WORKFLOW ---\n";

$adminController = $app->make(AdminServiceController::class);

// Test 7: Admin GET /admin-home/services-json returns service with status 'pending'
$adminListRes = $adminController->apiServices();
assertTest("Admin apiServices returns HTTP 200", $adminListRes->getStatusCode() === 200);
$adminListBody = json_decode($adminListRes->getContent(), true);
$adminServices = $adminListBody['services'] ?? [];
$foundInAdmin = null;
foreach ($adminServices as $as) {
    if ($as['id'] == $createdId) {
        $foundInAdmin = $as;
        break;
    }
}
assertTest("Admin sees newly created service", !empty($foundInAdmin));
assertTest("Admin sees status as 'pending' (NOT 'suspended')", $foundInAdmin && $foundInAdmin['status'] === 'pending');
assertTest("Admin apiServices returns real sellers list", !empty($adminListBody['sellers']) && count($adminListBody['sellers']) > 0);
assertTest("Admin apiServices returns categories list", !empty($adminListBody['categories']) && count($adminListBody['categories']) > 0);

// Test 8: Admin approves service (POST /admin-home/services-json/{id}/status -> 'active')
$statusReq = Request::create('/admin-home/services-json/' . $createdId . '/status', 'POST', [
    'status' => 'active',
]);
$statusRes = $adminController->apiUpdateServiceStatus($statusReq, $createdId);
assertTest("Admin status update to 'active' succeeds (HTTP 200)", $statusRes->getStatusCode() === 200);
$serviceRow->refresh();
assertTest("Database service status is now 1 (Active/Approved)", (int)$serviceRow->status === 1);

echo "\n--- E. CUSTOMER VISIBILITY AFTER APPROVAL ---\n";

// Test 9: Customer can now see approved service in service-list
resetAuth();
$allServicesApprovedReq = Request::create('/api/v1/service-list/all-services', 'GET');
$allServicesApprovedReq->headers->set('Accept', 'application/json');
$resAllServicesApproved = $app->handle($allServicesApprovedReq);
$allApprovedBody = json_decode($resAllServicesApproved->getContent(), true);
$allApprovedList = $allApprovedBody['all_services']['data'] ?? [];
$foundApproved = false;
foreach ($allApprovedList as $srv) {
    if ($srv['id'] == $createdId) {
        $foundApproved = true;
        break;
    }
}
assertTest("Customer CAN see approved service in /api/v1/service-list/all-services", $foundApproved);

// Test 10: Customer can now view service details
resetAuth();
$detailsApprovedReq = Request::create('/api/v1/service-details/' . $createdId, 'GET');
$detailsApprovedReq->headers->set('Accept', 'application/json');
$resDetailsApproved = $app->handle($detailsApprovedReq);
assertTest("Customer CAN view approved service details (HTTP 200/201)", in_array($resDetailsApproved->getStatusCode(), [200, 201]));

echo "\n--- F. ADMIN SERVICE CREATION & VALIDATION ---\n";

// Test 11: Admin creating service with non-existent seller (e.g. 101) returns 404
$reqDummySeller = Request::create('/admin-home/services-json', 'POST', [
    'title_en' => 'Service for Nonexistent Seller',
    'title_ar' => 'خدمة لبائع غير موجود',
    'category_id' => $category->id,
    'seller_id' => 101, // Non-existent seller
    'price' => 120.0,
    'duration' => '1 day',
    'description_en' => 'Description test',
    'description_ar' => 'وصف اختباري',
]);
try {
    $resDummy = $adminController->apiCreateService($reqDummySeller);
    assertTest("Non-existent seller 101 rejected", $resDummy->getStatusCode() === 404);
} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    assertTest("Non-existent seller 101 correctly throws ModelNotFoundException (HTTP 404)", true);
}

// Test 12: Admin creating service for real seller succeeds and is persisted
$adminServiceTitle = 'Admin Created Real Service ' . time();
$reqRealSeller = Request::create('/admin-home/services-json', 'POST', [
    'title_en' => $adminServiceTitle,
    'title_ar' => 'خدمة تم إنشاؤها بواسطة المدير',
    'category_id' => $category->id,
    'seller_id' => $provider1->id,
    'price' => 175.0,
    'duration' => '2 days',
    'description_en' => 'Admin created service description with adequate details.',
    'description_ar' => 'وصف الخدمة المنشأة بواسطة المدير.',
]);
$resAdminCreate = $adminController->apiCreateService($reqRealSeller);
assertTest("Admin creates service for real seller successfully (HTTP 200)", $resAdminCreate->getStatusCode() === 200);
$adminCreatedBody = json_decode($resAdminCreate->getContent(), true);
$adminCreatedId = $adminCreatedBody['service']['id'] ?? null;
assertTest("Admin created service returns valid ID", !empty($adminCreatedId));

$adminCreatedRow = Service::find($adminCreatedId);
assertTest("Admin created service persisted in database", !empty($adminCreatedRow));
assertTest("Admin created service status is active (1)", $adminCreatedRow && (int)$adminCreatedRow->status === 1);
assertTest("Admin created service seller_id matches chosen seller", $adminCreatedRow && (int)$adminCreatedRow->seller_id === $provider1->id);

// --- CLEANUP TEST DATA ---
if ($serviceRow) {
    $serviceRow->metaData()->delete();
    $serviceRow->delete();
}
if ($adminCreatedRow) {
    $adminCreatedRow->metaData()->delete();
    $adminCreatedRow->delete();
}
$provider1->tokens()->delete();
$provider2->tokens()->delete();
$customer->tokens()->delete();

echo "\n========================================================\n";
echo "SUMMARY: $passed PASSED, $failed FAILED\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
