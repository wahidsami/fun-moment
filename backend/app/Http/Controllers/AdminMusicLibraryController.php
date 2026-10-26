<?php

namespace App\Http\Controllers;

use App\Admin;
use App\Jobs\ProcessMusicAudioJob;
use App\MusicAlbum;
use App\MusicGenre;
use App\MusicOffer;
use App\MusicSetting;
use App\MusicTrack;
use App\Services\Music\AudioValidationService;
use App\Services\Music\MusicAudioProcessingService;
use App\Services\Music\MusicStorageService;
use App\Services\Music\MusicStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminMusicLibraryController extends Controller
{
    protected AudioValidationService $audioValidator;
    protected MusicStorageService $storageService;
    protected MusicStreamService $streamService;

    public function __construct(
        AudioValidationService $audioValidator,
        MusicStorageService $storageService,
        MusicStreamService $streamService
    ) {
        $this->audioValidator = $audioValidator;
        $this->storageService = $storageService;
        $this->streamService = $streamService;
    }

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
     * List all platform-owned tracks (FUN MOMENT Originals).
     */
    public function index(Request $request): JsonResponse
    {
        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $query = MusicTrack::query()
            ->byAdmin()
            ->with(['genre', 'secondaryGenres', 'album', 'originalTrack:id,title_en,artist_name', 'offers', 'admin:id,name']);

        // Filter by publication status (draft, approved, suspended, all)
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by media processing status (pending, processing, ready, failed, all)
        if ($request->has('media_status') && !empty($request->media_status) && $request->media_status !== 'all') {
            $query->where('media_status', $request->media_status);
        }

        // Filter by genre
        if ($request->has('genre_id') && is_numeric($request->genre_id)) {
            $query->where('genre_id', (int) $request->genre_id);
        }

        // Filter by album
        if ($request->has('album_id') && is_numeric($request->album_id)) {
            $query->where('album_id', (int) $request->album_id);
        }

        // Search by title or artist
        if ($request->has('search') && !empty($request->search)) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'ILIKE', $search)
                  ->orWhere('title_ar', 'ILIKE', $search)
                  ->orWhere('artist_name', 'ILIKE', $search);
            });
        }

        $counts = [
            'all' => MusicTrack::byAdmin()->count(),
            'approved' => MusicTrack::byAdmin()->where('status', MusicTrack::STATUS_APPROVED)->count(),
            'draft' => MusicTrack::byAdmin()->where('status', MusicTrack::STATUS_DRAFT)->count(),
            'ready' => MusicTrack::byAdmin()->where('media_status', MusicTrack::MEDIA_STATUS_READY)->count(),
            'processing' => MusicTrack::byAdmin()->whereIn('media_status', [MusicTrack::MEDIA_STATUS_PENDING, MusicTrack::MEDIA_STATUS_PROCESSING])->count(),
            'failed' => MusicTrack::byAdmin()->where('media_status', MusicTrack::MEDIA_STATUS_FAILED)->count(),
        ];

        $perPage = max(5, min(100, (int) $request->input('per_page', 15)));
        $tracks = $query->orderBy('id', 'desc')->paginate($perPage);

        $paginatedTracks = [
            'data' => $tracks->items(),
            'current_page' => $tracks->currentPage(),
            'per_page' => $tracks->perPage(),
            'total' => $tracks->total(),
            'last_page' => $tracks->lastPage(),
        ];

        return response()->json([
            'success' => true,
            'counts' => $counts,
            'tracks' => $paginatedTracks,
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
     * Get list of active genres for administrative music catalog selection.
     */
    public function genres(Request $request): JsonResponse
    {
        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $genres = MusicGenre::query()
            ->active()
            ->ordered()
            ->get(['id', 'name_en', 'name_ar', 'slug', 'sort_order', 'icon']);

        return response()->json([
            'success' => true,
            'genres' => $genres,
            'data' => $genres,
        ]);
    }

    /**
     * Inspect a specific platform-owned track and generate preview stream ticket.
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
            ->with(['genre', 'secondaryGenres', 'album', 'originalTrack', 'remixes:id,title_en,artist_name', 'offers', 'admin'])
            ->find($id);

        if (!$track) {
            return response()->json([
                'success' => false,
                'message' => 'Music track not found',
            ], 404);
        }

        $ticket = null;
        $streamUrl = null;
        if ($track->isMediaReady() && !empty($track->audio_path_preview)) {
            $ticket = $this->streamService->createStreamTicket($admin, $track, 'full', 3600);
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
                'admin_stream_ticket' => $ticket,
            ]),
        ]);
    }

    /**
     * Upload and create a new platform-owned track (FUN MOMENT Originals).
     */
    public function store(Request $request): JsonResponse
    {
        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        // Normalize inputs
        if ($request->has('title') && !$request->has('title_en')) {
            $request->merge([
                'title_en' => $request->input('title'),
                'title_ar' => $request->input('title_ar', $request->input('title')),
            ]);
        }
        if ($request->has('description') && !$request->has('description_en')) {
            $request->merge([
                'description_en' => $request->input('description'),
                'description_ar' => $request->input('description_ar', $request->input('description')),
            ]);
        }

        // Parse JSON offers if sent via multipart form
        if ($request->has('offers') && is_string($request->input('offers'))) {
            $decodedOffers = json_decode($request->input('offers'), true);
            if (is_array($decodedOffers)) {
                $request->merge(['offers' => $decodedOffers]);
            }
        }

        // Parse secondary_genre_ids if sent as JSON string or comma-delimited
        if ($request->has('secondary_genre_ids')) {
            $sec = $request->input('secondary_genre_ids');
            if (is_string($sec)) {
                $decoded = json_decode($sec, true);
                if (is_array($decoded)) {
                    $request->merge(['secondary_genre_ids' => $decoded]);
                } elseif (str_contains($sec, ',')) {
                    $request->merge(['secondary_genre_ids' => array_map('intval', explode(',', $sec))]);
                }
            }
        }

        $validator = Validator::make($request->all(), [
            'title_en' => 'required|string|max:191',
            'title_ar' => 'nullable|string|max:191',
            'artist_name' => 'nullable|string|max:191',
            'description_en' => 'nullable|string|max:2000',
            'description_ar' => 'nullable|string|max:2000',
            'genre_id' => 'required|integer|exists:music_genres,id',
            'secondary_genre_ids' => 'nullable|array',
            'secondary_genre_ids.*' => 'integer|exists:music_genres,id',
            'album_id' => 'nullable|integer|exists:music_albums,id',
            'track_number' => 'nullable|integer|min:1|max:999',
            'original_track_id' => 'nullable|integer|exists:music_tracks,id',
            'bpm' => 'nullable|integer|min:20|max:300',
            'track_key' => 'nullable|string|max:16',
            'rights_declaration_type' => 'nullable|string|in:original_composition,licensed_remix,public_domain',
            'rights_notes' => 'nullable|string|max:1000',
            'publish_immediately' => 'nullable|boolean',
            'audio_file' => 'required|file',
            'cover_image' => 'nullable|file|image|mimes:jpeg,png,webp,jpg|max:5120',
            'offers' => 'nullable|array',
            'offers.*.offer_type' => 'required_with:offers|in:free,rent,sale',
            'offers.*.price' => 'nullable|numeric|min:0',
            'offers.*.rental_duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate audio file
        $audioFile = $request->file('audio_file');
        $validationResult = $this->audioValidator->validate($audioFile);
        if (!$validationResult->isValid()) {
            return response()->json([
                'success' => false,
                'error_code' => 'AUDIO_VALIDATION_FAILED',
                'message' => $validationResult->getError(),
                'errors' => ['audio_file' => [$validationResult->getError()]],
            ], 422);
        }

        $storedMaster = null;
        $coverPath = null;

        try {
            $storedMaster = $this->storageService->storeMaster(
                $audioFile,
                $audioFile->getClientOriginalName(),
                $validationResult->getFormat()
            );

            // Cover artwork handling
            if ($request->hasFile('cover_image')) {
                $coverFile = $request->file('cover_image');
                $coverExt = $coverFile->getClientOriginalExtension() ?: 'jpg';
                $coverName = 'cover_' . Str::uuid()->toString() . '.' . $coverExt;
                $coverPath = $coverFile->storeAs('music_covers', $coverName, 'public');
            } elseif ($request->filled('album_id')) {
                // Inherit album cover if available
                $album = MusicAlbum::find($request->input('album_id'));
                if ($album && !empty($album->cover_image)) {
                    $coverPath = $album->cover_image;
                }
            }

            // Rights declaration normalization
            $rightsType = $request->input('rights_declaration_type', MusicTrack::RIGHTS_ORIGINAL);
            if ($request->filled('original_track_id')) {
                $rightsType = MusicTrack::RIGHTS_REMIX;
            }

            $track = DB::transaction(function () use ($request, $admin, $validationResult, $storedMaster, $coverPath, $rightsType) {
                $publishImmediately = filter_var($request->input('publish_immediately', false), FILTER_VALIDATE_BOOLEAN);
                $initialStatus = MusicTrack::STATUS_DRAFT;

                $titleEn = trim($request->input('title_en'));
                $titleAr = trim($request->input('title_ar') ?: $titleEn);
                $artistName = trim($request->input('artist_name') ?: 'FUN MOMENT Originals');

                // Determine track_number if album is set but track_number is not provided
                $albumId = $request->input('album_id') ? (int) $request->input('album_id') : null;
                $trackNumber = $request->input('track_number') ? (int) $request->input('track_number') : null;
                if ($albumId && !$trackNumber) {
                    $maxOrder = MusicTrack::where('album_id', $albumId)->max('track_number') ?? 0;
                    $trackNumber = $maxOrder + 1;
                }

                $track = MusicTrack::create([
                    'title_en' => $titleEn,
                    'title_ar' => $titleAr,
                    'artist_name' => $artistName,
                    'owner_type' => MusicTrack::OWNER_TYPE_ADMIN,
                    'admin_id' => $admin->id,
                    'seller_id' => null,
                    'submitted_by_user_id' => null,
                    'original_creator_name' => $artistName,
                    'genre_id' => (int) $request->input('genre_id'),
                    'album_id' => $albumId,
                    'track_number' => $trackNumber,
                    'original_track_id' => $request->input('original_track_id') ? (int) $request->input('original_track_id') : null,
                    'cover_image' => $coverPath,
                    'audio_path_original' => $storedMaster['storage_path'],
                    'original_filename' => $storedMaster['original_filename'],
                    'file_size_bytes' => $storedMaster['file_size'],
                    'audio_format' => $validationResult->getFormat(),
                    'audio_mime_type' => $validationResult->getMimeType(),
                    'media_status' => MusicTrack::MEDIA_STATUS_PENDING,
                    'status' => $initialStatus,
                    'bpm' => $request->input('bpm'),
                    'track_key' => $request->input('track_key'),
                    'description_en' => $request->input('description_en'),
                    'description_ar' => $request->input('description_ar') ?: $request->input('description_en'),
                    'rights_declaration_type' => $rightsType,
                    'rights_agreement_accepted' => true,
                    'rights_notes' => $request->input('rights_notes'),
                    'declared_at' => now(),
                    'declared_ip' => $request->ip(),
                ]);

                // Sync secondary genres
                $secondaryGenreIds = $request->input('secondary_genre_ids', []);
                if (is_array($secondaryGenreIds)) {
                    $track->syncGenres((int) $request->input('genre_id'), $secondaryGenreIds);
                }

                // Create offers (default to free offer if none supplied)
                $offersInput = $request->input('offers', []);
                if (empty($offersInput)) {
                    $offersInput = [
                        [
                            'offer_type' => MusicOffer::OFFER_TYPE_FREE,
                            'price' => 0.00,
                        ],
                    ];
                }

                foreach ($offersInput as $offerData) {
                    $type = $offerData['offer_type'] ?? MusicOffer::OFFER_TYPE_FREE;
                    $price = $type === MusicOffer::OFFER_TYPE_FREE ? 0.00 : (float) ($offerData['price'] ?? 0);
                    $rentalDays = $type === MusicOffer::OFFER_TYPE_RENT
                        ? (int) ($offerData['rental_duration_days'] ?? MusicSetting::get('default_rental_duration_days', 7))
                        : null;

                    MusicOffer::create([
                        'track_id' => $track->id,
                        'offer_type' => $type,
                        'price' => $price,
                        'currency' => 'SAR',
                        'rental_duration_days' => $rentalDays,
                        'license_type' => MusicOffer::LICENSE_STREAM_ONLY,
                        'allow_download' => false,
                        'commercial_use' => false,
                        'remix_rights' => false,
                        'is_active' => true,
                    ]);
                }

                return $track;
            });

            // Queue processing job
            ProcessMusicAudioJob::dispatch($track->id);

            // If publish_immediately was requested, check if synchronous processing is viable or leave queued
            $publishImmediately = filter_var($request->input('publish_immediately', false), FILTER_VALIDATE_BOOLEAN);
            if ($publishImmediately && app()->environment(['testing', 'local'])) {
                try {
                    $processingService = app(MusicAudioProcessingService::class);
                    $processingService->processTrackAudio($track);
                    if ($track->fresh()->isMediaReady()) {
                        $track->update([
                            'status' => MusicTrack::STATUS_APPROVED,
                            'reviewed_by_admin_id' => $admin->id,
                            'reviewed_at' => now(),
                            'released_at' => now(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning("Immediate transcode attempt encountered notice: " . $e->getMessage());
                }
            }

            $track->load(['genre', 'secondaryGenres', 'album', 'offers']);

            return response()->json([
                'success' => true,
                'message' => 'Platform music track uploaded successfully. Audio processing queued.',
                'data' => $track,
            ], 201);
        } catch (\Throwable $e) {
            if ($storedMaster && isset($storedMaster['storage_path'])) {
                try {
                    $this->storageService->deleteFile($storedMaster['storage_path']);
                } catch (\Throwable $ignored) {}
            }
            if ($coverPath && !str_contains($coverPath, 'storage/')) {
                try {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($coverPath);
                } catch (\Throwable $ignored) {}
            }

            Log::error("Admin music upload failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update metadata of an existing platform-owned track.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $track = MusicTrack::byAdmin()->find($id);
        if (!$track) {
            return response()->json([
                'success' => false,
                'message' => 'Platform music track not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title_en' => 'sometimes|required|string|max:191',
            'title_ar' => 'nullable|string|max:191',
            'artist_name' => 'nullable|string|max:191',
            'description_en' => 'nullable|string|max:2000',
            'description_ar' => 'nullable|string|max:2000',
            'genre_id' => 'sometimes|required|integer|exists:music_genres,id',
            'secondary_genre_ids' => 'nullable|array',
            'secondary_genre_ids.*' => 'integer|exists:music_genres,id',
            'album_id' => 'nullable|integer|exists:music_albums,id',
            'track_number' => 'nullable|integer|min:1|max:999',
            'original_track_id' => 'nullable|integer|exists:music_tracks,id',
            'bpm' => 'nullable|integer|min:20|max:300',
            'track_key' => 'nullable|string|max:16',
            'rights_declaration_type' => 'nullable|string|in:original_composition,licensed_remix,public_domain',
            'rights_notes' => 'nullable|string|max:1000',
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
        foreach (['title_en', 'title_ar', 'artist_name', 'description_en', 'description_ar', 'bpm', 'track_key', 'rights_declaration_type', 'rights_notes'] as $field) {
            if ($request->has($field)) {
                $updates[$field] = $request->input($field);
            }
        }

        if ($request->has('album_id')) {
            $updates['album_id'] = $request->input('album_id') ? (int) $request->input('album_id') : null;
            if ($updates['album_id'] && empty($track->cover_image) && !$request->hasFile('cover_image')) {
                $album = MusicAlbum::find($updates['album_id']);
                if ($album && !empty($album->cover_image)) {
                    $updates['cover_image'] = $album->cover_image;
                }
            }
        }
        if ($request->has('track_number')) {
            $updates['track_number'] = $request->input('track_number') ? (int) $request->input('track_number') : null;
        }
        if ($request->has('original_track_id')) {
            $updates['original_track_id'] = $request->input('original_track_id') ? (int) $request->input('original_track_id') : null;
            if ($updates['original_track_id']) {
                $updates['rights_declaration_type'] = MusicTrack::RIGHTS_REMIX;
            }
        }

        if ($request->hasFile('cover_image')) {
            $coverFile = $request->file('cover_image');
            $coverExt = $coverFile->getClientOriginalExtension() ?: 'jpg';
            $coverName = 'cover_' . Str::uuid()->toString() . '.' . $coverExt;
            $updates['cover_image'] = $coverFile->storeAs('music_covers', $coverName, 'public');
        }

        DB::transaction(function () use ($track, $updates, $request) {
            if (!empty($updates)) {
                $track->update($updates);
            }

            if ($request->has('genre_id')) {
                $genreId = (int) $request->input('genre_id');
                $secondaryIds = $request->input('secondary_genre_ids', []);
                $track->syncGenres($genreId, is_array($secondaryIds) ? $secondaryIds : []);
            } elseif ($request->has('secondary_genre_ids')) {
                $track->syncGenres($track->genre_id, (array) $request->input('secondary_genre_ids'));
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Platform track metadata updated successfully',
            'data' => $track->fresh(['genre', 'secondaryGenres', 'album', 'originalTrack', 'offers']),
        ]);
    }

    /**
     * Publish a platform-owned track to the public catalog once media processing is ready.
     */
    public function publish(Request $request, int $id): JsonResponse
    {
        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $track = MusicTrack::byAdmin()->with('offers')->find($id);
        if (!$track) {
            return response()->json([
                'success' => false,
                'message' => 'Platform music track not found',
            ], 404);
        }

        if (!$track->isMediaReady()) {
            return response()->json([
                'success' => false,
                'error_code' => 'MEDIA_NOT_READY',
                'message_en' => "Cannot publish track: audio media processing status is '{$track->media_status}'. Must be 'ready'.",
                'message_ar' => "لا يمكن نشر المسار: معالجة الوسائط ليست جاهزة بعد. الحالة: {$track->media_status}",
            ], 422);
        }

        // Ensure at least one active offer exists
        if ($track->activeOffers()->count() === 0) {
            MusicOffer::create([
                'track_id' => $track->id,
                'offer_type' => MusicOffer::OFFER_TYPE_FREE,
                'price' => 0.00,
                'currency' => 'SAR',
                'license_type' => MusicOffer::LICENSE_STREAM_ONLY,
                'allow_download' => false,
                'commercial_use' => false,
                'remix_rights' => false,
                'is_active' => true,
            ]);
        }

        $track->update([
            'status' => MusicTrack::STATUS_APPROVED,
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at' => now(),
            'released_at' => $track->released_at ?: now(),
        ]);

        return response()->json([
            'success' => true,
            'message_en' => "Track '{$track->title_en}' published successfully to the music catalog.",
            'message_ar' => "تم نشر المسار '{$track->title_ar}' بنجاح في دليل الموسيقى.",
            'data' => $track->fresh(['genre', 'secondaryGenres', 'album', 'offers']),
        ]);
    }

    /**
     * Unpublish a track, moving it back to draft status so it is removed from public catalog.
     */
    public function unpublish(Request $request, int $id): JsonResponse
    {
        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $track = MusicTrack::byAdmin()->find($id);
        if (!$track) {
            return response()->json([
                'success' => false,
                'message' => 'Platform music track not found',
            ], 404);
        }

        $track->update([
            'status' => MusicTrack::STATUS_DRAFT,
        ]);

        return response()->json([
            'success' => true,
            'message_en' => "Track '{$track->title_en}' unpublished and returned to draft.",
            'message_ar' => "تم إلغاء نشر المسار وإعادته لحالة المسودة.",
            'data' => $track->fresh(['genre', 'secondaryGenres', 'album', 'offers']),
        ]);
    }

    /**
     * Safe delete or archive a platform-owned track.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        [$admin, $err] = $this->resolveAdmin($request);
        if ($err) {
            return $err;
        }

        $track = MusicTrack::byAdmin()->find($id);
        if (!$track) {
            return response()->json([
                'success' => false,
                'message' => 'Platform music track not found',
            ], 404);
        }

        // Integrity safeguard: If track has completed orders or entitlements, suspend instead of hard delete
        if ($track->orders()->exists() || $track->entitlements()->exists()) {
            $track->update([
                'status' => MusicTrack::STATUS_SUSPENDED,
                'rejection_reason' => 'Archived by platform administrator',
            ]);
            $track->offers()->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'archived' => true,
                'message_en' => "Track has historical purchase or entitlement records; safely suspended from catalog rather than hard-deleted.",
                'message_ar' => "تم تعليق المسار بأمان لوجود سجلات طلبات ومشتريات مرتبطة به حفاظاً على سلامة البيانات.",
            ]);
        }

        // Otherwise perform clean deletion
        try {
            if (!empty($track->audio_path_original)) {
                $this->storageService->deleteFile($track->audio_path_original);
            }
            if (!empty($track->audio_path_preview)) {
                $this->storageService->deleteFile($track->audio_path_preview);
            }
            if (!empty($track->audio_path_stream) && $track->audio_path_stream !== $track->audio_path_original) {
                $this->storageService->deleteFile($track->audio_path_stream);
            }
        } catch (\Throwable $e) {
            Log::warning("Notice deleting storage files for track #{$id}: " . $e->getMessage());
        }

        $track->delete();

        return response()->json([
            'success' => true,
            'message_en' => 'Platform track deleted successfully.',
            'message_ar' => 'تم حذف المسار بنجاح.',
        ]);
    }
}
