<?php

namespace Modules\LiveChat\Services;

use App\AdminAuditLog;
use App\Events\MessageSent;
use App\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\LiveChat\Entities\LiveChatConversation;
use Modules\LiveChat\Entities\LiveChatMessage;

class LiveChatService
{
    /**
     * Send a real-time message between participants.
     *
     * @param int $senderId
     * @param int $recipientId
     * @param string|null $messageText
     * @param string|null $imagePath
     * @return LiveChatMessage
     * @throws \Exception
     */
    public function sendMessage(
        int $senderId,
        int $recipientId,
        ?string $messageText,
        ?string $imagePath = null
    ): LiveChatMessage {
        $sender = User::findOrFail($senderId);
        $recipient = User::findOrFail($recipientId);

        if (empty(trim((string) $messageText)) && empty($imagePath)) {
            throw new \InvalidArgumentException("Message body or attachment is required.");
        }

        // Determine buyer and seller
        if ((int) $sender->user_type === 1 && (int) $recipient->user_type === 0) {
            $buyerId = $senderId;
            $sellerId = $recipientId;
        } elseif ((int) $sender->user_type === 0 && (int) $recipient->user_type === 1) {
            $sellerId = $senderId;
            $buyerId = $recipientId;
        } else {
            // Default role mapping
            $buyerId = (int) $sender->user_type === 1 ? $senderId : $recipientId;
            $sellerId = (int) $sender->user_type === 0 ? $senderId : $recipientId;
        }

        return DB::transaction(function () use ($sender, $recipient, $senderId, $recipientId, $buyerId, $sellerId, $messageText, $imagePath) {
            // Locate or create conversation
            $conversation = LiveChatConversation::getOrCreate($buyerId, $sellerId);

            $cleanMessage = $messageText ? strip_tags(trim($messageText)) : null;

            $message = LiveChatMessage::create([
                'conversation_id' => $conversation->id,
                'from_user' => $senderId,
                'to_user' => $recipientId,
                'buyer_id' => $buyerId,
                'seller_id' => $sellerId,
                'message' => $cleanMessage,
                'image' => $imagePath,
                'attachment_type' => $imagePath ? 'image' : null,
                'is_read' => false,
            ]);

            // Update conversation metadata & unread counters
            $preview = $cleanMessage ?: '[Image attachment]';
            $conversation->last_message = mb_substr($preview, 0, 255);
            $conversation->last_message_at = now();

            if ($recipientId === $buyerId) {
                $conversation->increment('buyer_unread_count');
            } else {
                $conversation->increment('seller_unread_count');
            }
            $conversation->save();

            // Load relations for response & broadcast
            $message->load(['fromUser', 'toUser']);

            // Safely attempt broadcasting
            $this->safelyBroadcast($message);

            return $message;
        });
    }

    /**
     * Broadcast message to recipient with error tolerance.
     */
    protected function safelyBroadcast(LiveChatMessage $message): void
    {
        try {
            $pusherKey = get_static_option('pusher_app_key') ?: env('PUSHER_APP_KEY');
            if (!empty($pusherKey)) {
                event(new MessageSent($message));
            }
        } catch (\Throwable $e) {
            Log::warning("Pusher broadcast skipped/failed: " . $e->getMessage());
        }
    }

    /**
     * Get paginated conversation messages between two users.
     */
    public function getConversationMessages(int $userId1, int $userId2, int $perPage = 16)
    {
        // Auto mark received messages as read
        $this->markAsRead($userId1, $userId2);

        return LiveChatMessage::where(function ($q) use ($userId1, $userId2) {
            $q->where('from_user', $userId1)->where('to_user', $userId2);
        })->orWhere(function ($q) use ($userId1, $userId2) {
            $q->where('from_user', $userId2)->where('to_user', $userId1);
        })
        ->with(['fromUser', 'toUser'])
        ->orderBy('created_at', 'desc')
        ->paginate($perPage)
        ->withQueryString();
    }

