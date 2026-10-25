<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\MusicFavorite;
use App\MusicOffer;
use App\MusicSetting;
use App\MusicTrack;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MusicCatalogController extends Controller
{
    /**
     * Resolve authenticated user across guards (sanctum, web).
     */
    protected function resolveUser(Request $request)
    {
        return $request->user() ?? Auth::guard('sanctum')->user() ?? Auth::user();
    }

    /**
     * Browse and search public music catalog with validated filters and safe pagination.
     */
    public function index(Request $request): JsonResponse
    {
        if (!MusicSetting::isMarketplaceEnabled()) {
            return response()->json([
                'success' => false,
                'error_code' => 'MARKETPLACE_DISABLED',
                'message' => 'Music marketplace is currently disabled by platform administration.',
                'data' => [
                    'current_page' => 1,
                    'data' => [],
                    'total' => 0,
                    'last_page' => 1,
                ],
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'q' => 'nullable|string|max:100',
            'genre_id' => 'nullable|integer|exists:music_genres,id',
            'artist' => 'nullable|string|max:100',
            'offer_type' => 'nullable|string|in:free,rent,sale',
            'sort' => 'nullable|string|in:latest,popular,title',
            'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid filter parameters.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $this->resolveUser($request);
        $perPage = min(50, max(1, (int) $request->input('per_page', 15)));

        // Base query: only approved, published, media-ready tracks with active offers
        $query = MusicTrack::query()
            ->published()
            ->mediaReady()
            ->whereHas('offers', function ($q) {
                $q->where('is_active', true);
            })
            ->with([
                'genre:id,name_en,name_ar,slug,icon',
                'secondaryGenres:id,name_en,name_ar,slug',
                'offers' => function ($q) {
                    $q->where('is_active', true)
                      ->select(['id', 'track_id', 'offer_type', 'price', 'currency', 'rental_duration_days', 'license_type']);
                },
                'seller:id,name,username,image',
            ]);

        // Search by title or artist
        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('title_en', 'ilike', "%{$q}%")
                    ->orWhere('title_ar', 'ilike', "%{$q}%")
                    ->orWhere('artist_name', 'ilike', "%{$q}%");
            });
        }

        // Filter by artist
        if ($request->filled('artist')) {
            $artist = trim($request->input('artist'));
            $query->where('artist_name', 'ilike', "%{$artist}%");
        }

        // Filter by genre (matches primary or secondary genre pivot)
        if ($request->filled('genre_id')) {
            $genreId = (int) $request->input('genre_id');
            $query->where(function ($q) use ($genreId) {
                $q->where('genre_id', $genreId)
                  ->orWhereHas('secondaryGenres', function ($sub) use ($genreId) {
                      $sub->where('music_genres.id', $genreId);
                  });
            });
        }

        // Filter by offer type (free, rent, sale) respecting administrative switches
        if ($request->filled('offer_type')) {
            $offerType = $request->input('offer_type');
            $isOfferAllowed = match ($offerType) {
                'free' => (bool) MusicSetting::get('offer_free_enabled', true),
                'rent' => (bool) MusicSetting::get('offer_rent_enabled', true),
                'sale' => (bool) MusicSetting::get('offer_sale_enabled', true),
                default => true,
            };

            if ($isOfferAllowed) {
                $query->whereHas('offers', function ($q) use ($offerType) {
                    $q->where('offer_type', $offerType)->where('is_active', true);
                });
            } else {
                // If the requested offer type is administratively disabled, match nothing
                $query->whereRaw('1 = 0');
            }
        }

        // Sorting
        $sort = $request->input('sort', 'latest');
        match ($sort) {
            'popular' => $query->orderBy('play_count', 'desc')->orderBy('released_at', 'desc'),
            'title' => $query->orderBy('title_en', 'asc'),
            default => $query->orderBy('released_at', 'desc'),
        };

        $paginated = $query->paginate($perPage);

        // Map favorite status and sanitize private fields
        $userFavoriteIds = $user ? MusicFavorite::where('user_id', $user->id)
            ->whereIn('track_id', $paginated->pluck('id'))
            ->pluck('track_id')
            ->flip()
            ->all() : [];

        $tracks = $paginated->getCollection()->map(function (MusicTrack $track) use ($userFavoriteIds) {
            return $this->formatCatalogTrack($track, isset($userFavoriteIds[$track->id]));
        });

        return response()->json([
            'success' => true,
            'data' => [
                'current_page' => $paginated->currentPage(),
                'data' => $tracks,
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * Get latest releases for the top carousel.
     * Handles 0, 1, or multiple items cleanly.
     */
    public function latestReleases(Request $request): JsonResponse
    {
        if (!MusicSetting::isMarketplaceEnabled()) {
            return response()->json([
                'success' => false,
                'error_code' => 'MARKETPLACE_DISABLED',
                'message' => 'Music marketplace is currently disabled.',
                'data' => [],
            ], 403);
        }

        $limit = min(20, max(1, (int) $request->input('limit', 8)));
        $user = $this->resolveUser($request);

        $tracks = MusicTrack::query()
            ->published()
            ->mediaReady()
            ->whereHas('offers', fn($q) => $q->where('is_active', true))
            ->with([
                'genre:id,name_en,name_ar,slug,icon',
                'secondaryGenres:id,name_en,name_ar,slug',
                'offers' => fn($q) => $q->where('is_active', true),
                'seller:id,name,username,image',
            ])
            ->orderBy('released_at', 'desc')
            ->take($limit)
            ->get();

        $userFavoriteIds = $user && $tracks->isNotEmpty()
            ? MusicFavorite::where('user_id', $user->id)
                ->whereIn('track_id', $tracks->pluck('id'))
                ->pluck('track_id')
                ->flip()
                ->all()
            : [];

        $formatted = $tracks->map(fn($t) => $this->formatCatalogTrack($t, isset($userFavoriteIds[$t->id])));

        return response()->json([
            'success' => true,
            'count' => $formatted->count(),
            'data' => $formatted,
        ]);
    }

    /**
     * Get detailed view for a single catalog track.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        if (!MusicSetting::isMarketplaceEnabled()) {
            return response()->json([
                'success' => false,
                'error_code' => 'MARKETPLACE_DISABLED',
                'message' => 'Music marketplace is currently disabled.',
            ], 403);
        }

        $user = $this->resolveUser($request);

        $track = MusicTrack::query()
            ->where('id', $id)
            ->with([
                'genre:id,name_en,name_ar,slug,icon',
                'secondaryGenres:id,name_en,name_ar,slug',
                'offers' => fn($q) => $q->where('is_active', true),
                'seller:id,name,username,image',
            ])
            ->first();

        if (!$track) {
            return response()->json([
                'success' => false,
                'message' => 'Track not found.',
            ], 404);
        }

        // Security: Public catalog users can ONLY inspect published & media-ready tracks.
        // Track owner (seller) or admin can inspect their own track even before publication.
        $isOwnerOrAdmin = $user && (
            ((int) $track->seller_id === (int) $user->id) ||
            ($user instanceof \App\Admin) ||
            ((int) ($user->role_id ?? 0) === 1)
        );

        if (!$isOwnerOrAdmin && (!$track->isApproved() || !$track->isMediaReady() || $track->isSuspended())) {
            return response()->json([
                'success' => false,
                'message' => 'This track is not publicly available.',
            ], 404);
        }

        $isFavorite = $user ? MusicFavorite::where('user_id', $user->id)->where('track_id', $track->id)->exists() : false;

        return response()->json([
            'success' => true,
            'data' => $this->formatCatalogTrack($track, $isFavorite, true),
        ]);
    }

    /**
     * Format a track for public catalog responses without exposing master paths or private storage keys.
     */
    protected function formatCatalogTrack(MusicTrack $track, bool $isFavorite = false, bool $fullDetails = false): array
    {
        // Resolve cover URL
        $coverUrl = null;
        if (!empty($track->cover_image)) {
            $coverUrl = str_starts_with($track->cover_image, 'http')
                ? $track->cover_image
                : url('storage/' . ltrim($track->cover_image, '/'));
        }

        $data = [
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
            'is_favorite' => $isFavorite,
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

        if ($fullDetails) {
            $data['description_en'] = $track->description_en;
            $data['description_ar'] = $track->description_ar ?: $track->description_en;
            $data['rights_declaration_type'] = $track->rights_declaration_type;
            $data['rights_notes'] = $track->rights_notes;
            $data['original_creator_name'] = $track->original_creator_name ?: $track->artist_name;
        }

        return $data;
    }
}
