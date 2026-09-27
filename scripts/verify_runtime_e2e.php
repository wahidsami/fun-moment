<?php

require __DIR__ . '/../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../backend/bootstrap/app.php';

$initReq = \Illuminate\Http\Request::create('/');
$app->instance('request', $initReq);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

echo "=======================================================\n";
echo "FUN MOMENT — COMPLETE HTTP RUNTIME VERIFICATION SUITE\n";
echo "=======================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest($description, $condition, $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] " . $description . "\n";
    } else {
        $failCount++;
        echo " [FAIL] " . $description . "\n";
        if ($details) {
            echo "        Details: " . $details . "\n";
        }
    }
}

$admin = \App\Admin::first();
if (!$admin) {
    die("No admin found in database\n");
}
\Illuminate\Support\Facades\Auth::guard('admin')->login($admin);

function sendRequest($kernel, $app, $method, $uri, $params = [], $content = null) {
    $server = [
        'HTTP_ACCEPT' => 'application/json',
        'REMOTE_ADDR' => '127.0.0.1',
    ];
    $req = \Illuminate\Http\Request::create($uri, $method, $params, [], [], $server, $content);
    $app->instance('request', $req);
    $req->setUserResolver(function() {
        return \App\Admin::first();
    });
    \Illuminate\Support\Facades\Auth::guard('admin')->login(\App\Admin::first());
    return $kernel->handle($req);
}

// -------------------------------------------------------------
// Test 1: Frontend Users
// -------------------------------------------------------------
$res = sendRequest($kernel, $app, 'GET', '/admin-home/frontend-users');
assertTest(
    "GET /admin-home/frontend-users returns 200 without Wallet crash",
    $res->getStatusCode() === 200,
    "Status: " . $res->getStatusCode()
);
$usersData = json_decode($res->getContent(), true);
assertTest(
    "Users payload contains valid user list",
    is_array($usersData) && count($usersData) > 0,
    "Count: " . (is_array($usersData) ? count($usersData) : 0)
);

// -------------------------------------------------------------
// Test 2: Seller Verification Details & Action
// -------------------------------------------------------------
$seller = \App\User::where('user_type', 0)->first();
if ($seller) {
    $res = sendRequest($kernel, $app, 'GET', '/admin-home/frontend-users/' . $seller->id . '/verification');
    assertTest(
        "GET /admin-home/frontend-users/{id}/verification returns 200 with details",
        $res->getStatusCode() === 200,
        "Status: " . $res->getStatusCode()
    );
    $verData = json_decode($res->getContent(), true);
    assertTest(
        "Verification details contain seller info",
        isset($verData['verification']['seller_id']) && $verData['verification']['seller_id'] == $seller->id,
        "Payload: " . substr($res->getContent(), 0, 150)
    );

    // Verify Seller POST mutation
    $res = sendRequest($kernel, $app, 'POST', '/admin-home/frontend-users/verify/' . $seller->id, ['status' => 1]);
    $dbStatus = \App\SellerVerify::where('seller_id', $seller->id)->value('status');
    assertTest(
        "POST /admin-home/frontend-users/verify/{id} persists approved status",
        $res->getStatusCode() === 200 && (int)$dbStatus === 1,
        "Status in DB: " . var_export($dbStatus, true)
    );
}

// -------------------------------------------------------------
// Test 3: Categories 3-Level Hierarchy & CRUD
// -------------------------------------------------------------
$res = sendRequest($kernel, $app, 'GET', '/admin-home/categories-json');
assertTest(
    "GET /admin-home/categories-json returns 200 with hierarchy tree",
    $res->getStatusCode() === 200
);
$catsPayload = json_decode($res->getContent(), true);
$cats = $catsPayload['categories'] ?? [];
assertTest(
    "Categories tree contains parent nodes with subcategories",
    is_array($cats) && count($cats) > 0 && isset($cats[0]['subcategories'])
);

