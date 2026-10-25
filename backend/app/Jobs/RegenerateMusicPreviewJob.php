<?php

namespace App\Jobs;

use App\MusicTrack;
use App\Services\Music\MusicAudioProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegenerateMusicPreviewJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public MusicTrack $track;
    public bool $force;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public int $timeout = 180;

    /**
     * The unique ID of the job to prevent duplicate queueing for the same track.
     */
    public function uniqueId(): string
    {
        return (string) $this->track->id;
    }

    /**
     * Create a new job instance.
     */
    public function __construct(MusicTrack $track, bool $force = false)
    {
        $this->track = $track;
        $this->force = $force;
    }

    /**
     * Execute the job.
     */
    public function handle(MusicAudioProcessingService $processingService): void
    {
        Log::info("RegenerateMusicPreviewJob started for track #{$this->track->id}");
        $success = $processingService->regeneratePreviewIfNeeded($this->track, $this->force);

        if (!$success) {
            Log::warning("RegenerateMusicPreviewJob did not produce a replacement preview for track #{$this->track->id}");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("RegenerateMusicPreviewJob failed permanently for track #{$this->track->id}: {$exception->getMessage()}");
    }
}
