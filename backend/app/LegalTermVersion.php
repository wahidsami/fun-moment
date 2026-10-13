<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalTermVersion extends Model
{
    use HasFactory;

    protected $table = 'legal_term_versions';

    protected $fillable = [
        'legal_term_id',
        'version_number',
        'title',
        'content',
        'content_hash',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'legal_term_id' => 'integer',
        'version_number' => 'integer',
        'published_by' => 'integer',
        'published_at' => 'datetime',
    ];

    /**
     * Parent master document registry record.
     */
    public function legalTerm(): BelongsTo
    {
        return $this->belongsTo(LegalTerm::class, 'legal_term_id', 'id');
    }

    /**
     * Administrator who published this version.
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'published_by', 'id');
    }

    /**
     * User acceptance records tied to this specific immutable version.
     */
    public function acceptances(): HasMany
    {
        return $this->hasMany(UserTermsAcceptance::class, 'legal_term_version_id', 'id');
    }

    /**
     * Compute SHA-256 hash for canonical content.
     */
    public static function hashContent(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * Verify stored content hash matches actual canonical content.
     */
    public function verifyHash(): bool
    {
        return hash('sha256', (string) $this->content) === $this->content_hash;
    }

    /**
     * Check if this version is currently the active published version.
     */
    public function isCurrent(): bool
    {
        return $this->legalTerm && (int) $this->legalTerm->current_version_id === (int) $this->id;
    }
}