    /**
     * Mark all unread messages from a sender as read.
     */
    public function markAsRead(int $currentUserId, int $senderId): int
    {
        $updated = LiveChatMessage::where('to_user', $currentUserId)
            ->where('from_user', $senderId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        // Reset conversation unread counter
        $conversation = LiveChatConversation::where(function ($q) use ($currentUserId, $senderId) {
            $q->where('buyer_id', $currentUserId)->where('seller_id', $senderId);
        })->orWhere(function ($q) use ($currentUserId, $senderId) {
            $q->where('buyer_id', $senderId)->where('seller_id', $currentUserId);
        })->first();

        if ($conversation) {
            if ($currentUserId === (int) $conversation->buyer_id) {
                $conversation->update(['buyer_unread_count' => 0]);
            } else {
                $conversation->update(['seller_unread_count' => 0]);
            }
        }

        return $updated;
    }

    /**
     * Retrieve contacts for a Buyer (returns list of Sellers they have chatted with).
     */
    public function getBuyerContacts(int $buyerId): array
    {
        $conversations = LiveChatConversation::where('buyer_id', $buyerId)
            ->with(['seller'])
            ->orderByDesc('last_message_at')
            ->get();

        $sellerLists = [];
        $sellerImages = [];

        foreach ($conversations as $c) {
            $seller = $c->seller;
            if (!$seller) {
                continue;
            }

            $imgData = !empty($seller->image) ? get_attachment_image_by_id($seller->image) : null;
            $imgUrl = $imgData['img_url'] ?? null;

            $sellerLists[] = [
                'seller_id' => (int) $seller->id,
                'seller_list' => $seller->toArray(),
                'date_time_str' => $c->last_message_at ? $c->last_message_at->format('h:i A') : '',
                'date_human_readable' => $c->last_message_at ? $c->last_message_at->diffForHumans() : '',
                'image_url' => $imgUrl ?: '',
                'sender_profile_image' => $imgUrl ?: '',
                'unread_count' => (int) $c->buyer_unread_count,
                'last_message' => (string) $c->last_message,
            ];

            $sellerImages[] = $imgUrl ? ['image_url' => $imgUrl] : [];
        }

        return [
            'chat_seller_lists' => $sellerLists,
            'seller_image' => $sellerImages,
        ];
    }

    /**
     * Retrieve contacts for a Seller (returns list of Buyers who messaged them).
     */
    public function getSellerContacts(int $sellerId): array
    {
        $conversations = LiveChatConversation::where('seller_id', $sellerId)
            ->with(['buyer'])
            ->orderByDesc('last_message_at')
            ->get();

        $buyerLists = [];
        $buyerImages = [];

        foreach ($conversations as $c) {
            $buyer = $c->buyer;
            if (!$buyer) {
                continue;
            }

            $imgData = !empty($buyer->image) ? get_attachment_image_by_id($buyer->image) : null;
            $imgUrl = $imgData['img_url'] ?? null;

            $buyerLists[] = [
                'buyer_id' => (int) $buyer->id,
                'buyer_list' => $buyer->toArray(),
                'date_time_str' => $c->last_message_at ? $c->last_message_at->format('h:i A') : '',
                'date_human_readable' => $c->last_message_at ? $c->last_message_at->diffForHumans() : '',
                'image_url' => $imgUrl ?: '',
                'sender_profile_image' => $imgUrl ?: '',
                'unread_count' => (int) $c->seller_unread_count,
                'last_message' => (string) $c->last_message,
            ];

            $buyerImages[] = $imgUrl ? ['image_url' => $imgUrl] : [];
        }

        return [
            'chat_buyer_lists' => $buyerLists,
            'buyer_image' => $buyerImages,
        ];
    }

    /**
     * Admin Chat Hub: Overview of conversations across the entire marketplace.
     */
    public function getAdminConversationsOverview(?string $search = null, ?string $status = null): array
    {
        $query = LiveChatConversation::with(['buyer', 'seller']);

        if (!empty($search)) {
            $s = trim($search);
            $query->where(function ($q) use ($s) {
                $q->whereHas('buyer', function ($bq) use ($s) {
                    $bq->where('name', 'ilike', "%{$s}%")
                        ->orWhere('email', 'ilike', "%{$s}%");
                })->orWhereHas('seller', function ($sq) use ($s) {
                    $sq->where('name', 'ilike', "%{$s}%")
                        ->orWhere('email', 'ilike', "%{$s}%");
                });
            });
        }

        if ($status !== null && $status !== 'all') {
            $query->where('status', (int) $status);
        }

        $conversations = $query->orderByDesc('last_message_at')->get()->map(function (LiveChatConversation $c) {
            $totalMsgs = LiveChatMessage::where('conversation_id', $c->id)->count();

            return [
                'id' => (int) $c->id,
                'buyer' => [
                    'id' => optional($c->buyer)->id,
                    'name' => optional($c->buyer)->name ?? 'Buyer #' . $c->buyer_id,
                    'email' => optional($c->buyer)->email,
                ],
                'seller' => [
                    'id' => optional($c->seller)->id,
                    'name' => optional($c->seller)->name ?? 'Seller #' . $c->seller_id,
                    'email' => optional($c->seller)->email,
                ],
                'last_message' => (string) $c->last_message,
                'last_message_at' => optional($c->last_message_at)->toDateTimeString(),
                'total_messages' => $totalMsgs,
                'buyer_unread' => (int) $c->buyer_unread_count,
                'seller_unread' => (int) $c->seller_unread_count,
                'status' => (int) $c->status === 1 ? 'active' : 'archived',
            ];
        });

        $totalConversations = LiveChatConversation::count();
        $totalMessages = LiveChatMessage::count();
        $messagesToday = LiveChatMessage::whereDate('created_at', today())->count();

        return [
            'summary' => [
                'total_conversations' => $totalConversations,
                'total_messages' => $totalMessages,
                'messages_today' => $messagesToday,
                'active_conversations' => LiveChatConversation::where('status', 1)->count(),
            ],
            'conversations' => $conversations,
        ];
    }

    /**
     * Admin Chat Hub: Inspect full permitted transcript of a conversation.
     */
    public function getAdminConversationDetails(int $conversationId): array
    {
        $conversation = LiveChatConversation::with(['buyer', 'seller'])->findOrFail($conversationId);

        $messages = LiveChatMessage::where('conversation_id', $conversationId)
            ->with(['fromUser'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (LiveChatMessage $m) use ($conversation) {
                $isBuyer = (int) $m->from_user === (int) $conversation->buyer_id;
                return [
                    'id' => (int) $m->id,
                    'from_user' => (int) $m->from_user,
                    'sender_name' => optional($m->fromUser)->name ?? 'User #' . $m->from_user,
                    'sender_role' => $isBuyer ? 'buyer' : 'seller',
                    'message' => (string) $m->message,
                    'image' => (string) $m->image,
                    'image_url' => $m->image_url,
                    'is_read' => (bool) $m->is_read,
                    'created_at' => optional($m->created_at)->toDateTimeString(),
                    'time_str' => optional($m->created_at)->format('h:i A'),
                ];
            });

        return [
            'conversation' => [
                'id' => (int) $conversation->id,
                'buyer' => [
                    'id' => optional($conversation->buyer)->id,
                    'name' => optional($conversation->buyer)->name,
                    'email' => optional($conversation->buyer)->email,
                ],
                'seller' => [
                    'id' => optional($conversation->seller)->id,
                    'name' => optional($conversation->seller)->name,
                    'email' => optional($conversation->seller)->email,
                ],
                'status' => (int) $conversation->status === 1 ? 'active' : 'archived',
                'created_at' => optional($conversation->created_at)->toDateTimeString(),
            ],
            'messages' => $messages,
        ];
    }
}
