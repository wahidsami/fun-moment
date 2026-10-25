<?php

namespace App\Http\Controllers;

use App\Admin;
use App\MusicOffer;
use App\MusicTrack;
use App\Services\Music\MusicStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AdminMusicModerationController extends Controller
{
    protected MusicStreamService $streamService;

    public function __construct(MusicStreamService $streamService)
    {
        $this->streamService = $streamService;
    }

    /**
     * Check if authenticated administrator has permissions to review and moderate music submissions.
     * Returns [$admin, $errorResponse].
     */
    protected function resolveAdmin(Request $request): array
    {
        $admin = auth('admin')->user() ?? Auth::guard('admin')->user() ?? Auth::user();
        if (!$admin || !($admin instanceof Admin || (isset($admin->role) && in_array(strtolower($admin->role), ['super admin', 'super_admin', 'admin'], true)) || (int) ($admin->role_id ?? 0) === 1)) {
            if (!$admin) {
                return [
                    null,
                    response()->json([
                        'success' => false,
                        'error_code' => 'UNAUTHENTICATED',
                        'message_en' => 'Unauthenticated administrator access.',
                        'message_ar' => 'غير مصرح: يجب تسجيل الدخول كمسؤول.',
                    ], 401),
                ];
            }

            return [
                null,
                response()->json([
                    'success' => false,
                    'error_code' => 'FORBIDDEN',
                    'message_en' => 'Unauthorized: administrative music moderation privileges required.',
                    'message_ar' => 'غير مصرح: تتطلب صلاحيات المشرف لإدارة واعتماد الموسيقى.',
                ], 403),
            ];
        }

        return [$admin, null];
    }

    /**
     * List music tracks for administrative review and moderation.
     */
    public function index(Request $request): JsonResponse
    {
        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $query = MusicTrack::query()
            ->with(['genre', 'secondaryGenres', 'offers', 'seller']);

        // Filter by submission status (pending, approved, rejected, suspended, draft)
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by media processing status
        if ($request->has('media_status') && !empty($request->media_status) && $request->media_status !== 'all') {
            $query->where('media_status', $request->media_status);
        }

        // Filter by genre
        if ($request->has('genre_id') && is_numeric($request->genre_id)) {
            $query->where('genre_id', (int) $request->genre_id);
        }

        // Search by title, artist, or seller name/email
        if ($request->has('search') && !empty($request->search)) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'ILIKE', $search)
                  ->orWhere('title_ar', 'ILIKE', $search)
                  ->orWhere('artist_name', 'ILIKE', $search)
                  ->orWhereHas('seller', function ($sq) use ($search) {
                      $sq->where('name', 'ILIKE', $search)
                         ->orWhere('email', 'ILIKE', $search);
                  });
            });
        }

        $counts = [
            'pending' => MusicTrack::where('status', MusicTrack::STATUS_PENDING)->count(),
            'approved' => MusicTrack::where('status', MusicTrack::STATUS_APPROVED)->count(),
            'rejected' => MusicTrack::where('status', MusicTrack::STATUS_REJECTED)->count(),
            'suspended' => MusicTrack::where('status', MusicTrack::STATUS_SUSPENDED)->count(),
            'all' => MusicTrack::count(),
        ];

        $perPage = max(5, min(50, (int) $request->input('per_page', 15)));
        $tracks = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'counts' => $counts,
            'data' => $tracks->items(),
            'meta' => [
                'current_page' => $tracks->currentPage(),
                'per_page' => $tracks->perPage(),
                'total' => $tracks->total(),
                'last_page' => $tracks->lastPage(),
            ],
        ]);
    }

    /**
     * Inspect a specific track for moderation.
     */
    public function show($requestOrId, ?int $id = null): JsonResponse
    {
        $request = $requestOrId instanceof Request ? $requestOrId : request();
        $id = $requestOrId instanceof Request ? $id : (int) $requestOrId;

        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $track = MusicTrack::query()
            ->with(['genre', 'secondaryGenres', 'offers', 'seller'])
            ->find($id);

        if (!$track) {
            return response()->json([
                'success' => false,
                'message' => 'Music track not found',
            ], 404);
        }

        // Generate an administrative stream ticket for quality inspection
        $ticket = null;
        $streamUrl = null;

        if ($track->isMediaReady() && !empty($track->audio_path_preview)) {
            $ticket = $this->streamService->createStreamTicket($admin, $track, 'preview', 3600);
            $streamUrl = route('api.v1.music.tracks.stream', [
                'id' => $track->id,
                'ticket' => $ticket,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $track,
            'track' => array_merge($track->toArray(), [
                'preview_stream_url' => $streamUrl,
            ]),
            'admin_stream' => [
                'available' => !empty($ticket),
                'ticket' => $ticket,
                'stream_url' => $streamUrl,
            ],
        ]);
    }

    /**
     * Approve a music submission for public publication.
     */
    public function approve($requestOrId, ?int $id = null): JsonResponse
    {
        $request = $requestOrId instanceof Request ? $requestOrId : request();
        $id = $requestOrId instanceof Request ? $id : (int) $requestOrId;

        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $track = MusicTrack::with('offers')->find($id);
        if (!$track) {
            return response()->json([
                'success' => false,
                'message' => 'Music track not found',
            ], 404);
        }

        // Integrity Check: Audio MUST be ready before approval is permitted
        if (!$track->isMediaReady()) {
            return response()->json([
                'success' => false,
                'error_code' => 'MEDIA_NOT_READY',
                'message_en' => 'Cannot approve track: audio processing is incomplete or failed. Media status: ' . $track->media_status,
                'message_ar' => 'لا يمكن اعتماد المسار: معالجة الصوت غير مكتملة أو فشلت. حالة الوسائط: ' . $track->media_status,
            ], 422);
        }

        DB::transaction(function () use ($track, $admin) {
            $track->update([
                'status' => MusicTrack::STATUS_APPROVED,
                'rejection_reason' => null,
                'reviewed_by_admin_id' => $admin ? $admin->id : null,
                'reviewed_at' => now(),
                'released_at' => $track->released_at ?: now(),
            ]);

            // Reactivate offers if they were deactivated
            $track->offers()->update(['is_active' => true]);
        });

        Log::info("Music track #{$track->id} approved by admin #{$admin->id}");

        return response()->json([
            'success' => true,
            'message_en' => "Track '{$track->title_en}' approved and published successfully.",
            'message_ar' => "تم اعتماد ونشر المسار '{$track->title_ar}' بنجاح.",
            'track' => $track->fresh(['genre', 'secondaryGenres', 'offers', 'seller']),
            'data' => $track->fresh(['genre', 'secondaryGenres', 'offers', 'seller']),
        ]);
    }

    /**
     * Reject a music submission with mandatory feedback reason.
     */
    public function reject($requestOrId, $idOrNull = null): JsonResponse
    {
        $request = $requestOrId instanceof Request ? $requestOrId : request();
        $id = $requestOrId instanceof Request ? (int) $idOrNull : (int) $requestOrId;

        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $track = MusicTrack::with('offers')->find($id);
        if (!$track) {
            return response()->json([
                'success' => false,
                'message' => 'Music track not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|min:3|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'A clear rejection reason is mandatory.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $reason = trim($request->input('rejection_reason'));

        DB::transaction(function () use ($track, $admin, $reason) {
            $track->update([
                'status' => MusicTrack::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'reviewed_by_admin_id' => $admin ? $admin->id : null,
                'reviewed_at' => now(),
            ]);

            // Deactivate public offers
            $track->offers()->update(['is_active' => false]);
        });

        Log::info("Music track #{$track->id} rejected by admin #{$admin->id}. Reason: {$reason}");

        return response()->json([
            'success' => true,
            'message_en' => "Track rejected with feedback recorded.",
            'message_ar' => "تم رفض المسار وتسجيل ملاحظات المراجعة.",
            'track' => $track->fresh(['genre', 'secondaryGenres', 'offers', 'seller']),
            'data' => $track->fresh(['genre', 'secondaryGenres', 'offers', 'seller']),
        ]);
    }

    /**
     * Administratively suspend a track (e.g. copyright claim, investigation).
     * Strictly preserves prior buyer entitlements.
     */
    public function suspend($requestOrId, $idOrNull = null): JsonResponse
    {
        $request = $requestOrId instanceof Request ? $requestOrId : request();
        $id = $requestOrId instanceof Request ? (int) $idOrNull : (int) $requestOrId;

        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $track = MusicTrack::with('offers')->find($id);
        if (!$track) {
            return response()->json([
                'success' => false,
                'message' => 'Music track not found',
            ], 404);
        }

        $reason = $request->input('suspension_reason', $request->input('reason', 'Administrative suspension by platform review team'));

        DB::transaction(function () use ($track, $reason) {
            $track->update([
                'status' => MusicTrack::STATUS_SUSPENDED,
                'rejection_reason' => $reason,
            ]);

            // Deactivate public commercial offers
            $track->offers()->update(['is_active' => false]);
        });

        return response()->json([
            'success' => true,
            'message_en' => 'Track suspended from public catalog.',
            'message_ar' => 'تم تعليق المسار من الكتالوج العام.',
            'track' => $track->fresh(['genre', 'secondaryGenres', 'offers', 'seller']),
            'data' => $track->fresh(['genre', 'secondaryGenres', 'offers', 'seller']),
        ]);
    }
}
