<?php

namespace App\Services\Music;

use App\MusicSetting;
use App\MusicTrack;
use Illuminate\Support\Facades\Log;
use Throwable;

class MusicAudioProcessingService
{
    protected MusicStorageService $storageService;
    protected AudioProcessorInterface $audioProcessor;

    public function __construct(
        MusicStorageService $storageService,
        AudioProcessorInterface $audioProcessor
    ) {
        $this->storageService = $storageService;
        $this->audioProcessor = $audioProcessor;
    }

    /**
     * Process an uploaded track master: probe metadata, generate streaming rendition, and generate preview.
     *
     * @param MusicTrack $track
     * @return bool True if media was successfully processed and marked ready
     */
    public function processTrackAudio(MusicTrack $track): bool
    {
        $track->markMediaProcessing();

        $tempFiles = [];

        try {
            if (empty($track->audio_path_original)) {
                throw new \RuntimeException("Track does not have an original master audio path recorded");
            }

            $masterAbsolutePath = $this->storageService->getAbsolutePath($track->audio_path_original);

            // 1. Inspect metadata with AudioProcessor
            $metadata = $this->audioProcessor->probeMetadata($masterAbsolutePath);
            if (!$metadata->isValid) {
                throw new \RuntimeException("Audio inspection failed: " . ($metadata->error ?? 'Unknown error'));
            }

            if ($metadata->durationSeconds <= 0) {
                throw new \RuntimeException("Audio duration is zero or invalid");
            }

            // Update track with probed metadata
            $track->duration_seconds = $metadata->durationSeconds;
            $track->audio_codec = $metadata->codec;
            $track->audio_bitrate_kbps = $metadata->bitrateKbps;
            $track->audio_sample_rate = $metadata->sampleRate;
            $track->audio_channels = $metadata->channels;
            if ($metadata->format) {
                $track->audio_mime_type = $metadata->format;
            }

            // 2. Determine target preview duration
            $configuredPreviewDuration = MusicSetting::getEffectivePaidPreviewDurationSeconds();
            $effectivePreviewDuration = (int) min($track->duration_seconds, $configuredPreviewDuration);
            // Ensure minimum preview duration of 5 seconds unless track itself is shorter
            if ($track->duration_seconds >= 5 && $effectivePreviewDuration < 5) {
                $effectivePreviewDuration = 5;
            }

            // 3. Generate Preview Asset
            $previewVersion = ($track->preview_version ?? 0) + 1;
            $tempPreviewFile = tempnam(sys_get_temp_dir(), 'preview_') . '.mp3';
            $tempFiles[] = $tempPreviewFile;

            $previewSuccess = $this->audioProcessor->generatePreview(
                $masterAbsolutePath,
                $tempPreviewFile,
                $effectivePreviewDuration,
                true // Apply subtle fade out
            );

            if (!$previewSuccess || !file_exists($tempPreviewFile) || filesize($tempPreviewFile) === 0) {
                throw new \RuntimeException("Failed to generate preview audio file via audio processor");
            }

            $storedPreviewRelativePath = $this->storageService->storePreview(
                $tempPreviewFile,
                $effectivePreviewDuration,
                $previewVersion
            );

            $track->audio_path_preview = $storedPreviewRelativePath;
            $track->preview_duration_seconds = $effectivePreviewDuration;
            $track->preview_version = $previewVersion;

            // 4. Generate Standardized Streaming Rendition
            // For mobile compatibility, ensure non-MP3 files (WAV, FLAC, AAC) have a standardized MP3 192k rendition
            $sourceExt = strtolower(pathinfo($masterAbsolutePath, PATHINFO_EXTENSION));
            if ($sourceExt === 'mp3') {
                // MP3 source master can be used directly for streaming or duplicated if needed
                $track->audio_path_stream = $track->audio_path_original;
            } else {
                $tempStreamFile = tempnam(sys_get_temp_dir(), 'stream_') . '.mp3';
                $tempFiles[] = $tempStreamFile;

                $streamSuccess = $this->audioProcessor->generateStreamingRendition(
                    $masterAbsolutePath,
                    $tempStreamFile,
                    192
                );

                if ($streamSuccess && file_exists($tempStreamFile) && filesize($tempStreamFile) > 0) {
                    $storedStreamRelativePath = $this->storageService->storeStreamingRendition($tempStreamFile);
                    $track->audio_path_stream = $storedStreamRelativePath;
                } else {
                    // Fallback to master if transcode was skipped/unsupported
                    $track->audio_path_stream = $track->audio_path_original;
                }
            }

            // 5. Finalize and mark ready
            $track->markMediaReady();

            Log::info("Music track media processed successfully for track #{$track->id}");
            return true;
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
            Log::error("Music audio processing failed for track #{$track->id}: {$errorMessage}");
            $track->markMediaFailed($errorMessage);
            return false;
        } finally {
            // Clean up temporary files
            foreach ($tempFiles as $tempFile) {
                $this->storageService->cleanupTemp($tempFile);
            }
        }
    }

