<?php
/**
 * ============================================================
 * FUN MOMENT — SERVICE-SPECIFIC AVAILABILITY E2E TEST SUITE
 * Phase 6.1 — Validates service-scoped availability isolation
 * and cross-service double-booking protection.
 * ============================================================
 *
 * Test Scenarios:
 *   A. Provider creates Service A (Wedding DJ) → sets Sun 10-12
 *   B. Provider creates Service B (Birthday DJ) → sets Sun 15-18
 *   C. Buyer queries Service A schedule → sees only 10-12
 *   D. Buyer queries Service B schedule → sees only 15-18
 *   E. Buyer books Service A Sun 10:00-12:00
 *   F. Booking Service B Sun 10:30-11:30 is blocked (provider busy)
 *   G. Booking Service B Sun 15:00-18:00 succeeds (no overlap)
 */

require __DIR__ . '/../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../backend/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\User;
use App\Service;
use App\Category;
use App\ServiceCity;
use App\Day;
use App\Schedule;
use App\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

echo "=================================================================\n";
echo "FUN MOMENT — SERVICE-SPECIFIC AVAILABILITY ISOLATION E2E SUITE\n";
echo "=================================================================\n\n";

$passCount = 0;
$failCount = 0;
$errors = [];

function pass(string $name): void {
    global $passCount;
    echo "\033[32m[PASS]\033[0m $name\n";
    $passCount++;
}

function fail(string $name, string $detail = ''): void {
    global $failCount, $errors;
    echo "\033[31m[FAIL]\033[0m $name" . ($detail ? " — $detail" : '') . "\n";
    $failCount++;
    $errors[] = $name . ($detail ? ": $detail" : '');
}

function check(string $name, bool $condition, string $detail = ''): void {
    if ($condition) pass($name); else fail($name, $detail);
}

// ─── Setup ─────────────────────────────────────────────────────────────────

$stamp = time();

$city = ServiceCity::first() ?? ServiceCity::create(['service_city' => 'Riyadh', 'status' => 1]);
$category = Category::where('status', 1)->first() ?? Category::create([
    'name' => 'Events & Entertainment',
    'slug' => 'events-entertainment-' . $stamp,
    'status' => 1,
]);

$provider = User::create([
    'name'         => 'E2E Provider SA-' . $stamp,
    'email'        => 'e2e_prov_sa_' . $stamp . '@funmoments.test',
    'username'     => 'e2e_prov_sa_' . $stamp,
    'password'     => Hash::make('password123'),
    'user_type'    => 0,
    'user_status'  => 1,
    'service_city' => $city->id,
]);

$buyer = User::create([
    'name'        => 'E2E Buyer SA-' . $stamp,
    'email'       => 'e2e_buyer_sa_' . $stamp . '@funmoments.test',
    'username'    => 'e2e_buyer_sa_' . $stamp,
    'password'    => Hash::make('password123'),
    'user_type'   => 1,
    'user_status' => 1,
]);

check("Provider user created", $provider->id > 0);
check("Buyer user created",    $buyer->id > 0);

Auth::guard('sanctum')->setUser($provider);

$sellerController  = new \App\Http\Controllers\Api\SellerController();
$serviceController = new \App\Http\Controllers\Api\ServiceController();

// ─── Step 1: Create Service A ───────────────────────────────────────────────
echo "\n--- Step 1: Create Service A (Wedding DJ Package) ---\n";

$serviceA = Service::create([
    'seller_id'   => $provider->id,
    'title'       => 'Wedding DJ Package',
    'slug'        => 'wedding-dj-package-' . $stamp,
    'description' => 'Premium wedding DJ package with full setup',
    'category_id' => $category->id,
    'price'       => 1500,
    'status'      => 1,  // admin-approved for test
    'service_city_id' => $city->id,
]);
check("Service A created", $serviceA->id > 0, "id={$serviceA->id}");

// ─── Step 2: Create Service B ───────────────────────────────────────────────
echo "\n--- Step 2: Create Service B (Birthday DJ Package) ---\n";

$serviceB = Service::create([
    'seller_id'   => $provider->id,
    'title'       => 'Birthday DJ Package',
    'slug'        => 'birthday-dj-package-' . $stamp,
    'description' => 'Fun birthday DJ package for all ages',
    'category_id' => $category->id,
    'price'       => 800,
    'status'      => 1,
    'service_city_id' => $city->id,
]);
check("Service B created", $serviceB->id > 0, "id={$serviceB->id}");

