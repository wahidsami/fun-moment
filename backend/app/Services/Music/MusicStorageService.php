<?php

namespace App\Services\Music;

use App\MusicTrack;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class MusicStorageService
{
    public const DISK = 'music_private';
    public const DIR_MASTERS = 'masters';
    public const DIR_STREAMS = 'streams';
    public const DIR_PREVIEWS = 'previews';

    /**
     * Store master source audio onto the private disk.
     *
     * @param UploadedFile|string $source Either an uploaded file instance or absolute local path
     * @param string $originalFilename Original client display name
     * @param string|null $extension Canonical file extension (e.g. 'mp3', 'wav', 'flac', 'm4a')
     * @return array{storage_path: string, storage_name: string, file_size: int, original_filename: string}
     */
    public function storeMaster($source, string $originalFilename, ?string $extension = null): array
    {
        $sanitizedOriginal = $this->sanitizeFilename($originalFilename);

        if ($extension === null) {
            $extension = strtolower(pathinfo($sanitizedOriginal, PATHINFO_EXTENSION));
        }
        $extension = strtolower(trim($extension, '. '));

        if (empty($extension)) {
            $extension = 'bin';
        }

        $uuid = Str::uuid()->toString();
        $storageName = $uuid . '.' . $extension;
        $relativePath = self::DIR_MASTERS . '/' . $storageName;

        $disk = Storage::disk(self::DISK);

        if ($source instanceof UploadedFile) {
            $stream = fopen($source->getRealPath(), 'r');
            if ($stream === false) {
                throw new RuntimeException("Failed to open uploaded file stream: {$source->getRealPath()}");
            }
            $written = $disk->put($relativePath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } elseif (is_string($source) && file_exists($source)) {
            $stream = fopen($source, 'r');
            if ($stream === false) {
                throw new RuntimeException("Failed to open source file stream: {$source}");
            }
            $written = $disk->put($relativePath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } else {
            throw new InvalidArgumentException("Invalid source provided to storeMaster; must be UploadedFile or valid file path");
        }

        if (!$written || !$disk->exists($relativePath)) {
            throw new RuntimeException("Failed to write audio master file to private disk at: {$relativePath}");
        }

        $fileSize = (int) $disk->size($relativePath);

        return [
            'storage_path' => $relativePath,
            'storage_name' => $storageName,
            'file_size' => $fileSize,
            'original_filename' => $sanitizedOriginal,
        ];
    }

    /**
     * Store a pre-generated preview asset on the private disk.
     */
    public function storePreview(string $sourceLocalPath, int $durationSeconds, int $previewVersion = 1): string
    {
        if (!file_exists($sourceLocalPath)) {
            throw new InvalidArgumentException("Preview source file not found: {$sourceLocalPath}");
        }

        $uuid = Str::uuid()->toString();
        $storageName = sprintf('%s_%ds_v%d.mp3', $uuid, $durationSeconds, $previewVersion);
        $relativePath = self::DIR_PREVIEWS . '/' . $storageName;

        $disk = Storage::disk(self::DISK);
        $stream = fopen($sourceLocalPath, 'r');
        if ($stream === false) {
            throw new RuntimeException("Failed to open preview stream: {$sourceLocalPath}");
        }

        $written = $disk->put($relativePath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        if (!$written || !$disk->exists($relativePath)) {
            throw new RuntimeException("Failed to save preview asset to private disk: {$relativePath}");
        }

        return $relativePath;
    }

    /**
     * Store a standardized streaming rendition on the private disk.
     */
    public function storeStreamingRendition(string $sourceLocalPath): string
    {
        if (!file_exists($sourceLocalPath)) {
            throw new InvalidArgumentException("Streaming rendition source file not found: {$sourceLocalPath}");
        }

        $uuid = Str::uuid()->toString();
        $storageName = $uuid . '_stream.mp3';
        $relativePath = self::DIR_STREAMS . '/' . $storageName;

        $disk = Storage::disk(self::DISK);
        $stream = fopen($sourceLocalPath, 'r');
        if ($stream === false) {
            throw new RuntimeException("Failed to open stream rendition file: {$sourceLocalPath}");
        }

        $written = $disk->put($relativePath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        if (!$written || !$disk->exists($relativePath)) {
            throw new RuntimeException("Failed to save streaming rendition to private disk: {$relativePath}");
        }

        return $relativePath;
    }

    /**
     * Resolve the absolute path to a file on the private disk while strictly preventing path traversal.
     */
    public function getAbsolutePath(string $relativePath): string
    {
        $normalized = $this->sanitizeRelativePath($relativePath);
        $disk = Storage::disk(self::DISK);

        if (!$disk->exists($normalized)) {
            throw new RuntimeException("File not found on private storage disk: {$normalized}");
        }

        return $disk->path($normalized);
    }

    /**
     * Check if a relative path exists on the private disk.
     */
    public function exists(string $relativePath): bool
    {
        try {
            $normalized = $this->sanitizeRelativePath($relativePath);
            return Storage::disk(self::DISK)->exists($normalized);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Delete an asset on the private disk.
     */
    public function delete(string $relativePath): bool
    {
        try {
            $normalized = $this->sanitizeRelativePath($relativePath);
            return Storage::disk(self::DISK)->delete($normalized);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check whether a track master can be safely deleted or replaced.
     * Prevents deletion of masters associated with existing transactions or active entitlements.
     */
    public function canDeleteMaster(MusicTrack $track): bool
    {
        // If track has any orders or entitlements, the master is protected
        if ($track->orders()->exists()) {
            return false;
        }

        if ($track->entitlements()->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Remove temporary files safely.
     */
    public function cleanupTemp(?string $tempPath): void
    {
        if ($tempPath && file_exists($tempPath) && is_file($tempPath)) {
            @unlink($tempPath);
        }
    }

    /**
     * Strictly sanitize relative paths to prevent directory traversal attacks (e.g. ../ or /etc/passwd).
     */
    public function sanitizeRelativePath(string $path): string
    {
        $path = str_replace(['\\', "\0"], ['/', ''], $path);

        if (str_contains($path, '..') || preg_match('#(?:^|/)\.\.(?:/|$)#', $path)) {
            throw new InvalidArgumentException("Directory traversal detected in storage path: {$path}");
        }

        $parts = array_filter(explode('/', $path), function ($segment) {
            return $segment !== '' && $segment !== '.';
        });

        if (empty($parts)) {
            throw new InvalidArgumentException("Invalid or empty storage path");
        }

        $sanitized = implode('/', $parts);

        // Disallow navigating outside permitted directories
        $allowedPrefixes = [self::DIR_MASTERS, self::DIR_STREAMS, self::DIR_PREVIEWS];
        $isAllowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if ($sanitized === $prefix || str_starts_with($sanitized, $prefix . '/')) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            throw new InvalidArgumentException("Access to path outside permitted music storage folders is denied: {$sanitized}");
        }

        return $sanitized;
    }

    /**
     * Delete a stored file from the private music disk.
     */
    public function deleteFile(?string $relativePath): bool
    {
        if (empty($relativePath)) {
            return false;
        }

        try {
            $sanitized = $this->sanitizeRelativePath($relativePath);
            return Storage::disk(self::DISK)->delete($sanitized);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Sanitize original display filename.
     */
    protected function sanitizeFilename(string $filename): string
    {
        $filename = basename(str_replace(['\\', "\0"], ['/', ''], $filename));
        return preg_replace('/[^a-zA-Z0-9_\-\. ]/', '_', $filename) ?: 'audio_track';
    }
}
