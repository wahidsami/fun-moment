<?php

namespace App\Services;

use App\LegalTerm;
use App\LegalTermVersion;
use App\User;
use App\UserTermsAcceptance;
use Illuminate\Validation\ValidationException;

class LegalTermsAcceptanceService
{
    /**
     * Authoritatively validate that the submitted version ID matches the currently
     * active, published immutable Terms & Conditions version for the specified role and language.
     *
     * @param string $role 'buyer' or 'seller'
     * @param mixed $submittedVersionId
     * @param mixed $submittedLanguage 'ar' or 'en'
     * @return LegalTermVersion
     * @throws ValidationException
     */
    public function validatePublishedTerms(string $role, $submittedVersionId, $submittedLanguage): LegalTermVersion
    {
        // 1. Validate language and version ID parameters together
        $errors = [];
        if (empty($submittedLanguage) || !in_array($submittedLanguage, [LegalTerm::LANG_AR, LegalTerm::LANG_EN], true)) {
            $errors['terms_language'] = [__('A valid terms_language (ar or en) is required.')];
        }

        if (empty($submittedVersionId) || !is_numeric($submittedVersionId) || (int) $submittedVersionId <= 0) {
            $errors['terms_version_id'] = [__('Acceptance of published Terms & Conditions is required (terms_version_id).')];
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $submittedVersionId = (int) $submittedVersionId;

        // 3. Resolve master document for this exact (role, language) combination
        $master = LegalTerm::where('role', $role)->where('language', $submittedLanguage)->first();

        if (!$master || empty($master->current_version_id)) {
            throw ValidationException::withMessages([
                'terms_version_id' => [
                    __('Terms & Conditions are not currently published or available for this account type.'),
                ],
            ]);
        }

        // 4. Resolve the active published immutable version
        $activeVersion = LegalTermVersion::find($master->current_version_id);

        if (!$activeVersion) {
            throw ValidationException::withMessages([
                'terms_version_id' => [
                    __('Active published Terms & Conditions record could not be resolved.'),
                ],
            ]);
        }

        // 5. Strict version match check: Reject stale, cross-role, cross-language, or fabricated IDs
        if ($submittedVersionId !== (int) $activeVersion->id) {
            throw ValidationException::withMessages([
                'terms_version_id' => [
                    __('The accepted Terms & Conditions version is outdated or does not match the registration role and language. Please review and accept the latest published terms.'),
                ],
            ]);
        }

        return $activeVersion;
    }

    /**
     * Persist an immutable, auditable acceptance record inside the active transaction.
     *
     * @param User $user
     * @param LegalTermVersion $activeVersion
     * @param string $role
     * @param string $language
     * @return UserTermsAcceptance
     */
    public function recordAcceptance(User $user, LegalTermVersion $activeVersion, string $role, string $language): UserTermsAcceptance
    {
        return UserTermsAcceptance::create([
            'user_id' => $user->id,
            'role' => $role,
            'legal_term_id' => $activeVersion->legal_term_id,
            'legal_term_version_id' => $activeVersion->id,
            'version_number' => (int) $activeVersion->version_number,
            'content_hash' => $activeVersion->content_hash,
            'language' => $language,
            'accepted_at' => now(),
        ]);
    }
}
