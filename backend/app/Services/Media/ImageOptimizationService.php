<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Throwable;

/**
 * Class ImageOptimizationService
 *
 * Centralized, production-grade image optimization and responsive variant engine
 * across the FUN MOMENT ecosystem (Backend, React Admin, and Flutter Mobile).
 *
 * Capabilities:
 * - Bounded, synchronous, memory-safe execution (no queue daemon required).
 * - Supported raster formats: JPEG, PNG, WebP, GIF (first frame).
 * - Safe passthrough for non-raster assets (SVG, PDF, audio, documents).
 * - Decompression bomb & memory exhaustion protection (dimension & pixel limits).
 * - EXIF orientation correction.
 * - Aspect-ratio preservation with strictly zero upscaling.
 * - Alpha channel transparency preservation.
 * - WebP variant encoding with high visual fidelity (82% quality sweet-spot).
 * - Deterministic, collision-safe variant pathing under persistent storage.
 * - Atomic writes via temporary files.
 * - Fail-safe fallback to original upload on any processing anomaly.
 */
class ImageOptimizationService
{
    // Preset identifiers
    public const PRESET_MUSIC_ARTWORK    = 'music_artwork';
    public const PRESET_PROFILE          = 'profile';
    public const PRESET_CATEGORY_SERVICE = 'category_service';
    public const PRESET_BANNER_SLIDER    = 'banner_slider';
    public const PRESET_GENERAL          = 'general';

    // Variant keys
    public const VARIANT_THUMBNAIL = 'thumbnail';
    public const VARIANT_MEDIUM    = 'medium';
    public const VARIANT_LARGE     = 'large';

    // Safety limits
    protected const MAX_FILE_SIZE_BYTES  = 20971520;  // 20 MB max for image processing
    protected const MAX_PIXEL_DIMENSION  = 8192;      // 8K max width or height
    protected const MAX_PIXEL_AREA       = 36000000;  // 36 Megapixels max (e.g. 6000x6000)
    protected const DEFAULT_WEBP_QUALITY = 82;

    /**
     * Preset Dimension Configurations
     * [variant_key => [max_width, max_height, crop_square_bool]]
     */
    protected static array $presets = [
        self::PRESET_MUSIC_ARTWORK => [
            self::VARIANT_THUMBNAIL => ['width' => 160,  'height' => 160,  'square' => true],
            self::VARIANT_MEDIUM    => ['width' => 512,  'height' => 512,  'square' => true],
            self::VARIANT_LARGE     => ['width' => 1024, 'height' => 1024, 'square' => true],
        ],
        self::PRESET_PROFILE => [
            self::VARIANT_THUMBNAIL => ['width' => 128, 'height' => 128, 'square' => true],
            self::VARIANT_MEDIUM    => ['width' => 256, 'height' => 256, 'square' => true],
            self::VARIANT_LARGE     => ['width' => 512, 'height' => 512, 'square' => true],
        ],
        self::PRESET_CATEGORY_SERVICE => [
            self::VARIANT_THUMBNAIL => ['width' => 320,  'height' => 320,  'square' => false],
            self::VARIANT_MEDIUM    => ['width' => 640,  'height' => 640,  'square' => false],
            self::VARIANT_LARGE     => ['width' => 1024, 'height' => 1024, 'square' => false],
        ],
        self::PRESET_BANNER_SLIDER => [
            self::VARIANT_THUMBNAIL => ['width' => 640,  'height' => 360,  'square' => false],
            self::VARIANT_MEDIUM    => ['width' => 1280, 'height' => 720,  'square' => false],
            self::VARIANT_LARGE     => ['width' => 1920, 'height' => 1080, 'square' => false],
        ],
        self::PRESET_GENERAL => [
            self::VARIANT_THUMBNAIL => ['width' => 300,  'height' => 300,  'square' => false],
            self::VARIANT_MEDIUM    => ['width' => 800,  'height' => 800,  'square' => false],
            self::VARIANT_LARGE     => ['width' => 1600, 'height' => 1600, 'square' => false],
        ],
    ];

    /**
     * Get all registered preset names.
     */
    public static function getSupportedPresets(): array
    {
        return array_keys(static::$presets);
    }

