<?php

namespace App\Console\Commands;

use App\MediaUpload;
use App\MusicAlbum;
use App\MusicTrack;
use App\Services\Media\ImageOptimizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OptimizeExistingMediaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:optimize-variants
                            {--dry-run : Simulate execution without generating or saving any files}
                            {--scope=all : Processing scope: all, music, uploads}
                            {--batch=50 : Maximum files to process in this run}
                            {--force : Force regenerate existing variants}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely generate responsive WebP variants for existing uploaded images without modifying originals';

    protected ImageOptimizationService $optimizationService;

    public function __construct(ImageOptimizationService $optimizationService)
    {
        parent::__construct();
        $this->optimizationService = $optimizationService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $scope    = strtolower((string) $this->option('scope'));
        $batch    = max(1, (int) $this->option('batch'));
        $force    = (bool) $this->option('force');

        $this->info("=========================================================================");
        $this->info("FUN MOMENT — RESPONSIVE MEDIA OPTIMIZATION BACKFILL");
        $this->info("=========================================================================");
        $this->line("Mode     : " . ($isDryRun ? '<comment>DRY-RUN (Simulated, zero writes)</comment>' : '<info>LIVE (Generating WebP variants)</info>'));
        $this->line("Scope    : {$scope}");
        $this->line("Batch    : {$batch}");
        $this->line("Force    : " . ($force ? 'YES' : 'NO'));
        $this->info("=========================================================================");

        $processed = 0;
        $skipped = 0;
        $failed = 0;
        $candidates = [];

        // 1. Gather Music Covers candidates (Public disk)
        if ($scope === 'all' || $scope === 'music') {
            $tracks = MusicTrack::whereNotNull('cover_image')->where('cover_image', '!=', '')->pluck('cover_image');
            $albums = MusicAlbum::whereNotNull('cover_image')->where('cover_image', '!=', '')->pluck('cover_image');
            $musicCovers = $tracks->concat($albums)->unique()->filter(function ($path) {
                return !filter_var($path, FILTER_VALIDATE_URL) && str_starts_with($path, 'music_covers/');
            });

            foreach ($musicCovers as $relPath) {
                $candidates[] = [
                    'source' => 'music',
                    'path' => $relPath,
                    'disk' => 'public',
                    'preset' => ImageOptimizationService::PRESET_MUSIC_ARTWORK,
                ];
            }
        }

        // 2. Gather Media Vault Uploads candidates (public_path('assets/uploads/media-uploader'))
        if ($scope === 'all' || $scope === 'uploads') {
            $uploads = MediaUpload::whereNotNull('path')->where('path', '!=', '')->pluck('path')->unique();

            foreach ($uploads as $filename) {
                // Ignore generated thumbnail variants themselves (grid-, thumb-, etc.)
                if (preg_match('/^(grid|large|semi-large|thumb)-/i', $filename)) {
                    continue;
                }

                $candidates[] = [
                    'source' => 'uploads',
                    'path' => 'assets/uploads/media-uploader/' . $filename,
                    'disk' => null, // public_path
                    'preset' => ImageOptimizationService::PRESET_GENERAL,
                ];
            }
        }

        $totalCandidates = count($candidates);
        $this->line("Identified {$totalCandidates} unique image candidate(s) in scope.");

        $targetBatch = array_slice($candidates, 0, $batch);
        $this->line("Processing batch of " . count($targetBatch) . " file(s)...\n");

        $tableRows = [];

        foreach ($targetBatch as $item) {
            $path = $item['path'];
            $disk = $item['disk'];
            $preset = $item['preset'];
            $source = $item['source'];

            $absPath = ($disk === 'public')
                ? Storage::disk('public')->path($path)
                : public_path($path);

            if (!file_exists($absPath)) {
                $tableRows[] = [$source, basename($path), '<fg=yellow>FILE_NOT_FOUND</>', '-'];
                $skipped++;
                continue;
            }

            if (!$this->optimizationService->isOptimizable($absPath)) {
                $tableRows[] = [$source, basename($path), '<fg=yellow>UNSUPPORTED_FORMAT</>', '-'];
                $skipped++;
                continue;
            }

            // Check if variants already exist
            $parentDir = dirname($path);
            $filename = basename($path);
            $stem = pathinfo($filename, PATHINFO_FILENAME);
            $variantDir = ($parentDir === '.' || $parentDir === '')
                ? "variants/{$stem}"
                : "{$parentDir}/variants/{$stem}";

            $variantAbsDir = ($disk === 'public')
                ? Storage::disk('public')->path($variantDir)
                : public_path($variantDir);

            $thumbFile = "{$variantAbsDir}/thumbnail.webp";

            if (!$force && file_exists($thumbFile) && filesize($thumbFile) > 0) {
                $tableRows[] = [$source, basename($path), '<fg=cyan>ALREADY_OPTIMIZED</>', 'Skipped'];
                $skipped++;
                continue;
            }

            if ($isDryRun) {
                $info = @getimagesize($absPath);
                $origSize = @filesize($absPath) ?: 0;
                $dim = $info ? "{$info[0]}x{$info[1]}" : 'Unknown';
                $tableRows[] = [$source, basename($path), '<comment>WOULD_OPTIMIZE</comment>', "{$dim} (" . round($origSize / 1024, 1) . " KB)"];
                $processed++;
                continue;
            }

            // Live execution
            try {
                $result = $this->optimizationService->optimizeExistingFile($path, $preset, $disk, $force);
                if ($result['success']) {
                    $variantCount = count($result['variants']);
                    $origKb = round(($result['original']['size'] ?? 0) / 1024, 1);
                    $thumbKb = round(($result['variants']['thumbnail']['size'] ?? 0) / 1024, 1);
                    $tableRows[] = [$source, basename($path), '<info>OPTIMIZED</info>', "{$variantCount} variants (Orig: {$origKb}KB, Thumb: {$thumbKb}KB)"];
                    $processed++;
                } else {
                    $tableRows[] = [$source, basename($path), '<fg=red>ERROR</>', $result['error'] ?? 'Unknown'];
                    $failed++;
                }
            } catch (Throwable $e) {
                $tableRows[] = [$source, basename($path), '<fg=red>EXCEPTION</>', $e->getMessage()];
                $failed++;
            }
        }

        $this->table(['Source', 'File', 'Status', 'Details'], $tableRows);

        $this->info("\n=========================================================================");
        $this->info("EXECUTION SUMMARY");
        $this->info("=========================================================================");
        $this->line("Processed : {$processed}");
        $this->line("Skipped   : {$skipped}");
        $this->line("Failed    : {$failed}");
        $this->info("=========================================================================");

        return ($failed > 0) ? self::FAILURE : self::SUCCESS;
    }
}
