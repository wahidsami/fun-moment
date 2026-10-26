<?php

namespace App\Console\Commands;

use App\Admin;
use App\MusicAlbum;
use App\MusicGenre;
use App\MusicOffer;
use App\MusicSetting;
use App\MusicTrack;
use App\Services\Music\MusicAudioProcessingService;
use App\Services\Music\MusicStorageService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SeedStagingMusicCatalogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'music:seed-staging-catalog 
                            {--count=2 : Number of synthetic tracks to seed (1 or 2)}
                            {--force : Force execution}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deterministically seed first-party test tracks and an album for staging playback verification. BLOCKED IN PRODUCTION.';

    /**
     * Execute the console command.
     */
    public function handle(
        MusicStorageService $storageService,
        MusicAudioProcessingService $processingService
    ): int {
        // STRICT SAFETY GUARD: Absolutely forbid running in production
        if (app()->environment('production') || config('app.env') === 'production') {
            $this->error('===============================================================');
            $this->error('CRITICAL ABORT: music:seed-staging-catalog is strictly');
            $this->error('prohibited in production environments. Never run on live databases.');
            $this->error('===============================================================');
            return 1;
        }

        $this->info('Starting deterministic first-party music catalog seeder for staging...');

        // 1. Resolve or create admin
        $admin = Admin::first();
        if (!$admin) {
            $this->info('No existing admin found. Creating staging admin user...');
            $admin = Admin::create([
                'name' => 'Staging Administrator',
                'username' => 'staging_admin',
                'email' => 'staging-admin@funmoments.unifinitylab.com',
                'password' => bcrypt('FunMomentStaging2026!'),
            ]);
        }
        $this->info("Authenticated Admin: {$admin->name} (ID: {$admin->id})");

        // 2. Resolve or create Genre
        $genre = MusicGenre::where('status', MusicGenre::STATUS_ACTIVE)->first();
        if (!$genre) {
            $this->info('No active genre found. Creating default electronic genre...');
            $genre = MusicGenre::create([
                'name_en' => 'Electronic & Dance',
                'name_ar' => 'ساوند إلكتروني ورقص',
                'slug' => 'electronic-dance',
                'sort_order' => 1,
                'status' => MusicGenre::STATUS_ACTIVE,
            ]);
        }
        $this->info("Target Genre: {$genre->name_en} / {$genre->name_ar} (ID: {$genre->id})");

        // 3. Create or resolve Showcase Album
        $album = MusicAlbum::where('title_en', 'FUN MOMENT Staging Showcase Vol. 1')->first();
        if (!$album) {
            $album = MusicAlbum::create([
                'title_en' => 'FUN MOMENT Staging Showcase Vol. 1',
                'title_ar' => 'مجموعة فن مومنت التجريبية الإصدار ١',
                'description_en' => 'Official first-party audio showcase synthesized for staging verification.',
                'description_ar' => 'مجموعة تجريبية رسمية أصلية تم توليدها للتحقق من بيئة الاختبار.',
                'artist_name' => 'FUN MOMENT Originals',
                'genre_id' => $genre->id,
                'status' => 'published',
                'owner_type' => 'admin',
                'admin_id' => $admin->id,
                'is_featured' => true,
                'released_at' => now(),
            ]);
            $this->info("Created Album: {$album->title_en} (ID: {$album->id})");
        } else {
            $this->info("Using existing Album: {$album->title_en} (ID: {$album->id})");
        }

        $tempDir = sys_get_temp_dir() . '/fun_moment_staging_fixture_' . uniqid();
        @mkdir($tempDir, 0755, true);

        try {
            // Track 1: Original Track (440Hz / A4)
            $track1 = MusicTrack::where('title_en', 'Desert Echoes (Original Mix)')
                ->where('owner_type', 'admin')
                ->first();

            if (!$track1) {
                $this->info('Generating Track 1: Desert Echoes (Original Mix)...');
                $wavPath1 = $this->generateWavAudio($tempDir . '/track1.wav', 8.0, 440.0);
                $track1 = $this->createAndProcessTrack(
                    $storageService,
                    $processingService,
                    $admin,
                    $genre,
                    $album,
                    $wavPath1,
                    [
                        'title_en' => 'Desert Echoes (Original Mix)',
                        'title_ar' => 'أصداء الصحراء (النسخة الأصلية)',
                        'artist_name' => 'FUN MOMENT Originals',
                        'bpm' => 124,
                        'track_key' => 'Am',
                        'description_en' => 'Synthesized progressive house original mix for mobile playback validation.',
                        'description_ar' => 'مقطع موسيقي أصلي تجريبي لاختبار تشغيل الصوت على تطبيق الموبايل.',
                        'track_number' => 1,
                        'original_track_id' => null,
                    ]
                );
                $this->info("Track 1 created and ready: ID {$track1->id}");
            } else {
                if (!$track1->isMediaReady()) {
                    $this->info("Processing pending audio for Track 1 (ID: {$track1->id})...");
                    $processingService->processTrackAudio($track1);
                    $track1->refresh();
                    if ($track1->isMediaReady()) {
                        $track1->status = 'approved';
                        $track1->released_at = now();
                        $track1->save();
                    }
                }
                $this->info("Track 1 ready: ID {$track1->id} (media_status: {$track1->media_status})");
            }

            // Track 2: Remix of Track 1 (523.25Hz / C5)
            $count = (int) $this->option('count');
            if ($count >= 2) {
                $track2 = MusicTrack::where('title_en', 'Desert Echoes (Night Drive Remix)')
                    ->where('owner_type', 'admin')
                    ->first();

                if (!$track2) {
                    $this->info('Generating Track 2: Desert Echoes (Night Drive Remix)...');
                    $wavPath2 = $this->generateWavAudio($tempDir . '/track2.wav', 6.0, 523.25);
                    $track2 = $this->createAndProcessTrack(
                        $storageService,
                        $processingService,
                        $admin,
                        $genre,
                        $album,
                        $wavPath2,
                        [
                            'title_en' => 'Desert Echoes (Night Drive Remix)',
                            'title_ar' => 'أصداء الصحراء (ريمكس القيادة الليلية)',
                            'artist_name' => 'FUN MOMENT Originals',
                            'bpm' => 128,
                            'track_key' => 'C',
                            'description_en' => 'Synthesized uptempo remix linked directly to Desert Echoes Original Mix.',
                            'description_ar' => 'ريمكس تجريبي سريع مرتبط مباشرة بالمسار الأصلي لأصداء الصحراء.',
                            'track_number' => 2,
                            'original_track_id' => $track1->id,
                        ]
                    );
                    $this->info("Track 2 (Remix) created and ready: ID {$track2->id}");
                } else {
                    $this->info("Track 2 already exists: ID {$track2->id} (media_status: {$track2->media_status})");
                }
            }

            // 4. Ensure Marketplace is Enabled so Staging App shows music
            $marketplaceEnabled = (int) MusicSetting::get('music_marketplace_enabled', 0);
            if ($marketplaceEnabled !== 1) {
                MusicSetting::set('music_marketplace_enabled', 1, 'Enable music marketplace browsing for staging');
                $this->info('Enabled music_marketplace_enabled setting for staging testing.');
            }

            // Ensure checkout and VAT are strictly DISABLED
            MusicSetting::set('music_checkout_enabled', 0, 'Commercial checkout disabled on staging');
            MusicSetting::set('music_vat_enabled', 0, 'VAT disabled on staging');

            $this->info('===============================================================');
            $this->info('STAGING PLAYBACK FIXTURE SEED COMPLETED SUCCESSFULLY!');
            $this->info("Album: {$album->title_en} (ID: {$album->id})");
            $this->info("Track 1: {$track1->title_en} (ID: {$track1->id}, Media: {$track1->media_status})");
            if (isset($track2)) {
                $this->info("Track 2: {$track2->title_en} (ID: {$track2->id}, Remix Of: {$track2->original_track_id})");
            }
            $this->info('Commercial Checkout: DISABLED');
            $this->info('VAT Calculation: DISABLED');
            $this->info('Free Offer: ACTIVE');
            $this->info('===============================================================');

            return 0;
        } finally {
            if (File::isDirectory($tempDir)) {
                File::deleteDirectory($tempDir);
            }
        }
    }

    /**
     * Synthesize a clean 44.1kHz 16-bit mono PCM WAV audio file.
     */
    protected function generateWavAudio(string $filePath, float $durationSec, float $frequency): string
    {
        $sampleRate = 44100;
        $numSamples = (int) ($sampleRate * $durationSec);
        $dataSize = $numSamples * 2;
        $fileSize = 44 + $dataSize;

        $header = pack(
            'a4Va4a4VvvVVvva4V',
            'RIFF',
            $fileSize - 8,
            'WAVE',
            'fmt ',
            16,
            1,
            1,
            $sampleRate,
            $sampleRate * 2,
            2,
            16,
            'data',
            $dataSize
        );

        $samples = '';
        for ($i = 0; $i < $numSamples; $i++) {
            $t = $i / $sampleRate;
            $val = (int) (sin(2 * M_PI * $frequency * $t) * 16000);
            $samples .= pack('v', $val & 0xFFFF);
        }

        file_put_contents($filePath, $header . $samples);
        return $filePath;
    }

    /**
     * Store master file, process audio through real pipeline, create free offer, approve & publish.
     */
    protected function createAndProcessTrack(
        MusicStorageService $storageService,
        MusicAudioProcessingService $processingService,
        Admin $admin,
        MusicGenre $genre,
        MusicAlbum $album,
        string $wavPath,
        array $trackData
    ): MusicTrack {
        $fileSize = filesize($wavPath);
        $uploadedFile = new UploadedFile($wavPath, basename($wavPath), 'audio/wav', null, true);

        // Store private master audio
        $storageResult = $storageService->storeMaster($uploadedFile, basename($wavPath), 'wav');

        $track = DB::transaction(function () use ($admin, $genre, $album, $storageResult, $fileSize, $trackData) {
            $track = MusicTrack::create([
                'title_en' => $trackData['title_en'],
                'title_ar' => $trackData['title_ar'],
                'artist_name' => $trackData['artist_name'],
                'owner_type' => 'admin',
                'admin_id' => $admin->id,
                'seller_id' => null,
                'genre_id' => $genre->id,
                'album_id' => $album->id,
                'track_number' => $trackData['track_number'],
                'original_track_id' => $trackData['original_track_id'],
                'description_en' => $trackData['description_en'],
                'description_ar' => $trackData['description_ar'],
                'bpm' => $trackData['bpm'],
                'track_key' => $trackData['track_key'],
                'rights_declaration_type' => 'original_work',
                'rights_agreement_accepted' => true,
                'rights_notes' => 'Synthesized first-party staging audio asset owned by FUN MOMENT.',
                'status' => 'draft',
                'media_status' => 'pending',
                'audio_path_original' => $storageResult['storage_path'],
                'file_size_bytes' => $fileSize,
                'audio_format' => 'wav',
                'duration_seconds' => 0,
                'preview_duration_seconds' => 0,
            ]);

            // Ensure an active free offer exists
            MusicOffer::create([
                'track_id' => $track->id,
                'offer_type' => 'free',
                'price' => 0,
                'currency' => 'SAR',
                'rental_duration_days' => null,
                'allow_download' => false,
                'commercial_use' => false,
                'remix_rights' => false,
                'is_active' => true,
            ]);

            return $track;
        });

        // Run through real audio processing pipeline
        $processingService->processTrackAudio($track);
        $track->refresh();

        // If media is ready, approve and publish immediately
        if ($track->isMediaReady()) {
            $track->status = 'approved';
            $track->released_at = now();
            $track->save();
        }

        return $track;
    }
}