    /**
     * Check if a given file path or UploadedFile is a supported, optimizable raster image.
     */
    public function isOptimizable(string|UploadedFile $file): bool
    {
        $realPath = ($file instanceof UploadedFile) ? $file->getRealPath() : $file;

        if (empty($realPath) || !file_exists($realPath) || !is_readable($realPath)) {
            return false;
        }

        // File size check
        $size = @filesize($realPath);
        if ($size === false || $size === 0 || $size > self::MAX_FILE_SIZE_BYTES) {
            return false;
        }

        // MIME type inspection via fileinfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $realPath) : null;
        if ($finfo) {
            finfo_close($finfo);
        }

        $supportedMimes = [
            'image/jpeg',
            'image/pjpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ];

        if (!in_array($mime, $supportedMimes, true)) {
            return false;
        }

        // Header dimension inspection (reads headers only, zero image decompression memory)
        $info = @getimagesize($realPath);
        if (!$info || !isset($info[0], $info[1])) {
            return false;
        }

        $width = (int) $info[0];
        $height = (int) $info[1];

        if ($width <= 0 || $height <= 0) {
            return false;
        }

        // Decompression bomb & dimension limits
        if ($width > self::MAX_PIXEL_DIMENSION || $height > self::MAX_PIXEL_DIMENSION) {
            Log::warning("[ImageOptimizationService] Rejected image exceeding max dimension: {$width}x{$height}");
            return false;
        }

        if (($width * $height) > self::MAX_PIXEL_AREA) {
            Log::warning("[ImageOptimizationService] Rejected image exceeding max pixel area: {$width}x{$height} (" . ($width * $height) . " px)");
            return false;
        }

        return true;
    }

    /**
     * Process an existing file already stored on disk and generate responsive WebP variants.
     *
     * @param string $sourcePath Relative to disk, or relative to public_path(), or absolute path
     * @param string $preset Preset identifier (e.g. PRESET_MUSIC_ARTWORK)
     * @param string|null $disk 'public' for Storage::disk('public'), or null for public_path()
     * @param bool $force Regenerate even if variants already exist
     * @return array [success => bool, original => [...], variants => [...], urls => [...]]
     */
    public function optimizeExistingFile(
        string $sourcePath,
        string $preset = self::PRESET_GENERAL,
        ?string $disk = 'public',
        bool $force = false
    ): array {
        $cleanSource = ltrim($sourcePath, '/');
        $absoluteSource = $this->resolveAbsolutePath($cleanSource, $disk);

        if (!$this->isOptimizable($absoluteSource)) {
            $fallbackUrl = $this->resolvePublicUrl($cleanSource, $disk);
            return [
                'success' => false,
                'reason' => 'NOT_OPTIMIZABLE',
                'original' => ['path' => $cleanSource, 'url' => $fallbackUrl],
                'variants' => [],
                'urls' => [
                    self::VARIANT_THUMBNAIL => $fallbackUrl,
                    self::VARIANT_MEDIUM    => $fallbackUrl,
                    self::VARIANT_LARGE     => $fallbackUrl,
                ],
            ];
        }

        $originalUrl = $this->resolvePublicUrl($cleanSource, $disk);
        $variantDefs = self::$presets[$preset] ?? self::$presets[self::PRESET_GENERAL];

        // Determine variant directory: {parent_dir}/variants/{stem}/
        $parentDir = dirname($cleanSource);
        $filename = basename($cleanSource);
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $variantRelDir = ($parentDir === '.' || $parentDir === '')
            ? "variants/{$stem}"
            : "{$parentDir}/variants/{$stem}";

        $variantAbsDir = $this->resolveAbsolutePath($variantRelDir, $disk);

        if (!file_exists($variantAbsDir)) {
            @mkdir($variantAbsDir, 0775, true);
        }

        $info = @getimagesize($absoluteSource);
        $origWidth = (int) ($info[0] ?? 0);
        $origHeight = (int) ($info[1] ?? 0);
        $origSize = @filesize($absoluteSource) ?: 0;
        $origMime = $info['mime'] ?? 'image/jpeg';

        $generatedVariants = [];
        $urls = [
            self::VARIANT_THUMBNAIL => $originalUrl,
            self::VARIANT_MEDIUM    => $originalUrl,
            self::VARIANT_LARGE     => $originalUrl,
        ];

        try {
            foreach ($variantDefs as $key => $spec) {
                $targetFilename = "{$key}.webp";
                $variantRelPath = "{$variantRelDir}/{$targetFilename}";
                $variantAbsPath = "{$variantAbsDir}/{$targetFilename}";
                $variantUrl = $this->resolvePublicUrl($variantRelPath, $disk);

                // If variant already exists and not forcing, use existing
                if (!$force && file_exists($variantAbsPath) && filesize($variantAbsPath) > 0) {
                    $vInfo = @getimagesize($variantAbsPath);
                    $generatedVariants[$key] = [
                        'path' => $variantRelPath,
                        'url' => $variantUrl,
                        'width' => (int) ($vInfo[0] ?? $spec['width']),
                        'height' => (int) ($vInfo[1] ?? $spec['height']),
                        'format' => 'webp',
                        'size' => (int) filesize($variantAbsPath),
                        'status' => 'CACHED',
                    ];
                    $urls[$key] = $variantUrl;
                    continue;
                }

                // Never upscale beyond original dimensions
                $targetWidth = min($spec['width'], $origWidth);
                $targetHeight = min($spec['height'], $origHeight);

                // Read image fresh for each variant to ensure clean state and avoid cumulative compression
                $img = Image::make($absoluteSource);

                // Apply EXIF orientation
                if (function_exists('exif_read_data')) {
                    $img->orientate();
                }

                if ($spec['square']) {
                    // For music/profile square requirements: fit into square without distorting aspect ratio
                    $boxSize = min($spec['width'], max($origWidth, $origHeight));
                    $img->fit($spec['width'], $spec['height'], function ($constraint) {
                        $constraint->upsize();
                    });
                } else {
                    // Standard responsive resize: preserve aspect ratio, strictly no upscaling
                    $img->resize($spec['width'], $spec['height'], function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                }

                // Write atomically using temporary file in the same directory
                $tmpPath = "{$variantAbsDir}/.tmp_{$key}_" . Str::random(8) . ".webp";
                $img->encode('webp', self::DEFAULT_WEBP_QUALITY)->save($tmpPath);

                if (file_exists($tmpPath) && filesize($tmpPath) > 0) {
                    if (file_exists($variantAbsPath)) {
                        @unlink($variantAbsPath);
                    }
                    rename($tmpPath, $variantAbsPath);
                    @chmod($variantAbsPath, 0664);

                    $vInfo = @getimagesize($variantAbsPath);
                    $vSize = (int) filesize($variantAbsPath);

                    $generatedVariants[$key] = [
                        'path' => $variantRelPath,
                        'url' => $variantUrl,
                        'width' => (int) ($vInfo[0] ?? $targetWidth),
                        'height' => (int) ($vInfo[1] ?? $targetHeight),
                        'format' => 'webp',
                        'size' => $vSize,
                        'status' => 'GENERATED',
                    ];
                    $urls[$key] = $variantUrl;
                } else {
                    @unlink($tmpPath);
                    $urls[$key] = $originalUrl;
                }
            }

            return [
                'success' => true,
                'original' => [
                    'path' => $cleanSource,
                    'url' => $originalUrl,
                    'width' => $origWidth,
                    'height' => $origHeight,
                    'format' => pathinfo($cleanSource, PATHINFO_EXTENSION),
                    'mime' => $origMime,
                    'size' => $origSize,
                ],
                'variants' => $generatedVariants,
                'urls' => $urls,
            ];

        } catch (Throwable $e) {
            Log::warning("[ImageOptimizationService] Variant generation failed for '{$cleanSource}': " . $e->getMessage());

            // Non-destructive fallback: original remains 100% accessible
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'original' => [
                    'path' => $cleanSource,
                    'url' => $originalUrl,
                    'width' => $origWidth,
                    'height' => $origHeight,
                    'size' => $origSize,
                ],
                'variants' => [],
                'urls' => [
                    self::VARIANT_THUMBNAIL => $originalUrl,
                    self::VARIANT_MEDIUM    => $originalUrl,
                    self::VARIANT_LARGE     => $originalUrl,
                ],
            ];
        }
    }

    /**
     * Resolves the responsive variant URLs for an image path deterministically.
     * If variants exist on disk, their URLs are returned.
     * If variants do not exist, falls back gracefully to the original URL.
     *
     * @param string|null $path Relative path (e.g. 'music_covers/cover_123.jpg')
     * @param string|null $preset Preset identifier or disk name (for polymorphic compatibility)
     * @param string|null $disk 'public' or null
     * @return array [thumbnail => url, medium => url, large => url]
     */
    public function getVariantUrls(?string $path, ?string $preset = null, ?string $disk = 'public'): array
    {
        // Polymorphic compatibility: if 2nd arg was passed as disk ('public' or null with 2 args)
        if ($preset === 'public' || ($preset === null && func_num_args() === 2)) {
            $disk = $preset;
            $preset = null;
        }

        if (empty($path)) {
            return [
                self::VARIANT_THUMBNAIL => null,
                self::VARIANT_MEDIUM    => null,
                self::VARIANT_LARGE     => null,
            ];
        }

        // If path is already a full external URL, return it for all slots
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return [
                self::VARIANT_THUMBNAIL => $path,
                self::VARIANT_MEDIUM    => $path,
                self::VARIANT_LARGE     => $path,
            ];
        }

        $cleanPath = ltrim($path, '/');
        $originalUrl = $this->resolvePublicUrl($cleanPath, $disk);

        $parentDir = dirname($cleanPath);
        $filename = basename($cleanPath);
        $stem = pathinfo($filename, PATHINFO_FILENAME);

        $variantRelDir = ($parentDir === '.' || $parentDir === '')
            ? "variants/{$stem}"
            : "{$parentDir}/variants/{$stem}";

        $urls = [];
        $keys = [self::VARIANT_THUMBNAIL, self::VARIANT_MEDIUM, self::VARIANT_LARGE];

        foreach ($keys as $key) {
            $variantRelPath = "{$variantRelDir}/{$key}.webp";
            $variantAbsPath = $this->resolveAbsolutePath($variantRelPath, $disk);

            if (file_exists($variantAbsPath) && filesize($variantAbsPath) > 0) {
                $urls[$key] = $this->resolvePublicUrl($variantRelPath, $disk);
            } else {
                // Graceful fallback to original URL
                $urls[$key] = $originalUrl;
            }
        }

        return $urls;
    }