// Create Category
$randSlug = 'test-cat-' . time();
$res = sendRequest($kernel, $app, 'POST', '/admin-home/categories-json', [
    'level' => 'parent',
    'name_en' => 'Test HTTP Suite',
    'name_ar' => 'تصنيف اختبار عبر HTTP',
    'slug' => $randSlug
]);
$createdCatInDb = \App\Category::where('slug', $randSlug)->first();
assertTest(
    "POST /admin-home/categories-json creates Category in PostgreSQL",
    $res->getStatusCode() === 200 && $createdCatInDb !== null,
    "Status: " . $res->getStatusCode()
);

if ($createdCatInDb) {
    // Status update
    $res = sendRequest($kernel, $app, 'POST', '/admin-home/categories-json/parent/' . $createdCatInDb->id . '/status', [
        'status' => 'inactive'
    ]);
    $updatedStatus = \App\Category::find($createdCatInDb->id)->status;
    assertTest(
        "POST /admin-home/categories-json/{level}/{id}/status updates status in PostgreSQL",
        $res->getStatusCode() === 200 && (int)$updatedStatus === 0,
        "Status in DB: " . var_export($updatedStatus, true)
    );

    // Delete
    $res = sendRequest($kernel, $app, 'POST', '/admin-home/categories-json/parent/' . $createdCatInDb->id . '/delete');
    assertTest(
        "POST /admin-home/categories-json/{level}/{id}/delete removes row from PostgreSQL",
        $res->getStatusCode() === 200 && !\App\Category::where('slug', $randSlug)->exists()
    );
}

// -------------------------------------------------------------
// Test 4: Geographies & Locations
// -------------------------------------------------------------
$res = sendRequest($kernel, $app, 'GET', '/admin-home/locations-json');
assertTest(
    "GET /admin-home/locations-json returns countries, cities, areas",
    $res->getStatusCode() === 200
);
$locData = json_decode($res->getContent(), true);
assertTest(
    "Locations payload has countries, cities, and areas arrays",
    isset($locData['countries']) && isset($locData['cities']) && isset($locData['areas'])
);

// Create Area
$firstCity = \App\ServiceCity::first();
if ($firstCity) {
    $areaName = 'Test District ' . time();
    $res = sendRequest($kernel, $app, 'POST', '/admin-home/locations-json', [
        'level' => 'area',
        'name_en' => $areaName,
        'name_ar' => 'حي تجريبي',
        'city_id' => $firstCity->id
    ]);
    $areaInDb = \App\ServiceArea::where('service_area', $areaName)->first();
    assertTest(
        "POST /admin-home/locations-json creates ServiceArea in PostgreSQL",
        $res->getStatusCode() === 200 && $areaInDb !== null
    );
    if ($areaInDb) {
        $delRes = sendRequest($kernel, $app, 'POST', '/admin-home/locations-json/area/' . $areaInDb->id . '/delete');
        assertTest(
            "POST /admin-home/locations-json/{level}/{id}/delete removes area from PostgreSQL",
            $delRes->getStatusCode() === 200 && !\App\ServiceArea::where('service_area', $areaName)->exists()
        );
    }
}

// -------------------------------------------------------------
// Test 5: Payout Requests
// -------------------------------------------------------------
$res = sendRequest($kernel, $app, 'GET', '/admin-home/payouts-json');
assertTest(
    "GET /admin-home/payouts-json returns 200 with requests list",
    $res->getStatusCode() === 200
);
$payoutsData = json_decode($res->getContent(), true);
assertTest(
    "Payouts response has payouts and summary",
    isset($payoutsData['payouts']) && isset($payoutsData['summary'])
);

$firstPayout = \App\PayoutRequest::first();
if ($firstPayout) {
    $res = sendRequest($kernel, $app, 'POST', '/admin-home/payouts-json/' . $firstPayout->id . '/status', [
        'status' => 'completed',
        'admin_note' => 'Verified via automated HTTP test'
    ]);
    assertTest(
        "POST /admin-home/payouts-json/{id}/status persists status in PostgreSQL",
        $res->getStatusCode() === 200 && (int)\App\PayoutRequest::find($firstPayout->id)->status === 1
    );
}