    /**
     * Regenerate preview if global duration setting has changed or if manually requested.
     */
    public function regeneratePreviewIfNeeded(MusicTrack $track, bool $force = false): bool
    {
        $currentSetting = MusicSetting::getEffectivePaidPreviewDurationSeconds();
        $clampedSetting = max(5, min(60, $currentSetting));

        // Ensure preview duration never exceeds track duration or platform bounds (5-60s)
        if ($track->duration_seconds > 0) {
            $targetDuration = (int) min($track->duration_seconds, $clampedSetting);
        } else {
            $targetDuration = $clampedSetting;
        }

        if ($track->duration_seconds >= 5 && $targetDuration < 5) {
            $targetDuration = 5;
        }

        if (!$force && $track->preview_duration_seconds === $targetDuration && !empty($track->audio_path_preview)) {
            // Preview is already current
            return false;
        }

        if (empty($track->audio_path_original)) {
            return false;
        }

        $masterAbsolutePath = $this->storageService->getAbsolutePath($track->audio_path_original);
        $previewVersion = ($track->preview_version ?? 0) + 1;

        $tempPreviewFile = tempnam(sys_get_temp_dir(), 'preview_regen_') . '.mp3';

        try {
            $success = $this->audioProcessor->generatePreview(
                $masterAbsolutePath,
                $tempPreviewFile,
                $targetDuration,
                true
            );

            if ($success && file_exists($tempPreviewFile) && filesize($tempPreviewFile) > 0) {
                $oldPreview = $track->audio_path_preview;

                $storedPreviewPath = $this->storageService->storePreview(
                    $tempPreviewFile,
                    $targetDuration,
                    $previewVersion
                );

                $track->update([
                    'audio_path_preview' => $storedPreviewPath,
                    'preview_duration_seconds' => $targetDuration,
                    'preview_version' => $previewVersion,
                ]);

                // Safely delete old preview asset ONLY after new asset is safely written and database updated
                if ($oldPreview && $oldPreview !== $storedPreviewPath) {
                    $this->storageService->delete($oldPreview);
                }

                return true;
            }
        } catch (Throwable $e) {
            Log::error("Failed to regenerate preview for track #{$track->id}: " . $e->getMessage());
        } finally {
            $this->storageService->cleanupTemp($tempPreviewFile);
        }

        return false;
    }

    /**
     * Dispatch background jobs to regenerate preview assets for all tracks with outdated durations.
     *
     * @param bool $force Force regeneration even if duration matches setting
     * @return int Number of queued jobs dispatched
     */
    public function queuePreviewRegenerationForOutdatedTracks(bool $force = false): int
    {
        $currentSetting = MusicSetting::getEffectivePaidPreviewDurationSeconds();

        $query = MusicTrack::query()->where('media_status', MusicTrack::MEDIA_STATUS_READY);

        if (!$force) {
            $query->where(function ($q) use ($currentSetting) {
                $q->whereNull('preview_duration_seconds')
                  ->orWhere('preview_duration_seconds', '!=', $currentSetting);
            });
        }

        $tracks = $query->get();
        $count = 0;

        foreach ($tracks as $track) {
            try {
                \App\Jobs\RegenerateMusicPreviewJob::dispatch($track, $force);
                $count++;
            } catch (\Throwable $e) {
                Log::warning("Skipped regenerating preview for track #{$track->id}: " . $e->getMessage());
            }
        }

        return $count;
    }
}
