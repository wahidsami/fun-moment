<?php

namespace App\Http\Controllers;

use App\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\LiveChat\Entities\LiveChatConversation;
use Modules\LiveChat\Services\LiveChatService;

class AdminChatHubApiController extends Controller
{
    protected LiveChatService $chatService;

    public function __construct(LiveChatService $chatService)
    {
        $this->middleware('auth:admin');
        $this->chatService = $chatService;
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $data = $this->chatService->getAdminConversationsOverview($search, $status);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ], 200);
    }

    public function show(int $id): JsonResponse
    {
        try {
            $data = $this->chatService->getAdminConversationDetails($id);

            return response()->json([
                'status' => 'success',
                'data' => $data,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Conversation not found',
            ], 404);
        }
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:active,archived',
        ]);

        $conversation = LiveChatConversation::findOrFail($id);
        $oldStatus = (int) $conversation->status === 1 ? 'active' : 'archived';
        $newStatusVal = $request->status === 'active' ? 1 : 0;

        $conversation->status = $newStatusVal;
        $conversation->save();

        AdminAuditLog::record([
            'admin_id' => Auth::guard('admin')->id(),
            'action' => 'chat_moderation',
            'resource_type' => 'live_chat_conversation',
            'resource_id' => (string) $conversation->id,
            'details_en' => "Moderated conversation #{$id} status changed from {$oldStatus} to {$request->status}",
            'details_ar' => "تعديل حالة المحادثة #{$id} من {$oldStatus} إلى {$request->status}",
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $request->status],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Conversation status updated to {$request->status}",
            'data' => [
                'id' => $conversation->id,
                'status' => $request->status,
            ],
        ], 200);
    }
}
