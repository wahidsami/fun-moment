<?php

namespace App\Http\Controllers;

use App\Admin;
use App\MusicAlbum;
use App\MusicGenre;
use App\MusicTrack;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminMusicAlbumController extends Controller
{
    /**
     * Resolve authenticated administrator or return unauthorized response.
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
                    'message_en' => 'Unauthorized: administrative music library privileges required.',
                    'message_ar' => 'غير مصرح: تتطلب صلاحيات المشرف لإدارة مكتبة الموسيقى.',
                ], 403),
            ];
        }

        return [$admin, null];
    }

    /**
     * List all albums for administration.
     */
    public function index(Request $request): JsonResponse
    {
        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $query = MusicAlbum::query()
            ->with(['genre', 'admin:id,name'])
            ->withCount('tracks');

        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('genre_id') && is_numeric($request->genre_id)) {
            $query->where('genre_id', (int) $request->genre_id);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'ILIKE', $search)
                  ->orWhere('title_ar', 'ILIKE', $search)
                  ->orWhere('artist_name', 'ILIKE', $search);
            });
        }

        $perPage = max(5, min(100, (int) $request->input('per_page', 15)));
        $albums = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $albums->items(),
            'meta' => [
                'current_page' => $albums->currentPage(),
                'per_page' => $albums->perPage(),
                'total' => $albums->total(),
                'last_page' => $albums->lastPage(),
            ],
        ]);
    }

    /**
     * Inspect an album and its ordered tracks.
     */
    public function show($requestOrId, ?int $id = null): JsonResponse
    {
        $request = $requestOrId instanceof Request ? $requestOrId : request();
        $id = $requestOrId instanceof Request ? $id : (int) $requestOrId;

        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $album = MusicAlbum::query()
            ->with([
                'genre',
                'admin:id,name',
                'tracks' => function ($q) {
                    $q->orderBy('track_number', 'asc')->with(['genre', 'offers']);
                },
            ])
            ->find($id);

        if (!$album) {
            return response()->json([
                'success' => false,
                'message' => 'Music album not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $album,
        ]);
    }

    /**
     * Create a new music album.
     */
    public function store(Request $request): JsonResponse
    {
        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $validator = Validator::make($request->all(), [
            'title_en' => 'required|string|max:191',
            'title_ar' => 'nullable|string|max:191',
            'artist_name' => 'nullable|string|max:191',
            'genre_id' => 'required|integer|exists:music_genres,id',
            'description_en' => 'nullable|string|max:2000',
            'description_ar' => 'nullable|string|max:2000',
            'is_featured' => 'nullable|boolean',
            'status' => 'nullable|string|in:draft,published,archived',
            'cover_image' => 'nullable|file|image|mimes:jpeg,png,webp,jpg|max:5120',
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
            $coverFile = $request->file('cover_image');
            $coverExt = $coverFile->getClientOriginalExtension() ?: 'jpg';
            $coverName = 'album_cover_' . Str::uuid()->toString() . '.' . $coverExt;
            $coverPath = $coverFile->storeAs('music_covers', $coverName, 'public');
        }

        $status = $request->input('status', MusicAlbum::STATUS_DRAFT);
        $titleEn = trim($request->input('title_en'));
        $titleAr = trim($request->input('title_ar') ?: $titleEn);
        $artistName = trim($request->input('artist_name') ?: 'FUN MOMENT Originals');

        $album = MusicAlbum::create([
            'title_en' => $titleEn,
            'title_ar' => $titleAr,
            'artist_name' => $artistName,
            'genre_id' => (int) $request->input('genre_id'),
            'cover_image' => $coverPath,
            'description_en' => $request->input('description_en'),
            'description_ar' => $request->input('description_ar') ?: $request->input('description_en'),
            'status' => $status,
            'owner_type' => MusicAlbum::OWNER_TYPE_ADMIN,
            'admin_id' => $admin->id,
            'is_featured' => filter_var($request->input('is_featured', false), FILTER_VALIDATE_BOOLEAN),
            'released_at' => $status === MusicAlbum::STATUS_PUBLISHED ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Music album created successfully.',
            'album' => $album->load('genre'),
            'data' => $album->load('genre'),
        ], 201);
    }

    /**
     * Update an existing music album.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $album = MusicAlbum::find($id);
        if (!$album) {
            return response()->json([
                'success' => false,
                'message' => 'Music album not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title_en' => 'sometimes|required|string|max:191',
            'title_ar' => 'nullable|string|max:191',
            'artist_name' => 'nullable|string|max:191',
            'genre_id' => 'sometimes|required|integer|exists:music_genres,id',
            'description_en' => 'nullable|string|max:2000',
            'description_ar' => 'nullable|string|max:2000',
            'is_featured' => 'nullable|boolean',
            'status' => 'nullable|string|in:draft,published,archived',
            'cover_image' => 'nullable|file|image|mimes:jpeg,png,webp,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updates = [];
        foreach (['title_en', 'title_ar', 'artist_name', 'genre_id', 'description_en', 'description_ar', 'status'] as $field) {
            if ($request->has($field)) {
                $updates[$field] = $request->input($field);
            }
        }

        if ($request->has('is_featured')) {
            $updates['is_featured'] = filter_var($request->input('is_featured'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('cover_image')) {
            $coverFile = $request->file('cover_image');
            $coverExt = $coverFile->getClientOriginalExtension() ?: 'jpg';
            $coverName = 'album_cover_' . Str::uuid()->toString() . '.' . $coverExt;
            $updates['cover_image'] = $coverFile->storeAs('music_covers', $coverName, 'public');
        }

        $album->update($updates);

        return response()->json([
            'success' => true,
            'message' => 'Music album updated successfully.',
            'album' => $album->fresh('genre'),
            'data' => $album->fresh('genre'),
        ]);
    }

    /**
     * Publish an album.
     */
    public function publish(Request $request, int $id): JsonResponse
    {
        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $album = MusicAlbum::find($id);
        if (!$album) {
            return response()->json([
                'success' => false,
                'message' => 'Music album not found',
            ], 404);
        }

        $album->update([
            'status' => MusicAlbum::STATUS_PUBLISHED,
            'released_at' => $album->released_at ?: now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Music album published successfully.',
            'album' => $album->fresh('genre'),
            'data' => $album->fresh('genre'),
        ]);
    }

    /**
     * Reorder tracks within an album.
     * Takes an array of track_ids in desired sequence.
     */
    public function reorderTracks(Request $request, int $id): JsonResponse
    {
        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $album = MusicAlbum::find($id);
        if (!$album) {
            return response()->json([
                'success' => false,
                'message' => 'Music album not found',
            ], 404);
        }

        $rawTracks = $request->input('tracks');
        if (is_array($rawTracks)) {
            usort($rawTracks, function ($a, $b) {
                $numA = is_array($a) && isset($a['track_number']) ? (int) $a['track_number'] : 0;
                $numB = is_array($b) && isset($b['track_number']) ? (int) $b['track_number'] : 0;
                return $numA <=> $numB;
            });
            $ids = [];
            foreach ($rawTracks as $item) {
                if (is_array($item) && isset($item['id'])) {
                    $ids[] = (int) $item['id'];
                } elseif (is_numeric($item)) {
                    $ids[] = (int) $item;
                }
            }
            if (!empty($ids)) {
                $request->merge(['track_ids' => $ids]);
            }
        }

        $validator = Validator::make($request->all(), [
            'track_ids' => 'required|array|min:1',
            'track_ids.*' => 'integer|exists:music_tracks,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid track IDs list',
                'errors' => $validator->errors(),
            ], 422);
        }

        $trackIds = $request->input('track_ids');

        DB::transaction(function () use ($album, $trackIds) {
            foreach ($trackIds as $index => $trackId) {
                MusicTrack::where('id', $trackId)->update([
                    'album_id' => $album->id,
                    'track_number' => $index + 1,
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Album tracks reordered successfully.',
            'data' => $album->fresh(['tracks']),
        ]);
    }

    /**
     * Delete an album. Sets album_id = null on associated tracks.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $album = MusicAlbum::find($id);
        if (!$album) {
            return response()->json([
                'success' => false,
                'message' => 'Music album not found',
            ], 404);
        }

        DB::transaction(function () use ($album) {
            MusicTrack::where('album_id', $album->id)->update([
                'album_id' => null,
                'track_number' => null,
            ]);
            $album->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Music album deleted successfully.',
        ]);
    }
}
