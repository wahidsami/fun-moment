<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\LiveChat\Services\LiveChatService;

class SellerChatController extends Controller
{
    protected LiveChatService $chatService;

    public function __construct(LiveChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    public function liveChat()
    {
        $sellerId = auth('sanctum')->id();
        if (!$sellerId) {
            return response()->json(['msg' => __('Unauthorized')], 401);
        }

        $contacts = $this->chatService->getSellerContacts($sellerId);

        if (!empty($contacts['chat_buyer_lists'])) {
            return response()->success($contacts);
        }

        return response()->success([
            'chat_buyer_lists' => [],
            'buyer_image' => [],
            'msg' => __('No Contacts Yet'),
        ]);
    }

    public function postSendMessage(Request $request)
    {
        $request->validate([
            'to_user' => 'required|integer|exists:users,id',
            'message' => 'nullable|string|max:5000',
            'image' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $seller = auth('sanctum')->user();
        if (!$seller) {
            return response()->json(['msg' => __('Unauthorized')], 401);
        }

        $filename = null;
        if ($request->hasFile('image')) {
            $filename = $this->uploadImage($request);
        }

        try {
            $message = $this->chatService->sendMessage(
                (int) $seller->id,
                (int) $request->to_user,
                $request->message,
                $filename
            );

            $profileImage = render_image_markup_by_attachment_id(optional($seller)->image);
            $allArray = $message->toArray() + ['profile_image' => $profileImage];

            return response()->json([
                'state' => 1,
                'message' => $allArray,
                'from_user' => (int) $seller->id,
                'to_user' => (int) $request->to_user,
                'message_text' => $request->message,
                'image_url' => $message->image_url,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'state' => 0,
                'msg' => $e->getMessage(),
            ], 422);
        }
    }

    public function allMessages(Request $request)
    {
        $request->validate([
            'to_user' => 'required|integer|exists:users,id',
        ]);

        $userId = auth('sanctum')->id();
        if (!$userId) {
            return response()->json(['msg' => __('Unauthorized')], 401);
        }

        $messages = $this->chatService->getConversationMessages($userId, (int) $request->to_user, 16);

        return response()->json([
            'messages' => $messages,
        ], 200);
    }

    private function uploadImage(Request $request): ?string
    {
        $file = $request->file('image');
        $filename = md5(uniqid()) . '.' . $file->getClientOriginalExtension();
        $targetDir = public_path('assets/uploads/ticket');
        if (!file_exists($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        $file->move($targetDir, $filename);
        return $filename;
    }
}