// -------------------------------------------------------------
// Test 6: Support Tickets Thread & Reply
// -------------------------------------------------------------
$ticket = \App\SupportTicket::first();
if ($ticket) {
    $res = sendRequest($kernel, $app, 'GET', '/admin-home/support-tickets-json/' . $ticket->id);
    assertTest(
        "GET /admin-home/support-tickets-json/{id} returns ticket and message history",
        $res->getStatusCode() === 200
    );
    $tktData = json_decode($res->getContent(), true);
    assertTest(
        "Ticket details contain ticket record and messages array",
        isset($tktData['ticket']) && isset($tktData['messages'])
    );

    // Reply to ticket
    $testReplyMsg = "Automated support resolution message test #" . time();
    $res = sendRequest($kernel, $app, 'POST', '/admin-home/support-tickets-json/' . $ticket->id . '/reply', [
        'message' => $testReplyMsg
    ]);
    assertTest(
        "POST /admin-home/support-tickets-json/{id}/reply persists message in support_ticket_messages",
        $res->getStatusCode() === 200 && \App\SupportTicketMessage::where('message', $testReplyMsg)->exists()
    );
}

// -------------------------------------------------------------
// Test 7: Payment Gateway Administration (PayTabs)
// -------------------------------------------------------------
$res = sendRequest($kernel, $app, 'GET', '/admin-home/payment-gateways-json');
assertTest(
    "GET /admin-home/payment-gateways-json returns masked configuration",
    $res->getStatusCode() === 200
);
$gwData = json_decode($res->getContent(), true);
assertTest(
    "PayTabs configuration is present and server key is masked",
    isset($gwData['gateways']['paytabs']) &&
    strpos($gwData['gateways']['paytabs']['masked_server_key'], '••••') !== false
);

// Update Gateway
$res = sendRequest($kernel, $app, 'POST', '/admin-home/payment-gateways-json', [
    'paytabs' => [
        'enabled' => true,
        'profile_id' => '123456',
        'region' => 'SAU',
        'test_mode' => true,
        'server_key' => 'secret_server_key_test_9999'
    ]
]);
assertTest(
    "POST /admin-home/payment-gateways-json persists settings in static_options",
    $res->getStatusCode() === 200 && get_static_option('paytabs_profile_id') === '123456'
);

// -------------------------------------------------------------
// Test 8: Immutable Audit Logs
// -------------------------------------------------------------
$res = sendRequest($kernel, $app, 'GET', '/admin-home/audit-logs-json');
assertTest(
    "GET /admin-home/audit-logs-json returns 200 with immutable log records",
    $res->getStatusCode() === 200
);
$auditData = json_decode($res->getContent(), true);
assertTest(
    "Immutable audit logs array contains persisted records from PostgreSQL",
    isset($auditData['logs']) && count($auditData['logs']) > 0
);

// -------------------------------------------------------------
// Test 9: Flutter / Mobile Customer Marketplace Endpoints
// -------------------------------------------------------------
$res = sendRequest($kernel, $app, 'GET', '/api/v1/service-list/all-services');
assertTest(
    "GET /api/v1/service-list/all-services (Mobile Catalog) returns HTTP 200/201",
    in_array($res->getStatusCode(), [200, 201]),
    "Status: " . $res->getStatusCode()
);

$res = sendRequest($kernel, $app, 'GET', '/api/v1/category');
assertTest(
    "GET /api/v1/category (Mobile Categories) returns HTTP 200/201",
    in_array($res->getStatusCode(), [200, 201]),
    "Status: " . $res->getStatusCode()
);

echo "\n-------------------------------------------------------\n";
echo "SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "=======================================================\n";
exit($failCount > 0 ? 1 : 0);
