<?php

namespace App\Http\Controllers;

use App\AdminAuditLog;
use App\LegalTerm;
use App\LegalTermVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mews\Purifier\Facades\Purifier;

class AdminLegalTermsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:manage_cms');
    }

    /**
     * List all master legal terms documents with active versions and draft status.
     */
    public function index(): JsonResponse
    {
        $terms = LegalTerm::with(['currentVersion.publisher:id,name,username'])
            ->withCount('versions')
            ->orderBy('id')
            ->get()
            ->map(function (LegalTerm $term) {
                return [
                    'id' => $term->id,
                    'role' => $term->role,
                    'language' => $term->language,
                    'title' => $term->title,
                    'draft_title' => $term->draft_title,
                    'draft_content' => $term->draft_content,
                    'current_version_id' => $term->current_version_id,
                    'current_version' => $term->currentVersion ? [
                        'id' => $term->currentVersion->id,
                        'version_number' => $term->currentVersion->version_number,
                        'title' => $term->currentVersion->title,
                        'content' => $term->currentVersion->content,
                        'content_hash' => $term->currentVersion->content_hash,
                        'published_by' => $term->currentVersion->published_by,
                        'publisher_name' => optional($term->currentVersion->publisher)->name
                            ?? optional($term->currentVersion->publisher)->username
                            ?? 'Administrator',
                        'published_at' => $term->currentVersion->published_at
                            ? $term->currentVersion->published_at->toIso8601String()
                            : null,
                    ] : null,
                    'versions_count' => (int) $term->versions_count,
                    'updated_at' => $term->updated_at ? $term->updated_at->toIso8601String() : null,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $terms,
        ]);
    }

    /**
     * Save draft title and content without altering published versions.
     */
    public function updateDraft(Request $request, int $id): JsonResponse
    {
        $term = LegalTerm::findOrFail($id);

        $validated = $request->validate([
            'draft_title' => 'nullable|string|max:255',
            'draft_content' => 'nullable|string',
        ]);

        $rawContent = $validated['draft_content'] ?? '';
        $cleanContent = $this->sanitizeHtml($rawContent);
        $cleanTitle = !empty($validated['draft_title']) ? trim(strip_tags($validated['draft_title'])) : null;

        $term->update([
            'draft_title' => $cleanTitle,
            'draft_content' => $cleanContent,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Draft saved successfully.',
            'data' => [
                'id' => $term->id,
                'draft_title' => $term->draft_title,
                'draft_content' => $term->draft_content,
                'updated_at' => $term->updated_at ? $term->updated_at->toIso8601String() : null,
            ],
        ]);
    }

    /**
     * Publish current draft as a new immutable version.
     */
    public function publish(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            /** @var LegalTerm $term */
            $term = LegalTerm::lockForUpdate()->findOrFail($id);

            // Require non-empty title and content
            $title = trim(strip_tags((string) ($term->draft_title ?: $term->title)));
            $content = (string) $term->draft_content;

            // Strip tags to verify there is genuine substantive content
            $plainText = trim(strip_tags($content));
            if (empty($title) || empty($plainText)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot publish terms: Title and document body must not be empty.',
                ], 422);
            }

            $sanitizedContent = $this->sanitizeHtml($content);
            $contentHash = LegalTermVersion::hashContent($sanitizedContent);

            // Determine next monotonic version number for this document
            $latestVersion = (int) LegalTermVersion::where('legal_term_id', $term->id)
                ->lockForUpdate()
                ->max('version_number');
            $nextVersionNumber = $latestVersion + 1;

            $admin = Auth::guard('admin')->user();
            $adminId = optional($admin)->id;
            $adminName = optional($admin)->name ?? optional($admin)->username ?? 'Administrator';

            // Create immutable snapshot
            $version = LegalTermVersion::create([
                'legal_term_id' => $term->id,
                'version_number' => $nextVersionNumber,
                'title' => $title,
                'content' => $sanitizedContent,
                'content_hash' => $contentHash,
                'published_by' => $adminId,
                'published_at' => now(),
            ]);

            // Update master active pointer and sync draft to published state
            $term->update([
                'title' => $title,
                'draft_title' => $title,
                'draft_content' => $sanitizedContent,
                'current_version_id' => $version->id,
            ]);

            // Audit record using existing system
            AdminAuditLog::record([
                'action' => 'publish_legal_terms',
                'resource_type' => 'legal_term',
                'resource_id' => (string) $term->id,
                'details_en' => "Published version {$nextVersionNumber} for {$term->role} ({$term->language}) terms.",
                'details_ar' => "تم نشر الإصدار {$nextVersionNumber} لشروط {$term->role} ({$term->language}).",
                'new_values' => [
                    'version_id' => $version->id,
                    'version_number' => $nextVersionNumber,
                    'content_hash' => $contentHash,
                ],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Version {$nextVersionNumber} published successfully.",
                'data' => [
                    'term' => [
                        'id' => $term->id,
                        'role' => $term->role,
                        'language' => $term->language,
                        'title' => $term->title,
                        'current_version_id' => $version->id,
                    ],
                    'version' => [
                        'id' => $version->id,
                        'version_number' => $version->version_number,
                        'title' => $version->title,
                        'content' => $version->content,
                        'content_hash' => $version->content_hash,
                        'published_by' => $version->published_by,
                        'publisher_name' => $adminName,
                        'published_at' => $version->published_at ? $version->published_at->toIso8601String() : null,
                    ],
                ],
            ]);
        });
    }

    /**
     * Retrieve read-only immutable version history for a document.
     */
    public function history(int $id): JsonResponse
    {
        $term = LegalTerm::findOrFail($id);

        $versions = LegalTermVersion::where('legal_term_id', $term->id)
            ->with('publisher:id,name,username')
            ->orderByDesc('version_number')
            ->get()
            ->map(function (LegalTermVersion $v) use ($term) {
                return [
                    'id' => $v->id,
                    'legal_term_id' => $v->legal_term_id,
                    'version_number' => $v->version_number,
                    'title' => $v->title,
                    'content' => $v->content,
                    'content_hash' => $v->content_hash,
                    'published_by' => $v->published_by,
                    'publisher_name' => optional($v->publisher)->name
                        ?? optional($v->publisher)->username
                        ?? 'Administrator',
                    'published_at' => $v->published_at ? $v->published_at->toIso8601String() : null,
                    'is_current' => (int) $v->id === (int) $term->current_version_id,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $versions,
        ]);
    }

    /**
     * Sanitize HTML content: whitelist safe legal document formatting tags,
     * strip scripts/styles/iframes/embeds/objects, and remove any event handlers.
     */
    protected function sanitizeHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        // 1. Remove dangerous script, style, and executable container tags
        $cleaned = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
        $cleaned = preg_replace('#<style(.*?)>(.*?)</style>#is', '', $cleaned);
        $cleaned = preg_replace('#<iframe(.*?)>(.*?)</iframe>#is', '', $cleaned);
        $cleaned = preg_replace('#<object(.*?)>(.*?)</object>#is', '', $cleaned);
        $cleaned = preg_replace('#<embed(.*?)>(.*?)</embed>#is', '', $cleaned);
        $cleaned = preg_replace('#<form(.*?)>(.*?)</form>#is', '', $cleaned);

        // 2. Strip all inline JavaScript event handlers (e.g., onload, onclick, onerror)
        $cleaned = preg_replace('/\s+on[a-zA-Z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $cleaned);

        // 3. Disallow javascript: and vbscript: URIs in attributes
        $cleaned = preg_replace('/href\s*=\s*["\']\s*(javascript|vbscript|data):[^"\']*["\']/i', 'href="#"', $cleaned);

        // 4. Allowed formatting tags safe for legal contracts and Flutter HTML renderer
        $allowedTags = '<h1><h2><h3><h4><h5><h6><p><br><hr><strong><b><em><i><u><s><blockquote><ul><ol><li><a><span><div><table><thead><tbody><tr><th><td>';
        $cleaned = strip_tags($cleaned, $allowedTags);

        // 5. If Purifier package is bound, pass through clean() for XHTML normalization
        if (class_exists(Purifier::class)) {
            try {
                $cleaned = Purifier::clean($cleaned);
            } catch (\Throwable $e) {
                // Graceful fallback to regex-sanitized output if Purifier config is absent
            }
        }

        return trim($cleaned);
    }
}
