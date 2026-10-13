<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTermsAcceptance extends Model
{
    use HasFactory;

    protected $table = 'user_terms_acceptances';

    protected $fillable = [
        'user_id',
        'role',
        'legal_term_id',
        'legal_term_version_id',
        'version_number',
        'content_hash',
        'language',
        'accepted_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'legal_term_id' => 'integer',
        'legal_term_version_id' => 'integer',
        'version_number' => 'integer',
        'accepted_at' => 'datetime',
    ];

    /**
     * The user who accepted the terms.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * The master legal term document.
     */
    public function legalTerm(): BelongsTo
    {
        return $this->belongsTo(LegalTerm::class, 'legal_term_id', 'id');
    }

    /**
     * The specific immutable version that was accepted.
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(LegalTermVersion::class, 'legal_term_version_id', 'id');
    }

    /**
     * Verify that the recorded content hash matches the referenced version.
     */
    public function verifyHashAgainstVersion(): bool
    {
        return $this->version && $this->version->content_hash === $this->content_hash;
    }
}