    /**
     * Clean up generated variants when an original image is deleted.
     */
    public function deleteVariants(string $path, ?string $disk = 'public'): bool
    {
        try {
            $cleanPath = ltrim($path, '/');
            $parentDir = dirname($cleanPath);
            $filename = basename($cleanPath);
            $stem = pathinfo($filename, PATHINFO_FILENAME);

            $variantRelDir = ($parentDir === '.' || $parentDir === '')
                ? "variants/{$stem}"
                : "{$parentDir}/variants/{$stem}";

            $variantAbsDir = $this->resolveAbsolutePath($variantRelDir, $disk);

            if (is_dir($variantAbsDir)) {
                $files = glob("{$variantAbsDir}/*");
                if ($files) {
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            @unlink($file);
                        }
                    }
                }
                @rmdir($variantAbsDir);
            }

            return true;
        } catch (Throwable $e) {
            Log::warning("[ImageOptimizationService] Failed deleting variants for '{$path}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Resolve absolute disk path given relative path and disk context.
     */
    protected function resolveAbsolutePath(string $relativePath, ?string $disk): string
    {
        $clean = ltrim($relativePath, '/');

        if ($disk === 'public') {
            return Storage::disk('public')->path($clean);
        }

        // Default or null disk implies public_path() for legacy assets/uploads
        return public_path($clean);
    }

    /**
     * Resolve public HTTP URL given relative path and disk context.
     */
    protected function resolvePublicUrl(string $relativePath, ?string $disk): string
    {
        $clean = ltrim($relativePath, '/');

        if ($disk === 'public') {
            return asset('storage/' . $clean);
        }

        return asset($clean);
    }
}