// ─── Step 3: Configure Service A availability (Sun 10:00-12:00) ────────────
echo "\n--- Step 3: Set Service A availability → Sun 10:00 AM - 12:00 PM ---\n";

$createDayAReq = Request::create('/api/v1/seller/create-day', 'POST', [
    'day'        => 'Sunday',
    'service_id' => $serviceA->id,
]);
$resp = $sellerController->createDay($createDayAReq);
$dayAData = json_decode($resp->getContent(), true);
check(
    "Day 'Sunday' created for Service A",
    ($dayAData['status'] ?? '') === 'success' && !empty($dayAData['day']['id']),
    json_encode($dayAData)
);
$dayAId = $dayAData['day']['id'] ?? null;

if ($dayAId) {
    $slotAReq = Request::create('/api/v1/seller/schedule/create', 'POST', [
        'day_id'     => $dayAId,
        'schedule'   => '10:00 AM - 12:00 PM',
        'service_id' => $serviceA->id,
    ]);
    $resp = $sellerController->scheduleCreate($slotAReq);
    $slotAData = json_decode($resp->getContent(), true);
    check(
        "Time slot '10:00 AM - 12:00 PM' added to Service A / Sunday",
        isset($slotAData['schedule']['id']),
        json_encode($slotAData)
    );
    $slotAId = $slotAData['schedule']['id'] ?? null;
}

// ─── Step 4: Configure Service B availability (Sun 15:00-18:00) ────────────
echo "\n--- Step 4: Set Service B availability → Sun 03:00 PM - 06:00 PM ---\n";

$createDayBReq = Request::create('/api/v1/seller/create-day', 'POST', [
    'day'        => 'Sunday',
    'service_id' => $serviceB->id,
]);
$resp = $sellerController->createDay($createDayBReq);
$dayBData = json_decode($resp->getContent(), true);
check(
    "Day 'Sunday' created for Service B (independent of A)",
    ($dayBData['status'] ?? '') === 'success' && !empty($dayBData['day']['id']),
    json_encode($dayBData)
);
$dayBId = $dayBData['day']['id'] ?? null;

if ($dayBId) {
    $slotBReq = Request::create('/api/v1/seller/schedule/create', 'POST', [
        'day_id'     => $dayBId,
        'schedule'   => '03:00 PM - 06:00 PM',
        'service_id' => $serviceB->id,
    ]);
    $resp = $sellerController->scheduleCreate($slotBReq);
    $slotBData = json_decode($resp->getContent(), true);
    check(
        "Time slot '03:00 PM - 06:00 PM' added to Service B / Sunday",
        isset($slotBData['schedule']['id']),
        json_encode($slotBData)
    );
    $slotBId = $slotBData['schedule']['id'] ?? null;
}

// ─── Step 5: Verify schedule isolation — list endpoints ────────────────────
echo "\n--- Step 5: Verify Service A & B days are isolated in schedule-days-list ---\n";

// Service A list
$listAReq = Request::create('/api/v1/seller/schedule-days-list', 'GET', ['service_id' => $serviceA->id]);
$resp = $sellerController->scheduleDaysList($listAReq);
$listA = json_decode($resp->getContent(), true);
$listAHasSunday = false;
$listASlotIsCorrect = false;
if (is_array($listA)) {
    foreach ($listA as $d) {
        if (strtolower(substr($d['day'] ?? '', 0, 3)) === 'sun') {
            $listAHasSunday = true;
            if (isset($d['schedules'])) {
                foreach ($d['schedules'] as $s) {
                    if (str_contains($s['schedule'] ?? '', '10:00 AM')) {
                        $listASlotIsCorrect = true;
                    }
                }
            }
        }
    }
}
check("Service A schedule list contains Sunday",           $listAHasSunday);
check("Service A Sunday slot is '10:00 AM - 12:00 PM'",  $listASlotIsCorrect);

// Service B list
$listBReq = Request::create('/api/v1/seller/schedule-days-list', 'GET', ['service_id' => $serviceB->id]);
$resp = $sellerController->scheduleDaysList($listBReq);
$listB = json_decode($resp->getContent(), true);
$listBHasSunday = false;
$listBSlotIsCorrect = false;
if (is_array($listB)) {
    foreach ($listB as $d) {
        if (strtolower(substr($d['day'] ?? '', 0, 3)) === 'sun') {
            $listBHasSunday = true;
            if (isset($d['schedules'])) {
                foreach ($d['schedules'] as $s) {
                    if (str_contains($s['schedule'] ?? '', '03:00 PM')) {
                        $listBSlotIsCorrect = true;
                    }
                }
            }
        }
    }
}
check("Service B schedule list contains Sunday",           $listBHasSunday);
check("Service B Sunday slot is '03:00 PM - 06:00 PM'",  $listBSlotIsCorrect);

