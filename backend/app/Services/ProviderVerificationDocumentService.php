<?php

namespace App\Services;

use App\Admin;
use App\SellerVerify;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProviderVerificationDocumentService
{
    public const DISK = 'verification_docs';

    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/jpg',
    ];

    public const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'exe', 'sh', 'bat', 'cmd', 'bin', 'dll', 'so',
        'js', 'html', 'htm', 'xhtml', 'svg', 'vbs', 'scr', 'cgi', 'pl'
    ];

    /**
     * Default maximum size in bytes: 5 MB (5 * 1024 * 1024)
     */
    public const DEFAULT_MAX_SIZE = 5242880;

    /**
     * Validate an uploaded document strictly against MIME, extension, size, and integrity.
     *
     * @param UploadedFile $file
     * @param int $maxSizeBytes
     * @throws ValidationException
     */
    public function validateDocument(UploadedFile $file, int $maxSizeBytes = self::DEFAULT_MAX_SIZE): void
    {
        if (!$file->isValid()) {
            throw ValidationException::withMessages([
                'document' => [__('The uploaded file is corrupt or failed to upload.')]
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, self::DANGEROUS_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'document' => [__('Executable or dangerous file types are strictly prohibited.')]
            ]);
        }

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'document' => [__('Invalid file extension. Only PDF, JPG, JPEG, and PNG files are allowed.')]
            ]);
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'document' => [__('Invalid file content type (:mime). Only PDF, JPG, JPEG, and PNG files are allowed.', ['mime' => $mimeType])]
            ]);
        }

        if ($file->getSize() > $maxSizeBytes) {
            $maxMb = round($maxSizeBytes / 1048576, 1);
            throw ValidationException::withMessages([
                'document' => [__('The document size exceeds the allowed limit of :limit MB.', ['limit' => $maxMb])]
            ]);
        }
    }

    /**
     * Store a verified document securely into the private filesystem.
     *
     * @param UploadedFile $file
     * @param string $documentType e.g., 'national_id', 'license', 'cr'
     * @return string Secure relative filename
     * @throws ValidationException
     */
    public function store(UploadedFile $file, string $documentType = 'doc'): string
    {
        $this->validateDocument($file);

        $safeExtension = strtolower($file->getClientOriginalExtension());
        $cleanType = preg_replace('/[^a-zA-Z0-9_-]/', '', $documentType);
        $filename = sprintf('%s_%s_%s.%s', $cleanType, Str::uuid()->toString(), time(), $safeExtension);

        Storage::disk(self::DISK)->putFileAs('', $file, $filename);

        return $filename;
    }

    /**
     * Check if a document exists on the private storage disk.
     */
    public function exists(string $filename): bool
    {
        $safeFilename = basename($filename);
        return Storage::disk(self::DISK)->exists($safeFilename);
    }

    /**
     * Delete a document securely from private storage.
     */
    public function delete(string $filename): bool
    {
        $safeFilename = basename($filename);
        if ($this->exists($safeFilename)) {
            return Storage::disk(self::DISK)->delete($safeFilename);
        }
        return false;
    }

    /**
     * Check authorization and stream the private file to authorized clients.
     *
     * @param string $filename
     * @param mixed $user Admin instance, User instance, or null
     * @return BinaryFileResponse
     */
    public function stream(string $filename, $user = null): BinaryFileResponse
    {
        $safeFilename = basename($filename);

        if (!$this->exists($safeFilename)) {
            abort(404, __('Document not found.'));
        }

        if (!$this->isAuthorized($safeFilename, $user)) {
            abort(403, __('Unauthorized access to confidential provider document.'));
        }

        $fullPath = Storage::disk(self::DISK)->path($safeFilename);
        $mime = Storage::disk(self::DISK)->mimeType($safeFilename) ?: 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $safeFilename . '"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Determine if the actor is authorized to view this document.
     */
    public function isAuthorized(string $safeFilename, $user = null): bool
    {
        // 1. If unauthenticated, access is forbidden
        if (empty($user)) {
            return false;
        }

        // 2. Administrators are authorized to view verification documents
        if ($user instanceof Admin || (is_object($user) && isset($user->guard_name) && $user->guard_name === 'admin')) {
            return true;
        }

        // 3. Service Provider: Can only view documents that belong to their own seller_verifies record
        if ($user instanceof User || (is_object($user) && isset($user->id))) {
            $userId = (int) $user->id;
            return SellerVerify::where('seller_id', $userId)
                ->where(function ($query) use ($safeFilename) {
                    $query->where('national_id_document', $safeFilename)
                          ->orWhere('license_document', $safeFilename)
                          ->orWhere('cr_document', $safeFilename);
                })
                ->exists();
        }

        return false;
    }
}
