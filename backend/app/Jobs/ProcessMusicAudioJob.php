<?php

namespace App\Jobs;

use App\MusicTrack;
use App\Services\Music\MusicAudioProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMusicAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public MusicTrack $track;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public int $timeout = 180;

    /**
     * Create a new job instance.
     *
     * @param MusicTrack|int $track
     */
    public function __construct($track)
    {
        $this->track = $track instanceof MusicTrack ? $track : MusicTrack::findOrFail($track);
    }

    /**
     * Execute the job.
     */
    public function handle(MusicAudioProcessingService $processingService): void
    {
        Log::info("ProcessMusicAudioJob started for track #{$this->track->id}");
        $success = $processingService->processTrackAudio($this->track);

        if (!$success) {
            Log::warning("ProcessMusicAudioJob failed to process audio for track #{$this->track->id}");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("ProcessMusicAudioJob permanently failed for track #{$this->track->id}: {$exception->getMessage()}");
        $this->track->markMediaFailed("Background transcode failed: " . $exception->getMessage());
    }
}
