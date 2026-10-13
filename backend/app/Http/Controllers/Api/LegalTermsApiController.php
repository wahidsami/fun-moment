<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\LegalTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LegalTermsApiController extends Controller
{
    /**
     * Retrieve the currently published Terms & Conditions document for a given role and language.
     * Publicly accessible without authentication.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function current(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|string|in:buyer,seller',
            'lang' => 'required|string|in:ar,en',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Invalid role or language parameter.'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $role = $request->query('role');
        $lang = $request->query('lang');

        /** @var LegalTerm|null $term */
        $term = LegalTerm::with('currentVersion')
            ->where('role', $role)
            ->where('language', $lang)
            ->first();

        if (!$term || !$term->current_version_id || !$term->currentVersion) {
            return response()->json([
                'status' => 'unavailable',
                'message' => __('Terms & Conditions are not currently available for this role and language.'),
            ], 404);
        }

        $version = $term->currentVersion;

        return response()->json([
            'status' => 'success',
            'data' => [
                'document_id' => $term->id,
                'version_id' => $version->id,
                'version_number' => (int) $version->version_number,
                'role' => $term->role,
                'language' => $term->language,
                'title' => $version->title,
                'content' => $version->content,
                'content_hash' => $version->content_hash,
                'published_at' => $version->published_at ? $version->published_at->toIso8601String() : null,
            ],
        ]);
    }
}
