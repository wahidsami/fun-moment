<?php

namespace App\Services\Music;

interface AudioProcessorInterface
{
    /**
     * Determine whether the underlying audio processing binaries are available.
     */
    public function isAvailable(): bool;

    /**
     * Extract audio metadata using ffprobe or equivalent inspector.
     */
    public function probeMetadata(string $filePath): AudioMetadata;

    /**
     * Generate a standardized preview file (MP3) from source audio.
     *
     * @param string $sourcePath Input audio path
     * @param string $outputPath Output MP3 path
     * @param int $durationSeconds Desired preview duration
     * @param bool $applyFade Whether to apply subtle fade out at end
     * @return bool
     */
    public function generatePreview(
        string $sourcePath,
        string $outputPath,
        int $durationSeconds,
        bool $applyFade = true
    ): bool;

    /**
     * Generate a standardized streaming rendition (MP3 192k) from master source audio.
     */
    public function generateStreamingRendition(
        string $sourcePath,
        string $outputPath,
        int $targetBitrateKbps = 192
    ): bool;
}
