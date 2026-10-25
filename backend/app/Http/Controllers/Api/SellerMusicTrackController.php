<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMusicAudioJob;
use App\MusicGenre;
use App\MusicOffer;
use App\MusicSetting;
use App\MusicTrack;
use App\Services\Music\AudioValidationService;
use App\Services\Music\MusicStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SellerMusicTrackController extends Controller
{
    protected AudioValidationService $audioValidator;
    protected MusicStorageService $storageService;

    public function __construct(
        AudioValidationService $audioValidator,
        MusicStorageService $storageService
    ) {
        $this->audioValidator = $audioValidator;
        $this->storageService = $storageService;
    }

    /**
     * Get seller music configuration options (runtime limits, allowed formats, offer switches).
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'marketplace_enabled' => MusicSetting::isMarketplaceEnabled(),
                'seller_submissions_enabled' => MusicSetting::areSellerSubmissionsEnabled(),
                'seller_tracks_require_approval' => (bool) (int) MusicSetting::get('seller_tracks_require_approval', 1),
                'allowed_audio_formats' => MusicSetting::getAllowedAudioFormats(),
                'max_audio_upload_mb' => MusicSetting::getEffectiveMaxUploadMb(),
                'default_rental_duration_days' => (int) MusicSetting::get('default_rental_duration_days', 7),
                'paid_preview_duration_seconds' => MusicSetting::getPaidPreviewDurationSeconds(),
                'offers' => [
                    'free' => MusicSetting::isOfferTypeEnabled('free'),
                    'rent' => MusicSetting::isOfferTypeEnabled('rent'),
                    'sale' => MusicSetting::isOfferTypeEnabled('sale'),
                ],
                'rights_declarations' => [
                    [
                        'key' => MusicTrack::RIGHTS_ORIGINAL,
                        'label_en' => '100% Original Composition & Master Recording',
                        'label_ar' => 'تأليف أصلي وتسجيل ماستر بنسبة 100%',
                    ],
                    [
                        'key' => MusicTrack::RIGHTS_REMIX,
                        'label_en' => 'Authorized Remix / Licensed Production',
                        'label_ar' => 'ريمكس مرخص / إنتاج بموجب ترخيص معتمد',
                    ],
                    [
                        'key' => MusicTrack::RIGHTS_PUBLIC_DOMAIN,
                        'label_en' => 'Public Domain / Creative Commons Material',
                        'label_ar' => 'ملكية عامة / مواد المشاع الإبداعي',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get list of active genres for dropdown selection.
     */
    public function genres(): JsonResponse
    {
        $genres = MusicGenre::query()
            ->active()
            ->ordered()
            ->get(['id', 'name_en', 'name_ar', 'slug', 'sort_order', 'icon']);

        return response()->json([
            'success' => true,
            'data' => $genres,
        ]);
    }

    /**
     * Resolve authenticated seller provider user or return authorization error.
     */
    protected function resolveSeller(Request $request): array
    {
        $user = $request->user() ?? Auth::user() ?? Auth::guard('sanctum')->user();
        if (!$user) {
            return [
                null,
                response()->json([
                    'success' => false,
                    'error_code' => 'UNAUTHENTICATED',
                    'message' => 'Unauthenticated',
                ], 401),
            ];
        }

        if (!$user->isProvider() || ($user->user_status !== null && (int) $user->user_status === 0)) {
            return [
                null,
                response()->json([
                    'success' => false,
                    'error_code' => 'FORBIDDEN',
                    'message' => 'Unauthorized: active provider account required',
                ], 403),
            ];
        }

        return [$user, null];
    }

    /**
     * List all music tracks submitted or owned by the authenticated seller.
     */
    public function index(Request $request): JsonResponse
    {
        [$user, $err] = $this->resolveSeller($request);
        if ($err) {
            return $err;
        }

        $query = MusicTrack::query()
            ->where('seller_id', $user->id)
            ->with(['genre', 'secondaryGenres', 'offers']);

        // Filter by submission / publication status
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // Filter by media processing status
        if ($request->has('media_status') && !empty($request->media_status)) {
            $query->where('media_status', $request->media_status);
        }

        // Filter by genre
        if ($request->has('genre_id') && is_numeric($request->genre_id)) {
            $query->where('genre_id', (int) $request->genre_id);
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

        $perPage = max(5, min(50, (int) $request->input('per_page', 15)));
        $tracks = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'current_page' => $tracks->currentPage(),
                'data' => $tracks->items(),
                'per_page' => $tracks->perPage(),
                'total' => $tracks->total(),
                'last_page' => $tracks->lastPage(),
            ],
            'meta' => [
                'current_page' => $tracks->currentPage(),
                'per_page' => $tracks->perPage(),
                'total' => $tracks->total(),
                'last_page' => $tracks->lastPage(),
            ],
        ]);
    }

    /**
     * Retrieve a specific track belonging to the authenticated seller.
     */
    public function show($requestOrId, ?int $id = null): JsonResponse
    {
        $request = $requestOrId instanceof Request ? $requestOrId : request();
        $id = $requestOrId instanceof Request ? $id : (int) $requestOrId;

        [$user, $err] = $this->resolveSeller($request);
        if ($err) {
            return $err;
        }

        $track = MusicTrack::query()
            ->where('seller_id', $user->id)
            ->with(['genre', 'secondaryGenres', 'offers'])
            ->find($id);

        if (!$track) {
            return response()->json([
                'success' => false,
                'message' => 'Music track not found or not owned by authenticated seller',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $track,
        ]);
    }

    /**
     * Submit a new music track for processing and administration review.
     */
    public function store(Request $request): JsonResponse
    {
        [$user, $err] = $this->resolveSeller($request);
        if ($err) {
            return $err;
        }

        // 1. Enforce global marketplace availability
        if (!MusicSetting::isMarketplaceEnabled()) {
            return response()->json([
                'success' => false,
                'error_code' => 'MARKETPLACE_DISABLED',
                'message' => 'Music marketplace submissions are temporarily unavailable.',
            ], 403);
        }

        // 2. Enforce seller submissions switch
        if (!MusicSetting::areSellerSubmissionsEnabled()) {
            return response()->json([
                'success' => false,
                'error_code' => 'SUBMISSIONS_DISABLED',
                'message' => 'Seller music submissions are currently disabled by platform administrators.',
            ], 403);
        }

        // Normalization: allow 'title' as alias for 'title_en' / 'title_ar'
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
        if ($request->has('agree_rights_declaration') && !$request->has('rights_agreement_accepted')) {
            $request->merge(['rights_agreement_accepted' => $request->input('agree_rights_declaration')]);
        }
        if ($request->has('rights_declaration') && !$request->has('rights_declaration_type')) {
            $type = $request->input('rights_declaration');
            if ($type === 'exclusive') $type = MusicTrack::RIGHTS_ORIGINAL;
            if ($type === 'non_exclusive') $type = MusicTrack::RIGHTS_REMIX;
            $request->merge(['rights_declaration_type' => $type]);
        }
        if ($request->has('secondary_genres') && !$request->has('secondary_genre_ids')) {
            $sec = $request->input('secondary_genres');
            if (is_array($sec)) {
                $secIds = [];
                foreach ($sec as $s) {
                    if (is_numeric($s)) {
                        $secIds[] = (int) $s;
                    } else {
                        $g = MusicGenre::where('slug', $s)->orWhere('name_en', $s)->first();
                        if ($g) $secIds[] = $g->id;
                    }
                }
                $request->merge(['secondary_genre_ids' => $secIds]);
            }
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
            'bpm' => 'nullable|integer|min:20|max:300',
            'track_key' => 'nullable|string|max:16',
            'rights_declaration_type' => 'required|string|in:original_composition,licensed_remix,public_domain,exclusive,non_exclusive',
            'rights_agreement_accepted' => 'required',
            'rights_notes' => 'nullable|string|max:1000',
            'is_draft' => 'nullable|boolean',
            'audio_file' => 'required|file',
            'cover_image' => 'nullable|file|image|mimes:jpeg,png,webp,jpg|max:5120',
            'offers' => 'required|array|min:1',
            'offers.*.offer_type' => 'required|in:free,rent,sale',
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

        // Verify rights agreement acceptance boolean value
        $rawRights = $request->input('rights_agreement_accepted');
        $rightsAccepted = (filter_var($rawRights, FILTER_VALIDATE_BOOLEAN) || $rawRights === '1' || $rawRights === 1) && $rawRights !== '0' && $rawRights !== 0;
        if (!$rightsAccepted) {
            return response()->json([
                'success' => false,
                'error_code' => 'RIGHTS_NOT_ACCEPTED',
                'message' => 'You must acknowledge and accept the rights declaration and terms before submitting music.',
                'errors' => [
                    'rights_agreement_accepted' => ['Rights agreement acceptance is mandatory.'],
                ],
            ], 422);
        }

        // 3. Validate Commercial Offers against Platform Enabled Switches
        $offersInput = $request->input('offers', []);
        $configuredOfferTypes = [];

        foreach ($offersInput as $idx => $offerData) {
            $type = $offerData['offer_type'] ?? null;
            if (!$type) continue;

            if (in_array($type, $configuredOfferTypes, true)) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'DUPLICATE_OFFER_TYPE',
                    'message' => "Offer type '{$type}' cannot be configured more than once per track.",
                ], 422);
            }
            $configuredOfferTypes[] = $type;

            if (!MusicSetting::isOfferTypeEnabled($type)) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'OFFER_TYPE_DISABLED',
                    'message' => "The commercial offer type '{$type}' is currently disabled on this platform.",
                ], 422);
            }

            if ($type === MusicOffer::OFFER_TYPE_FREE) {
                if (isset($offerData['price']) && (float) $offerData['price'] > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Free streaming offers cannot charge a positive price.',
                    ], 422);
                }
            } elseif ($type === MusicOffer::OFFER_TYPE_RENT) {
                $rentPrice = (float) ($offerData['price'] ?? 0);
                if ($rentPrice <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Rent offers require a valid price greater than zero.',
                    ], 422);
                }
            } elseif ($type === MusicOffer::OFFER_TYPE_SALE) {
                $salePrice = (float) ($offerData['price'] ?? 0);
                if ($salePrice <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sale offers require a valid price greater than zero.',
                    ], 422);
                }
            }
        }

        // 4. Validate Audio File Container & Magic Bytes
        $audioFile = $request->file('audio_file');
        $validationResult = $this->audioValidator->validate($audioFile);

        if (!$validationResult->isValid()) {
            return response()->json([
                'success' => false,
                'error_code' => 'AUDIO_VALIDATION_FAILED',
                'message' => $validationResult->getError(),
                'errors' => [
                    'audio_file' => [$validationResult->getError()],
                ],
            ], 422);
        }

        // 5. Store Master Audio securely on Private Disk
        $storedMaster = null;
        $coverPath = null;

        try {
            $storedMaster = $this->storageService->storeMaster(
                $audioFile,
                $audioFile->getClientOriginalName(),
                $validationResult->getFormat()
            );

            // 6. Handle Cover Artwork Upload (Public asset for mobile and web display)
            if ($request->hasFile('cover_image')) {
                $coverFile = $request->file('cover_image');
                $coverExt = $coverFile->getClientOriginalExtension() ?: 'jpg';
                $coverName = 'cover_' . Str::uuid()->toString() . '.' . $coverExt;
                $coverPath = $coverFile->storeAs('music_covers', $coverName, 'public');
            }

            // 7. Atomic Database Creation
            $track = DB::transaction(function () use ($request, $user, $validationResult, $storedMaster, $coverPath, $offersInput) {
                $isDraft = filter_var($request->input('is_draft', false), FILTER_VALIDATE_BOOLEAN);

                // Initial status: if draft, status is 'draft'; otherwise 'pending' review
                $initialStatus = $isDraft ? MusicTrack::STATUS_DRAFT : MusicTrack::STATUS_PENDING;

                $titleEn = trim($request->input('title_en'));
                $titleAr = trim($request->input('title_ar') ?: $titleEn);
                $artistName = trim($request->input('artist_name') ?: $user->name);

                $track = MusicTrack::create([
                    'title_en' => $titleEn,
                    'title_ar' => $titleAr,
                    'artist_name' => $artistName,
                    'owner_type' => MusicTrack::OWNER_TYPE_SELLER,
                    'seller_id' => $user->id,
                    'submitted_by_user_id' => $user->id,
                    'original_creator_name' => $artistName,
                    'genre_id' => (int) $request->input('genre_id'),
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
                    'rights_declaration_type' => $request->input('rights_declaration_type'),
                    'rights_agreement_accepted' => true,
                    'rights_accepted_by_user_id' => $user->id,
                    'rights_accepted_by_name' => $user->name,
                    'rights_notes' => $request->input('rights_notes'),
                    'declared_at' => now(),
                    'declared_ip' => $request->ip(),
                ]);

                // Sync secondary genres
                $secondaryGenreIds = $request->input('secondary_genre_ids', []);
                if (is_array($secondaryGenreIds)) {
                    $track->syncGenres((int) $request->input('genre_id'), $secondaryGenreIds);
                }

                // Create offers
                foreach ($offersInput as $offerData) {
                    $type = $offerData['offer_type'];
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

            // 8. Dispatch Background Audio Processing Pipeline
            ProcessMusicAudioJob::dispatch($track->id);

            $track->load(['genre', 'secondaryGenres', 'offers']);

            return response()->json([
                'success' => true,
                'message' => 'Music track submitted successfully. Audio transcoding and preview generation queued.',
                'data' => $track,
            ], 201);
        } catch (\Throwable $e) {
            // Safe cleanup on failure
            if ($storedMaster && isset($storedMaster['storage_path'])) {
                try {
                    $this->storageService->deleteFile($storedMaster['storage_path']);
                } catch (\Throwable $ignored) {}
            }
            if ($coverPath) {
                try {
                    Storage::disk('public')->delete($coverPath);
                } catch (\Throwable $ignored) {}
            }

            Log::error("Seller music submission error: " . $e->getMessage(), [
                'exception' => $e,
                'seller_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving the music submission. Please try again.',
            ], 500);
        }
    }

    /**
     * Update metadata, offers, or replace audio for an existing track owned by the authenticated seller.
     */
    public function update($requestOrId, $idOrNull = null): JsonResponse
    {
        $request = $requestOrId instanceof Request ? $requestOrId : request();
        $id = $requestOrId instanceof Request ? (int) $idOrNull : (int) $requestOrId;

        [$user, $err] = $this->resolveSeller($request);
        if ($err) {
            return $err;
        }

        $track = MusicTrack::where('seller_id', $user->id)->find($id);
        if (!$track) {
            return response()->json([
                'success' => false,
                'message' => 'Music track not found or not owned by authenticated seller',
            ], 404);
        }

        // Check policy: only draft, pending, or rejected tracks can be edited
        if (!$user->can('update', $track)) {
            return response()->json([
                'success' => false,
                'error_code' => 'EDIT_RESTRICTED',
                'message' => 'Approved or suspended tracks cannot be edited directly without administrator review.',
            ], 403);
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
            'title_en' => 'sometimes|required|string|max:191',
            'title_ar' => 'nullable|string|max:191',
            'artist_name' => 'nullable|string|max:191',
            'description_en' => 'nullable|string|max:2000',
            'description_ar' => 'nullable|string|max:2000',
            'genre_id' => 'sometimes|required|integer|exists:music_genres,id',
            'secondary_genre_ids' => 'nullable|array',
            'secondary_genre_ids.*' => 'integer|exists:music_genres,id',
            'bpm' => 'nullable|integer|min:20|max:300',
            'track_key' => 'nullable|string|max:16',
            'rights_declaration_type' => 'sometimes|required|string|in:original_composition,licensed_remix,public_domain',
            'rights_notes' => 'nullable|string|max:1000',
            'resubmit' => 'nullable|boolean',
            'audio_file' => 'nullable|file',
            'cover_image' => 'nullable|file|image|mimes:jpeg,png,webp,jpg|max:5120',
            'offers' => 'sometimes|required|array|min:1',
            'offers.*.offer_type' => 'required|in:free,rent,sale',
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

        $newMaster = null;
        $newCoverPath = null;

        try {
            // Handle audio file replacement if provided
            if ($request->hasFile('audio_file')) {
                $audioFile = $request->file('audio_file');
                $valResult = $this->audioValidator->validate($audioFile);
                if (!$valResult->isValid()) {
                    return response()->json([
                        'success' => false,
                        'message' => $valResult->getError(),
                    ], 422);
                }

                $newMaster = $this->storageService->storeMaster(
                    $audioFile,
                    $audioFile->getClientOriginalName(),
                    $valResult->getFormat()
                );
            }

            // Handle cover image replacement if provided
            if ($request->hasFile('cover_image')) {
                $coverFile = $request->file('cover_image');
                $coverExt = $coverFile->getClientOriginalExtension() ?: 'jpg';
                $coverName = 'cover_' . Str::uuid()->toString() . '.' . $coverExt;
                $newCoverPath = $coverFile->storeAs('music_covers', $coverName, 'public');
            }

            DB::transaction(function () use ($request, $track, $newMaster, $newCoverPath) {
                $fieldsToUpdate = [];

                if ($request->has('title_en')) $fieldsToUpdate['title_en'] = trim($request->input('title_en'));
                if ($request->has('title_ar')) $fieldsToUpdate['title_ar'] = trim($request->input('title_ar'));
                if ($request->has('artist_name')) $fieldsToUpdate['artist_name'] = trim($request->input('artist_name'));
                if ($request->has('description_en')) $fieldsToUpdate['description_en'] = $request->input('description_en');
                if ($request->has('description_ar')) $fieldsToUpdate['description_ar'] = $request->input('description_ar');
                if ($request->has('bpm')) $fieldsToUpdate['bpm'] = $request->input('bpm');
                if ($request->has('track_key')) $fieldsToUpdate['track_key'] = $request->input('track_key');
                if ($request->has('rights_declaration_type')) $fieldsToUpdate['rights_declaration_type'] = $request->input('rights_declaration_type');
                if ($request->has('rights_notes')) $fieldsToUpdate['rights_notes'] = $request->input('rights_notes');

                if ($newCoverPath) {
                    $fieldsToUpdate['cover_image'] = $newCoverPath;
                }

                if ($newMaster) {
                    $fieldsToUpdate['audio_path_original'] = $newMaster['storage_path'];
                    $fieldsToUpdate['original_filename'] = $newMaster['original_filename'];
                    $fieldsToUpdate['file_size_bytes'] = $newMaster['file_size'];
                    $fieldsToUpdate['media_status'] = MusicTrack::MEDIA_STATUS_PENDING;
                    $fieldsToUpdate['media_error'] = null;
                    $fieldsToUpdate['status'] = MusicTrack::STATUS_PENDING;
                    $fieldsToUpdate['rejection_reason'] = null;
                } elseif ($track->status === MusicTrack::STATUS_REJECTED && $request->input('resubmit')) {
                    $fieldsToUpdate['status'] = MusicTrack::STATUS_PENDING;
                    $fieldsToUpdate['rejection_reason'] = null;
                }

                if (!empty($fieldsToUpdate)) {
                    $track->update($fieldsToUpdate);
                }

                if ($request->has('genre_id')) {
                    $primaryId = (int) $request->input('genre_id');
                    $secondaryIds = $request->input('secondary_genre_ids', []);
                    $track->syncGenres($primaryId, is_array($secondaryIds) ? $secondaryIds : []);
                }

                // Update offers if provided
                if ($request->has('offers')) {
                    $offersInput = $request->input('offers', []);
                    // Remove existing offers and replace with newly configured set
                    $track->offers()->delete();

                    foreach ($offersInput as $offerData) {
                        $type = $offerData['offer_type'];
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
                }
            });

            if ($newMaster) {
                ProcessMusicAudioJob::dispatch($track->id);
            }

            $track->load(['genre', 'secondaryGenres', 'offers']);

            return response()->json([
                'success' => true,
                'message' => 'Track updated successfully.',
                'data' => $track,
            ]);
        } catch (\Throwable $e) {
            Log::error("Seller track update error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the track.',
            ], 500);
        }
    }

    /**
     * Retry audio processing pipeline for a failed track.
     */
    public function retryProcessing($requestOrId, $idOrNull = null): JsonResponse
    {
        $request = $requestOrId instanceof Request ? $requestOrId : request();
        $id = $requestOrId instanceof Request ? (int) $idOrNull : (int) $requestOrId;

        [$user, $err] = $this->resolveSeller($request);
        if ($err) {
            return $err;
        }

        $track = MusicTrack::where('seller_id', $user->id)->find($id);
        if (!$track) {
            return response()->json([
                'success' => false,
                'message' => 'Track not found',
            ], 404);
        }

        if ($track->media_status !== MusicTrack::MEDIA_STATUS_FAILED) {
            return response()->json([
                'success' => false,
                'message' => 'Track is not in a failed processing state.',
            ], 400);
        }

        $track->update([
            'media_status' => MusicTrack::MEDIA_STATUS_PENDING,
            'media_error' => null,
        ]);

        ProcessMusicAudioJob::dispatch($track->id);

        return response()->json([
            'success' => true,
            'message' => 'Track audio processing re-queued successfully.',
            'data' => [
                'track_id' => $track->id,
                'media_status' => $track->media_status,
            ],
        ]);
    }

    /**
     * Delete a track belonging to the authenticated seller (guarded by order integrity).
     */
    public function destroy($requestOrId, $idOrNull = null): JsonResponse
    {
        $request = $requestOrId instanceof Request ? $requestOrId : request();
        $id = $requestOrId instanceof Request ? (int) $idOrNull : (int) $requestOrId;

        [$user, $err] = $this->resolveSeller($request);
        if ($err) {
            return $err;
        }

        $track = MusicTrack::where('seller_id', $user->id)->find($id);
        if (!$track) {
            return response()->json([
                'success' => false,
                'message' => 'Track not found',
            ], 404);
        }

        if (!$user->can('delete', $track)) {
            return response()->json([
                'success' => false,
                'error_code' => 'DELETE_RESTRICTED',
                'message' => 'Cannot delete track: active or completed financial orders exist, or track is approved.',
            ], 422);
        }

        $masterPath = $track->audio_path_original;
        $previewPath = $track->audio_path_preview;
        $streamPath = $track->audio_path_stream;
        $coverPath = $track->cover_image;

        DB::transaction(function () use ($track) {
            $track->secondaryGenres()->detach();
            $track->offers()->delete();
            $track->delete();
        });

        // Clean up private audio files
        if ($masterPath) $this->storageService->deleteFile($masterPath);
        if ($previewPath) $this->storageService->deleteFile($previewPath);
        if ($streamPath) $this->storageService->deleteFile($streamPath);
        if ($coverPath) {
            try {
                Storage::disk('public')->delete($coverPath);
            } catch (\Throwable $ignored) {}
        }

        return response()->json([
            'success' => true,
            'message' => 'Track deleted successfully.',
        ]);
    }
}
