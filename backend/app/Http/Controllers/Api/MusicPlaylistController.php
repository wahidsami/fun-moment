<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\MusicEntitlement;
use App\MusicFavorite;
use App\MusicPlaylist;
use App\MusicPlaylistTrack;
use App\MusicSetting;
use App\MusicTrack;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MusicPlaylistController extends Controller
{
    /**
     * Resolve authenticated customer user.
     */
    protected function resolveUser(Request $request): ?User
    {
        return $request->user() ?? Auth::guard('sanctum')->user() ?? Auth::user();
    }

    /**
     * Determine authoritative access state for a track relative to the user.
     *
     * Returns:
     * - 'status': free | purchased | rented | rental_expired | purchase_required | unavailable
     * - 'label_en': localized English label
     * - 'label_ar': localized Arabic label
     * - 'is_playable': whether track can be streamed in full
     * - 'can_stream_full': whether full stream ticket is grantable
     * - 'expires_at': ISO-8601 string if rental
     */
    public static function resolveAccessStatus(?User $user, ?MusicTrack $track): array
    {
        if (!$track) {
            return [
                'status' => 'unavailable',
                'label_en' => 'Unavailable',
                'label_ar' => 'غير متاح',
                'is_playable' => false,
                'can_stream_full' => false,
                'expires_at' => null,
            ];
        }

        $isAvailable = $track->isApproved()
            && $track->isMediaReady()
            && !$track->isSuspended()
            && MusicSetting::isMarketplaceEnabled();

        // 1. Check user entitlement
        if ($user) {
            $entitlement = MusicEntitlement::query()
                ->where('user_id', $user->id)
                ->where('track_id', $track->id)
                ->where('is_active', true)
                ->whereNull('revoked_at')
                ->first();

            if ($entitlement) {
                if ($entitlement->expires_at === null) {
                    // Perpetual Purchase
                    return [
                        'status' => 'purchased',
                        'label_en' => 'Purchased',
                        'label_ar' => 'تم الشراء',
                        'is_playable' => true,
                        'can_stream_full' => true,
                        'expires_at' => null,
                    ];
                }

                if ($entitlement->expires_at->isPast()) {
                    // Expired in-flight
                    return [
                        'status' => 'rental_expired',
                        'label_en' => 'Rental Expired',
                        'label_ar' => 'انتهى الاستئجار',
                        'is_playable' => false,
                        'can_stream_full' => false,
                        'expires_at' => $entitlement->expires_at->toIso8601String(),
                    ];
                }

                // Active valid rental
                return [
                    'status' => 'rented',
                    'label_en' => 'Rented',
                    'label_ar' => 'مستأجر',
                    'is_playable' => true,
                    'can_stream_full' => true,
                    'expires_at' => $entitlement->expires_at->toIso8601String(),
                ];
            }

            // Check if user had a past expired rental
            $pastExpiredRental = MusicEntitlement::query()
                ->where('user_id', $user->id)
                ->where('track_id', $track->id)
                ->where('is_active', false)
                ->whereNotNull('expires_at')
                ->latest('expires_at')
                ->first();

            if ($pastExpiredRental && $pastExpiredRental->expires_at->isPast()) {
                return [
                    'status' => 'rental_expired',
                    'label_en' => 'Rental Expired',
                    'label_ar' => 'انتهى الاستئجار',
                    'is_playable' => false,
                    'can_stream_full' => false,
                    'expires_at' => $pastExpiredRental->expires_at->toIso8601String(),
                ];
            }
        }

        // 2. If track is unavailable / unpublished / suspended
        if (!$isAvailable) {
            return [
                'status' => 'unavailable',
                'label_en' => 'Unavailable',
                'label_ar' => 'غير متاح',
                'is_playable' => false,
                'can_stream_full' => false,
                'expires_at' => null,
            ];
        }

        // 3. Free Track
        if ($track->hasActiveFreeOffer()) {
            return [
                'status' => 'free',
                'label_en' => 'Free',
                'label_ar' => 'مجاني',
                'is_playable' => true,
                'can_stream_full' => true,
                'expires_at' => null,
            ];
        }

        // 4. Paid track requiring purchase or rental
        return [
            'status' => 'purchase_required',
            'label_en' => 'Purchase Required',
            'label_ar' => 'شراء مطلوب',
            'is_playable' => false, // Full playback requires purchase, preview only
            'can_stream_full' => false,
            'expires_at' => null,
        ];
    }

    /**
     * List customer's playlists with track counts and covers.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Please log in to view your playlists.',
            ], 401);
        }

        $playlists = MusicPlaylist::query()
            ->where('user_id', $user->id)
            ->with([
                'tracks' => function ($q) {
                    $q->select(['music_tracks.id', 'music_tracks.cover_image', 'music_tracks.duration_seconds']);
                },
            ])
            ->withCount('playlistTracks')
            ->orderBy('updated_at', 'desc')
            ->get();

        $data = $playlists->map(function (MusicPlaylist $playlist) {
            return [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'description' => $playlist->description,
                'cover_image_url' => $playlist->cover_image_url,
                'is_public' => $playlist->is_public,
                'tracks_count' => (int) $playlist->playlist_tracks_count,
                'total_duration_seconds' => $playlist->getTotalDurationSeconds(),
                'created_at' => $playlist->created_at ? $playlist->created_at->toIso8601String() : null,
                'updated_at' => $playlist->updated_at ? $playlist->updated_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Create a new playlist.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Please log in to create a playlist.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'cover_image' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $coverPath = null;
        if ($request->hasFile('cover_image')) {
            $file = $request->file('cover_image');
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = 'playlist_cover_' . Str::uuid() . '.' . strtolower($ext);
            $coverPath = $file->storeAs('music_covers', $filename, 'public');
        }

        $playlist = MusicPlaylist::create([
            'user_id' => $user->id,
            'name' => trim($request->input('name')),
            'description' => $request->filled('description') ? trim($request->input('description')) : null,
            'cover_image' => $coverPath,
            'is_public' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Playlist created successfully.',
            'data' => [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'description' => $playlist->description,
                'cover_image_url' => $playlist->cover_image_url,
                'is_public' => $playlist->is_public,
                'tracks_count' => 0,
                'total_duration_seconds' => 0,
                'created_at' => $playlist->created_at->toIso8601String(),
                'updated_at' => $playlist->updated_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * View playlist details and ordered tracks.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Please log in to view this playlist.',
            ], 401);
        }

        $playlist = MusicPlaylist::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$playlist) {
            return response()->json([
                'success' => false,
                'message' => 'Playlist not found or access denied.',
            ], 404);
        }

        // Load ordered items
        $playlistTracks = MusicPlaylistTrack::query()
            ->where('playlist_id', $playlist->id)
            ->orderBy('position', 'asc')
            ->with([
                'track' => function ($q) {
                    $q->with([
                        'genre:id,name_en,name_ar,slug,icon',
                        'secondaryGenres:id,name_en,name_ar,slug',
                        'album:id,title_en,title_ar,artist_name,cover_image',
                        'originalTrack:id,title_en,title_ar,artist_name',
                        'offers' => fn($sub) => $sub->where('is_active', true),
                        'seller:id,name,username,image',
                    ]);
                },
            ])
            ->get();

        $userFavoriteIds = MusicFavorite::where('user_id', $user->id)
            ->whereIn('track_id', $playlistTracks->pluck('track_id'))
            ->pluck('track_id')
            ->flip()
            ->all();

        $formattedTracks = $playlistTracks->map(function (MusicPlaylistTrack $pt) use ($user, $userFavoriteIds) {
            $track = $pt->track;
            $accessStatus = self::resolveAccessStatus($user, $track);

            if (!$track) {
                return [
                    'id' => $pt->track_id,
                    'position' => (int) $pt->position,
                    'title_en' => 'Unavailable Track',
                    'title_ar' => 'مسار غير متاح',
                    'artist_name' => 'Unknown',
                    'cover_image_url' => null,
                    'duration_seconds' => 0,
                    'preview_duration_seconds' => 0,
                    'access_status' => $accessStatus,
                    'is_favorite' => false,
                    'added_at' => $pt->created_at ? $pt->created_at->toIso8601String() : null,
                ];
            }

            $coverUrl = null;
            if (!empty($track->cover_image)) {
                $coverUrl = str_starts_with($track->cover_image, 'http')
                    ? $track->cover_image
                    : url('storage/' . ltrim($track->cover_image, '/'));
            }

            return [
                'id' => $track->id,
                'position' => (int) $pt->position,
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
                'is_favorite' => isset($userFavoriteIds[$track->id]),
                'is_first_party' => $track->isOwnedByAdmin(),
                'owner_type' => $track->owner_type,
                'badge' => $track->isOwnedByAdmin() ? 'FUN MOMENT Original' : null,
                'badge_ar' => $track->isOwnedByAdmin() ? 'أصلي من FUN MOMENT' : null,
                'access_status' => $accessStatus,
                'added_at' => $pt->created_at ? $pt->created_at->toIso8601String() : null,
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
                'album' => $track->album ? [
                    'id' => $track->album->id,
                    'title_en' => $track->album->title_en,
                    'title_ar' => $track->album->title_ar ?: $track->album->title_en,
                    'artist_name' => $track->album->artist_name,
                    'cover_image_url' => $track->album->cover_image_url,
                    'track_number' => $track->track_number,
                ] : null,
                'original_track' => $track->originalTrack ? [
                    'id' => $track->originalTrack->id,
                    'title_en' => $track->originalTrack->title_en,
                    'title_ar' => $track->originalTrack->title_ar ?: $track->originalTrack->title_en,
                    'artist_name' => $track->originalTrack->artist_name,
                ] : null,
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
                'id' => $playlist->id,
                'name' => $playlist->name,
                'description' => $playlist->description,
                'cover_image_url' => $playlist->cover_image_url,
                'is_public' => $playlist->is_public,
                'tracks_count' => $playlistTracks->count(),
                'total_duration_seconds' => (int) $playlistTracks->sum(fn($pt) => $pt->track ? $pt->track->duration_seconds : 0),
                'created_at' => $playlist->created_at ? $playlist->created_at->toIso8601String() : null,
                'updated_at' => $playlist->updated_at ? $playlist->updated_at->toIso8601String() : null,
                'tracks' => $formattedTracks,
            ],
        ]);
    }

    /**
     * Rename or update metadata for a playlist.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Please log in to update this playlist.',
            ], 401);
        }

        $playlist = MusicPlaylist::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$playlist) {
            return response()->json([
                'success' => false,
                'message' => 'Playlist not found or access denied.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'cover_image' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('name')) {
            $playlist->name = trim($request->input('name'));
        }

        if ($request->has('description')) {
            $playlist->description = $request->filled('description') ? trim($request->input('description')) : null;
        }

        if ($request->hasFile('cover_image')) {
            // Delete previous cover if exists on public disk
            if ($playlist->cover_image && Storage::disk('public')->exists($playlist->cover_image)) {
                Storage::disk('public')->delete($playlist->cover_image);
            }
            $file = $request->file('cover_image');
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = 'playlist_cover_' . Str::uuid() . '.' . strtolower($ext);
            $playlist->cover_image = $file->storeAs('music_covers', $filename, 'public');
        }

        $playlist->save();

        return response()->json([
            'success' => true,
            'message' => 'Playlist updated successfully.',
            'data' => [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'description' => $playlist->description,
                'cover_image_url' => $playlist->cover_image_url,
                'is_public' => $playlist->is_public,
                'updated_at' => $playlist->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete a playlist without deleting underlying tracks.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Please log in to delete this playlist.',
            ], 401);
        }

        $playlist = MusicPlaylist::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$playlist) {
            return response()->json([
                'success' => false,
                'message' => 'Playlist not found or access denied.',
            ], 404);
        }

        // Delete cover image if custom cover was uploaded
        if ($playlist->cover_image && Storage::disk('public')->exists($playlist->cover_image)) {
            Storage::disk('public')->delete($playlist->cover_image);
        }

        // Cascade deletes pivot entries, leaves music_tracks untouched
        $playlist->delete();

        return response()->json([
            'success' => true,
            'message' => 'Playlist deleted successfully.',
        ]);
    }

    /**
     * Add a catalog track to a playlist.
     * Prevents duplicates within this playlist; allows same track across multiple playlists.
     */
    public function addTrack(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Please log in to modify this playlist.',
            ], 401);
        }

        $playlist = MusicPlaylist::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$playlist) {
            return response()->json([
                'success' => false,
                'message' => 'Playlist not found or access denied.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'track_id' => 'required|integer|exists:music_tracks,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $trackId = (int) $request->input('track_id');

        // Check for duplicate in this playlist
        $existing = MusicPlaylistTrack::where('playlist_id', $playlist->id)
            ->where('track_id', $trackId)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'error_code' => 'DUPLICATE_TRACK',
                'message' => 'This track is already in the playlist.',
            ], 422);
        }

        // Calculate next position
        $maxPosition = MusicPlaylistTrack::where('playlist_id', $playlist->id)->max('position');
        $nextPosition = ($maxPosition !== null) ? $maxPosition + 1 : 0;

        $playlistTrack = MusicPlaylistTrack::create([
            'playlist_id' => $playlist->id,
            'track_id' => $trackId,
            'position' => $nextPosition,
        ]);

        $playlist->touch();

        return response()->json([
            'success' => true,
            'message' => 'Track added to playlist.',
            'data' => [
                'playlist_id' => $playlist->id,
                'track_id' => $trackId,
                'position' => $playlistTrack->position,
                'tracks_count' => MusicPlaylistTrack::where('playlist_id', $playlist->id)->count(),
            ],
        ], 201);
    }

    /**
     * Remove a track from a playlist.
     */
    public function removeTrack(Request $request, int $id, int $trackId): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Please log in to modify this playlist.',
            ], 401);
        }

        $playlist = MusicPlaylist::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$playlist) {
            return response()->json([
                'success' => false,
                'message' => 'Playlist not found or access denied.',
            ], 404);
        }

        $entry = MusicPlaylistTrack::where('playlist_id', $playlist->id)
            ->where('track_id', $trackId)
            ->first();

        if (!$entry) {
            return response()->json([
                'success' => false,
                'message' => 'Track not found in this playlist.',
            ], 404);
        }

        $entry->delete();

        // Re-compact positions
        $remaining = MusicPlaylistTrack::where('playlist_id', $playlist->id)
            ->orderBy('position', 'asc')
            ->get();

        foreach ($remaining as $idx => $item) {
            if ($item->position !== $idx) {
                $item->update(['position' => $idx]);
            }
        }

        $playlist->touch();

        return response()->json([
            'success' => true,
            'message' => 'Track removed from playlist.',
            'data' => [
                'playlist_id' => $playlist->id,
                'track_id' => $trackId,
                'tracks_count' => $remaining->count(),
            ],
        ]);
    }

    /**
     * Reorder tracks in a playlist.
     */
    public function reorder(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Please log in to reorder this playlist.',
            ], 401);
        }

        $playlist = MusicPlaylist::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$playlist) {
            return response()->json([
                'success' => false,
                'message' => 'Playlist not found or access denied.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'track_ids' => 'required|array',
            'track_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $trackIds = array_values(array_unique(array_map('intval', $request->input('track_ids'))));

        DB::transaction(function () use ($playlist, $trackIds) {
            foreach ($trackIds as $position => $trackId) {
                MusicPlaylistTrack::where('playlist_id', $playlist->id)
                    ->where('track_id', $trackId)
                    ->update(['position' => $position]);
            }
        });

        $playlist->touch();

        return response()->json([
            'success' => true,
            'message' => 'Playlist reordered successfully.',
            'data' => [
                'playlist_id' => $playlist->id,
                'ordered_track_ids' => $trackIds,
            ],
        ]);
    }

    /**
     * Helper for mobile Add to Playlist sheet:
     * Returns all user playlists with a `contains_track` flag for the given track.
     */
    public function availableForTrack(Request $request, int $trackId): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Please log in to view playlists.',
            ], 401);
        }

        $playlists = MusicPlaylist::query()
            ->where('user_id', $user->id)
            ->withCount('playlistTracks')
            ->orderBy('updated_at', 'desc')
            ->get();

        $containedPlaylistIds = MusicPlaylistTrack::whereIn('playlist_id', $playlists->pluck('id'))
            ->where('track_id', $trackId)
            ->pluck('playlist_id')
            ->flip()
            ->all();

        $data = $playlists->map(function (MusicPlaylist $playlist) use ($containedPlaylistIds) {
            return [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'description' => $playlist->description,
                'cover_image_url' => $playlist->cover_image_url,
                'tracks_count' => (int) $playlist->playlist_tracks_count,
                'contains_track' => isset($containedPlaylistIds[$playlist->id]),
            ];
        });

        return response()->json([
            'success' => true,
            'track_id' => $trackId,
            'data' => $data,
        ]);
    }
}
