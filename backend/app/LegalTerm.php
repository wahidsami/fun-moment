<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalTerm extends Model
{
    use HasFactory;

    public const ROLE_BUYER = 'buyer';
    public const ROLE_SELLER = 'seller';

    public const LANG_AR = 'ar';
    public const LANG_EN = 'en';

    protected $table = 'legal_terms';

    protected $fillable = [
        'role',
        'language',
        'title',
        'draft_title',
        'draft_content',
        'current_version_id',
    ];

    protected $casts = [
        'current_version_id' => 'integer',
    ];

    /**
     * Get all immutable published versions for this document.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(LegalTermVersion::class, 'legal_term_id', 'id')
            ->orderBy('version_number', 'desc');
    }

    /**
     * Get the currently active published immutable version.
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(LegalTermVersion::class, 'current_version_id', 'id');
    }

    /**
     * Alias for currentVersion.
     */
    public function activeVersion(): BelongsTo
    {
        return $this->currentVersion();
    }

    /**
     * Get all user acceptance records for this document.
     */
    public function acceptances(): HasMany
    {
        return $this->hasMany(UserTermsAcceptance::class, 'legal_term_id', 'id');
    }

    public function isBuyer(): bool
    {
        return $this->role === self::ROLE_BUYER;
    }

    public function isSeller(): bool
    {
        return $this->role === self::ROLE_SELLER;
    }

    public function isArabic(): bool
    {
        return $this->language === self::LANG_AR;
    }

    public function isEnglish(): bool
    {
        return $this->language === self::LANG_EN;
    }

    /**
     * Check if this document has an active published version.
     */
    public function hasPublishedVersion(): bool
    {
        return !is_null($this->current_version_id);
    }

    /**
     * Find a master legal term record by role and language.
     */
    public static function findByRoleAndLanguage(string $role, string $language): ?self
    {
        return static::where('role', $role)->where('language', $language)->first();
    }
}