// ─── Step 6: Verify scheduleByDay (buyer-side lookup) is service-scoped ────
echo "\n--- Step 6: Buyer-side scheduleByDay scoped by service_id ---\n";

// Service A lookup — must return 10-12 slot only
// scheduleByDay reads service_id from request()->query(), so we bind the request
$reqA = Request::create(
    "/api/v1/service-list/service-schedule/Sun/{$provider->id}",
    'GET',
    ['service_id' => $serviceA->id]
);
app()->instance('request', $reqA);
request()->merge(['service_id' => $serviceA->id]);
$respA = $serviceController->scheduleByDay('Sun', $provider->id);
$schedA = json_decode($respA->getContent(), true);
$schedAHas1012 = false;
$schedAHas1518 = false;
if (isset($schedA['schedules'])) {
    foreach ($schedA['schedules'] as $s) {
        if (str_contains($s['schedule'] ?? '', '10:00 AM')) $schedAHas1012 = true;
        if (str_contains($s['schedule'] ?? '', '03:00 PM')) $schedAHas1518 = true;
    }
}
check("Buyer: Service A Sunday schedule contains 10:00 AM slot",    $schedAHas1012);
check("Buyer: Service A Sunday schedule does NOT contain 03:00 PM", !$schedAHas1518, "Service isolation breach!");

// Service B lookup — must return 15-18 slot only
$reqB = Request::create(
    "/api/v1/service-list/service-schedule/Sun/{$provider->id}",
    'GET',
    ['service_id' => $serviceB->id]
);
app()->instance('request', $reqB);
request()->merge(['service_id' => $serviceB->id]);
$respB = $serviceController->scheduleByDay('Sun', $provider->id);
$schedB = json_decode($respB->getContent(), true);
$schedBHas1518 = false;
$schedBHas1012 = false;
if (isset($schedB['schedules'])) {
    foreach ($schedB['schedules'] as $s) {
        if (str_contains($s['schedule'] ?? '', '03:00 PM')) $schedBHas1518 = true;
        if (str_contains($s['schedule'] ?? '', '10:00 AM')) $schedBHas1012 = true;
    }
}
check("Buyer: Service B Sunday schedule contains 03:00 PM slot",    $schedBHas1518);
check("Buyer: Service B Sunday schedule does NOT contain 10:00 AM", !$schedBHas1012, "Service isolation breach!");

// ─── Step 7: Book Service A (10:00 AM - 12:00 PM on Sunday) ───────────────
echo "\n--- Step 7: Buyer books Service A — Sun 10:00 AM - 12:00 PM ---\n";

Auth::guard('sanctum')->setUser($buyer);

$bookingDate = date('Y-m-d', strtotime('next Sunday'));

$orderAReq = Request::create('/api/v1/service/order', 'POST', [
    'service_id'              => $serviceA->id,
    'seller_id'               => $provider->id,
    'name'                    => $buyer->name,
    'email'                   => $buyer->email,
    'phone'                   => '+966500000001',
    'address'                 => 'Test Address, Riyadh',
    'date'                    => $bookingDate,
    'schedule'                => '10:00 AM - 12:00 PM',
    'is_service_online'       => '0',
    'selected_payment_gateway'=> 'cash_on_delivery',
]);
$respOrder = $serviceController->order($orderAReq);
$orderABody = json_decode($respOrder->getContent(), true);
$orderAStatus = $respOrder->getStatusCode();
check(
    "Service A booked successfully (status 200/201)",
    in_array($orderAStatus, [200, 201]),
    "HTTP $orderAStatus — " . json_encode($orderABody)
);

// Get the created order id for cleanup
$orderAId = Order::where('seller_id', $provider->id)
    ->where('service_id', $serviceA->id)
    ->where('schedule', '10:00 AM - 12:00 PM')
    ->latest()->first()?->id;

// ─── Step 8: Double-Booking Protection — Service B overlapping slot ─────────
echo "\n--- Step 8: Attempt to book Service B with overlapping time (10:30 AM - 11:30 AM) ---\n";
echo "    (Provider already booked for Service A 10:00 AM - 12:00 PM on same day)\n";

