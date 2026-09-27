<?php

require_once __DIR__ . '/../backend/vendor/autoload.php';

$app = require_once __DIR__ . '/../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Admin;
use App\AdminAuditLog;
use App\User;
use Illuminate\Support\Facades\DB;
use Modules\LiveChat\Entities\LiveChatConversation;
use Modules\LiveChat\Entities\LiveChatMessage;
use Modules\LiveChat\Services\LiveChatService;

echo "========================================================\n";
echo "  FUN MOMENT — LIVE CHAT MODULE END-TO-END VERIFICATION  \n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest($condition, $description) {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] $description\n";
        $passed++;
    } else {
        echo " [FAIL] $description\n";
        $failed++;
    }
}

try {
    // 1. Database Schema
    echo "\n--- 1. DATABASE SCHEMA & CONSTRAINTS ---\n";
    $convTableExists = DB::getSchemaBuilder()->hasTable('live_chat_conversations');
    assertTest($convTableExists, "Table 'live_chat_conversations' exists in PostgreSQL");

    $msgTableExists = DB::getSchemaBuilder()->hasTable('live_chat_messages');
    assertTest($msgTableExists, "Table 'live_chat_messages' exists in PostgreSQL");

    // 2. Initialize Users
    echo "\n--- 2. INITIALIZE CHAT PARTICIPANTS ---\n";
    $testBuyer = User::firstOrCreate(
        ['email' => 'chat_test_buyer@funmoment.test'],
        ['name' => 'Chat Buyer User', 'username' => 'chat_buyer', 'password' => bcrypt('password123'), 'user_type' => 1]
    );
    assertTest($testBuyer->id > 0, "Buyer user initialized (ID: {$testBuyer->id})");

    $testSeller = User::firstOrCreate(
        ['email' => 'chat_test_seller@funmoment.test'],
        ['name' => 'Chat Seller Provider', 'username' => 'chat_seller', 'password' => bcrypt('password123'), 'user_type' => 0]
    );
    assertTest($testSeller->id > 0, "Seller user initialized (ID: {$testSeller->id})");

    $testAdmin = Admin::first();
    if (!$testAdmin) {
        $testAdmin = Admin::create([
            'name' => 'Chat Admin',
            'username' => 'chat_admin',
            'email' => 'chat_admin@funmoment.test',
            'password' => bcrypt('password123'),
        ]);
    }
    assertTest($testAdmin->id > 0, "Admin user present (ID: {$testAdmin->id})");

    // Clean previous test chat data
    LiveChatMessage::whereIn('from_user', [$testBuyer->id, $testSeller->id])
        ->orWhereIn('to_user', [$testBuyer->id, $testSeller->id])
        ->delete();
    LiveChatConversation::where('buyer_id', $testBuyer->id)
        ->where('seller_id', $testSeller->id)
        ->delete();

    $chatService = app(LiveChatService::class);

    // 3. Buyer sends message to Seller
    echo "\n--- 3. BUYER SENDS MESSAGE TO SELLER ---\n";
    $rawMessage = "Hello! Is your wedding venue service available this Friday? <script>alert('xss')</script>";
    $msg1 = $chatService->sendMessage(
        $testBuyer->id,
        $testSeller->id,
        $rawMessage
    );

    assertTest($msg1->id > 0, "Message #1 persisted with ID {$msg1->id}");
    assertTest(!str_contains($msg1->message, '<script>'), "Malicious XSS script tags stripped from message");
    assertTest(str_contains($msg1->message, 'Hello! Is your wedding venue service available'), "Message content intact");
    assertTest((int)$msg1->from_user === (int)$testBuyer->id, "Message from_user matches buyer ID");
    assertTest((int)$msg1->to_user === (int)$testSeller->id, "Message to_user matches seller ID");
    assertTest($msg1->is_read === false, "Message is initially unread");

    // Verify conversation
    $conversation = LiveChatConversation::where('buyer_id', $testBuyer->id)
        ->where('seller_id', $testSeller->id)
        ->first();
    assertTest($conversation !== null, "Conversation record auto-created");
    assertTest((int)$conversation->seller_unread_count === 1, "Seller unread count incremented to 1");
    assertTest((int)$conversation->buyer_unread_count === 0, "Buyer unread count is 0");
    assertTest($conversation->last_message_at !== null, "Conversation last_message_at updated");

    // 4. Seller receives & inspects contacts
    echo "\n--- 4. SELLER RECEIVES CONTACTS & READS MESSAGES ---\n";
    $sellerContacts = $chatService->getSellerContacts($testSeller->id);
    assertTest(!empty($sellerContacts['chat_buyer_lists']), "Seller contacts list contains buyers");
    assertTest((int)$sellerContacts['chat_buyer_lists'][0]['unread_count'] === 1, "Contact shows 1 unread message");

    // Seller opens conversation (marks read)
    $sellerMsgs = $chatService->getConversationMessages($testSeller->id, $testBuyer->id, 16);
    assertTest($sellerMsgs->total() === 1, "Seller retrieves exactly 1 message in conversation");

    $conversation->refresh();
    assertTest((int)$conversation->seller_unread_count === 0, "Seller unread count reset to 0 after reading");

    $msg1Fresh = LiveChatMessage::find($msg1->id);
    assertTest($msg1Fresh->is_read === true, "Message marked as read (is_read = true)");
    assertTest($msg1Fresh->read_at !== null, "Message read_at timestamp recorded");

    // 5. Seller replies to Buyer
    echo "\n--- 5. SELLER REPLIES TO BUYER ---\n";
    $replyText = "Yes, absolutely! We have open slots on Friday evening.";
    $msg2 = $chatService->sendMessage(
        $testSeller->id,
        $testBuyer->id,
        $replyText
    );

    assertTest($msg2->id > 0, "Reply message persisted with ID {$msg2->id}");
    assertTest((int)$msg2->from_user === (int)$testSeller->id, "Reply from_user matches seller ID");
    assertTest((int)$msg2->to_user === (int)$testBuyer->id, "Reply to_user matches buyer ID");

    $conversation->refresh();
    assertTest((int)$conversation->buyer_unread_count === 1, "Buyer unread count incremented to 1");
    assertTest((int)$conversation->seller_unread_count === 0, "Seller unread count remains 0");

    // 6. Buyer receives reply
    echo "\n--- 6. BUYER RECEIVES REPLY ---\n";
    $buyerContacts = $chatService->getBuyerContacts($testBuyer->id);
    assertTest(!empty($buyerContacts['chat_seller_lists']), "Buyer contacts list contains sellers");
    assertTest((int)$buyerContacts['chat_seller_lists'][0]['unread_count'] === 1, "Buyer contact shows 1 unread message");

    $buyerMsgs = $chatService->getConversationMessages($testBuyer->id, $testSeller->id, 16);
    assertTest($buyerMsgs->total() === 2, "Buyer retrieves 2 messages total in conversation");

    $conversation->refresh();
    assertTest((int)$conversation->buyer_unread_count === 0, "Buyer unread count reset to 0 after reading");

    // 7. Admin Chat Hub Control Plane
    echo "\n--- 7. ADMIN CHAT HUB CONTROL PLANE ---\n";
    $adminOverview = $chatService->getAdminConversationsOverview();
    assertTest(isset($adminOverview['summary']['total_conversations']), "Admin summary contains total_conversations KPI");
    assertTest($adminOverview['summary']['total_messages'] >= 2, "Admin summary shows total messages >= 2");
    assertTest(count($adminOverview['conversations']) >= 1, "Admin overview lists active conversations");

    $adminTranscript = $chatService->getAdminConversationDetails($conversation->id);
    assertTest($adminTranscript['conversation']['id'] === (int)$conversation->id, "Admin retrieves conversation metadata");
    assertTest(count($adminTranscript['messages']) === 2, "Admin transcript contains all 2 exchanged messages");
    assertTest($adminTranscript['messages'][0]['sender_role'] === 'buyer', "First message attributed to Buyer");
    assertTest($adminTranscript['messages'][1]['sender_role'] === 'seller', "Second message attributed to Seller");

    // Admin Controller HTTP Endpoints
    $adminController = app(\App\Http\Controllers\AdminChatHubApiController::class);
    $adminReq = \Illuminate\Http\Request::create('/admin-home/chat-hub-json', 'GET');
    $httpResp = $adminController->index($adminReq);
    assertTest($httpResp->getStatusCode() === 200, "Admin Chat Hub HTTP index returns 200");

    $detailReq = \Illuminate\Http\Request::create("/admin-home/chat-hub-json/{$conversation->id}", 'GET');
    $detailResp = $adminController->show($conversation->id);
    assertTest($detailResp->getStatusCode() === 200, "Admin Chat Hub HTTP detail returns 200");

    // Admin Moderation: Archive conversation
    \Illuminate\Support\Facades\Auth::guard('admin')->setUser($testAdmin);
    $statusReq = \Illuminate\Http\Request::create("/admin-home/chat-hub-json/{$conversation->id}/status", 'POST', ['status' => 'archived']);
    $statusResp = $adminController->updateStatus($statusReq, $conversation->id);
    assertTest($statusResp->getStatusCode() === 200, "Admin moderation updateStatus returns 200");
    assertTest((int)$conversation->fresh()->status === 0, "Conversation status successfully archived");

    // Restore to active
    $restoreReq = \Illuminate\Http\Request::create("/admin-home/chat-hub-json/{$conversation->id}/status", 'POST', ['status' => 'active']);
    $restoreResp = $adminController->updateStatus($restoreReq, $conversation->id);
    assertTest((int)$conversation->fresh()->status === 1, "Conversation status restored to active");

    // 8. Mobile API HTTP Controllers
    echo "\n--- 8. MOBILE API HTTP CONTROLLERS ---\n";
    $buyerController = app(\App\Http\Controllers\Api\BuyerChatController::class);
    $sellerController = app(\App\Http\Controllers\Api\SellerChatController::class);

    // Mock Sanctum Auth for Buyer
    auth('sanctum')->setUser($testBuyer);
    $buyerListResp = $buyerController->liveChat();
    assertTest($buyerListResp->getStatusCode() === 201, "Buyer liveChat contacts API returns 201");

    $buyerAllMsgReq = \Illuminate\Http\Request::create('/api/v1/user/chat/all-messages', 'GET', ['to_user' => $testSeller->id]);
    $buyerAllMsgResp = $buyerController->allMessages($buyerAllMsgReq);
    assertTest($buyerAllMsgResp->getStatusCode() === 200, "Buyer all-messages API returns 200");

    // Mock Sanctum Auth for Seller
    auth('sanctum')->setUser($testSeller);
    $sellerListResp = $sellerController->liveChat();
    assertTest($sellerListResp->getStatusCode() === 201, "Seller liveChat contacts API returns 201");

    $sellerAllMsgReq = \Illuminate\Http\Request::create('/api/v1/seller/chat/all-messages', 'GET', ['to_user' => $testBuyer->id]);
    $sellerAllMsgResp = $sellerController->allMessages($sellerAllMsgReq);
    assertTest($sellerAllMsgResp->getStatusCode() === 200, "Seller all-messages API returns 200");

} catch (\Throwable $e) {
    echo "\n[ERROR EXCEPTION] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $failed++;
}

echo "\n========================================================\n";
echo "   LIVE CHAT TEST RESULTS: Passed: $passed, Failed: $failed\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
