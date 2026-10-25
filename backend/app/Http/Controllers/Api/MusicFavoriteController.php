<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\MusicFavorite;
use App\MusicSetting;
use App\MusicTrack;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MusicFavoriteController extends Controller
{
    /**
     * Resolve authenticated user.
     */
    protected function resolveUser(Request $request)
    {
        return $request->user() ?? Auth::guard('sanctum')->user() ?? Auth::user();
    }

    /**
     * Toggle favorite status for a track.
     */
    public function toggle(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Please log in to save tracks to your favorites.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'track_id' => 'required|integer|exists:music_tracks,id',
            'action' => 'nullable|string|in:favorite,unfavorite,toggle',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $trackId = (int) $request->input('track_id');
        $track = MusicTrack::find($trackId);

        // Security: only published, media-ready tracks can be favorited
        if (!$track || !$track->isApproved() || !$track->isMediaReady() || $track->isSuspended()) {
            return response()->json([
                'success' => false,
                'message' => 'Track is not available in the public catalog.',
            ], 404);
        }

        $action = $request->input('action', 'toggle');
        $isFavorited = MusicFavorite::setFavorite($user->id, $trackId, $action);

        return response()->json([
            'success' => true,
            'is_favorite' => $isFavorited,
            'track_id' => $trackId,
            'data' => [
                'is_favorite' => $isFavorited,
                'track_id' => $trackId,
                'action_performed' => $action,
            ],
            'message' => $isFavorited
                ? 'Track added to favorites.'
                : 'Track removed from favorites.',
        ]);
    }

    /**
     * List all favorited tracks for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Please log in to view your favorites.',
            ], 401);
        }

        $perPage = min(50, max(1, (int) $request->input('per_page', 15)));

        $paginatedFavorites = MusicFavorite::query()
            ->where('user_id', $user->id)
            ->whereHas('track', function ($q) {
                $q->published()->mediaReady();
            })
            ->with([
                'track' => function ($q) {
                    $q->with([
                        'genre:id,name_en,name_ar,slug,icon',
                        'secondaryGenres:id,name_en,name_ar,slug',
                        'offers' => fn($sub) => $sub->where('is_active', true),
                        'seller:id,name,username,image',
                    ]);
                },
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $tracks = $paginatedFavorites->getCollection()->map(function (MusicFavorite $fav) {
            $track = $fav->track;
            $coverUrl = null;
            if (!empty($track->cover_image)) {
                $coverUrl = str_starts_with($track->cover_image, 'http')
                    ? $track->cover_image
                    : url('storage/' . ltrim($track->cover_image, '/'));
            }

            return [
                'id' => $track->id,
                'title_en' => $track->title_en,
                'title_ar' => $track->title_ar ?: $track->title_en,
                'artist_name' => $track->artist_name,
                'cover_image_url' => $coverUrl,
                'duration_seconds' => (int) $track->duration_seconds,
                'preview_duration_seconds' => (int) ($track->preview_duration_seconds ?: MusicSetting::get('paid_preview_duration_seconds', 20)),
                'play_count' => (int) $track->play_count,
                'bpm' => $track->bpm,
                'track_key' => $track->track_key,
                'released_at' => $track->released_at ? $track->released_at->toIso8601String() : null,
                'is_favorite' => true,
                'favorited_at' => $fav->created_at ? $fav->created_at->toIso8601String() : null,
                'primary_genre' => $track->genre ? [
                    'id' => $track->genre->id,
                    'name_en' => $track->genre->name_en,
                    'name_ar' => $track->genre->name_ar,
                    'slug' => $track->genre->slug,
                ] : null,
                'secondary_genres' => $track->secondaryGenres->map(fn($g) => [
                    'id' => $g->id,
                    'name_en' => $g->name_en,
                    'name_ar' => $g->name_ar,
                    'slug' => $g->slug,
                ])->values(),
                'offers' => $track->offers->map(fn($o) => [
                    'id' => $o->id,
                    'offer_type' => $o->offer_type,
                    'price' => (float) $o->price,
                    'currency' => $o->currency ?: 'SAR',
                    'rental_duration_days' => $o->rental_duration_days,
                    'license_type' => $o->license_type,
                ])->values(),
                'seller' => $track->seller ? [
                    'id' => $track->seller->id,
                    'name' => $track->seller->name,
                    'username' => $track->seller->username,
                    'image_url' => $track->seller->image ? url('storage/' . ltrim($track->seller->image, '/')) : null,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'current_page' => $paginatedFavorites->currentPage(),
                'data' => $tracks,
                'per_page' => $paginatedFavorites->perPage(),
                'total' => $paginatedFavorites->total(),
                'last_page' => $paginatedFavorites->lastPage(),
            ],
            'meta' => [
                'current_page' => $paginatedFavorites->currentPage(),
                'per_page' => $paginatedFavorites->perPage(),
                'total' => $paginatedFavorites->total(),
                'last_page' => $paginatedFavorites->lastPage(),
            ],
        ]);
    }
}