$orderBOverlapReq = Request::create('/api/v1/service/order', 'POST', [
    'service_id'              => $serviceB->id,
    'seller_id'               => $provider->id,
    'name'                    => $buyer->name,
    'email'                   => $buyer->email,
    'phone'                   => '+966500000001',
    'address'                 => 'Test Address, Riyadh',
    'date'                    => $bookingDate,
    'schedule'                => '10:30 AM - 11:30 AM',
    'is_service_online'       => '0',
    'selected_payment_gateway'=> 'cash_on_delivery',
]);
$respOverlap = $serviceController->order($orderBOverlapReq);
$overlapStatus = $respOverlap->getStatusCode();
$overlapBody   = json_decode($respOverlap->getContent(), true);
check(
    "Double-booking rejected: overlapping slot (10:30 AM - 11:30 AM) on Service B returns 422",
    $overlapStatus === 422,
    "HTTP $overlapStatus — " . json_encode($overlapBody)
);
check(
    "Rejection message is present",
    !empty($overlapBody['message'] ?? $overlapBody['error'] ?? ''),
    json_encode($overlapBody)
);

// ─── Step 9: Non-overlapping slot on Service B (15:00-18:00) must succeed ──
echo "\n--- Step 9: Book Service B with non-overlapping time (03:00 PM - 06:00 PM) ---\n";

$orderBValidReq = Request::create('/api/v1/service/order', 'POST', [
    'service_id'              => $serviceB->id,
    'seller_id'               => $provider->id,
    'name'                    => $buyer->name,
    'email'                   => $buyer->email,
    'phone'                   => '+966500000001',
    'address'                 => 'Test Address, Riyadh',
    'date'                    => $bookingDate,
    'schedule'                => '03:00 PM - 06:00 PM',
    'is_service_online'       => '0',
    'selected_payment_gateway'=> 'cash_on_delivery',
]);
$respBValid = $serviceController->order($orderBValidReq);
$orderBStatus = $respBValid->getStatusCode();
$orderBBody   = json_decode($respBValid->getContent(), true);
check(
    "Service B booked successfully with non-overlapping slot (status 200/201)",
    in_array($orderBStatus, [200, 201]),
    "HTTP $orderBStatus — " . json_encode($orderBBody)
);

// ─── Step 10: Verify days are truly DB-isolated ────────────────────────────
echo "\n--- Step 10: DB-level isolation verification ---\n";

$daysForA = Day::where('seller_id', $provider->id)
    ->where('service_id', $serviceA->id)
    ->count();
$daysForB = Day::where('seller_id', $provider->id)
    ->where('service_id', $serviceB->id)
    ->count();
$daysNoService = Day::where('seller_id', $provider->id)
    ->whereNull('service_id')
    ->count();

check("DB: Service A has exactly 1 day record", $daysForA === 1, "actual=$daysForA");
check("DB: Service B has exactly 1 day record", $daysForB === 1, "actual=$daysForB");
check("DB: No seller-level (null service_id) day records created", $daysNoService === 0, "actual=$daysNoService");

$schedulesForA = Schedule::whereHas('days', fn($q) => $q->where('service_id', $serviceA->id))
    ->where('service_id', $serviceA->id)
    ->count();
$schedulesForB = Schedule::whereHas('days', fn($q) => $q->where('service_id', $serviceB->id))
    ->where('service_id', $serviceB->id)
    ->count();

check("DB: Service A has exactly 1 schedule slot", $schedulesForA === 1, "actual=$schedulesForA");
check("DB: Service B has exactly 1 schedule slot", $schedulesForB === 1, "actual=$schedulesForB");

// ─── Teardown ───────────────────────────────────────────────────────────────
echo "\n--- Cleanup: removing test data ---\n";

Order::where('seller_id', $provider->id)->delete();
Schedule::where('service_id', $serviceA->id)->orWhere('service_id', $serviceB->id)->delete();
Day::where('service_id', $serviceA->id)->orWhere('service_id', $serviceB->id)->delete();
$serviceA->delete();
$serviceB->delete();
$provider->delete();
$buyer->delete();
echo "Test data cleaned up.\n";

// ─── Results ────────────────────────────────────────────────────────────────
echo "\n=================================================================\n";
echo "RESULTS: {$passCount} PASSED, {$failCount} FAILED\n";
echo "=================================================================\n";

if ($failCount > 0) {
    echo "\nFailed checks:\n";
    foreach ($errors as $e) {
        echo "  - $e\n";
    }
    exit(1);
}

echo "\n✅  All service-specific availability isolation tests PASSED.\n";
exit(0);
