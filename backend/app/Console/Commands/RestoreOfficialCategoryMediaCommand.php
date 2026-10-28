<?php

namespace App\Console\Commands;

use App\Category;
use App\MediaUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreOfficialCategoryMediaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'categories:restore-media 
                            {--force : Bypass database name check for isolated local test drills}
                            {--dry-run : Preview changes without writing to database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore official category media attachments in media_uploads and categories for Project 2 (funmoments_staging). BLOCKED on production funmoments.';

    /**
     * Official category media catalog verified against Project 1 authoritative source.
     *
     * @var array<string, array<string, string>>
     */
    public const OFFICIAL_CATEGORY_MEDIA = [
        'music-dj' => [
            'name' => 'Music & DJ',
            'file' => 'dj-world1791019173.png',
            'title' => 'dj-world.png',
            'dimensions' => '1254 x 1254 pixels',
            'size' => '1.5 MB',
            'sha256' => '723e6a0bc8b994fd20c68ef2cd6daa37c8340ec3401bb501021a694bcd216daa',
        ],
        'food-hospitality' => [
            'name' => 'Food & Hospitality',
            'file' => 'food-hospitality-mobile1791026107.png',
            'title' => 'food-hospitality-mobile.png',
            'dimensions' => '512 x 512 pixels',
            'size' => '261 KB',
            'sha256' => '054767b244c92dc401e694e4d08f35eecb27ce9acab2f26206c404ebcd215a23',
        ],
        'sound-lighting' => [
            'name' => 'Sound & Lighting',
            'file' => 'sound-lighting-mobile1791026141.png',
            'title' => 'sound-lighting-mobile.png',
            'dimensions' => '512 x 512 pixels',
            'size' => '271 KB',
            'sha256' => 'b4cb2dff485e2eafb7b94dac0bbc81d84ea48d51cb2cc1c2dd1eaf3cb8350f6e',
        ],
        'event-setup-equipment' => [
            'name' => 'Event Setup & Equipment',
            'file' => 'event-setup-equipment-mobile1791026170.png',
            'title' => 'event-setup-equipment-mobile.png',
            'dimensions' => '512 x 512 pixels',
            'size' => '263 KB',
            'sha256' => '2a456ec1ee6246706ec8f09da07f359349b01b2afa4d45cc1d67457ccc5750cb',
        ],
        'decor-event-styling' => [
            'name' => 'Decor & Event Styling',
            'file' => 'decor-event-styling-mobile1791026203.png',
            'title' => 'decor-event-styling-mobile.png',
            'dimensions' => '512 x 512 pixels',
            'size' => '326 KB',
            'sha256' => '9de1796ac03fe311e879c921f539e1dfce96c44c72a8bea7e7d9d6566ae67566',
        ],
        'photography-video' => [
            'name' => 'Photography & Video',
            'file' => 'photography-video-mobile1791026232.png',
            'title' => 'photography-video-mobile.png',
            'dimensions' => '512 x 512 pixels',
            'size' => '271 KB',
            'sha256' => 'fc2bb9232ab3193537b51278f92a3061f0db140cfb657f17a4136c26f354f91b',
        ],
        'entertainment-activities' => [
            'name' => 'Entertainment Activities',
            'file' => 'entertainment-activities-mobile1791026260.png',
            'title' => 'entertainment-activities-mobile.png',
            'dimensions' => '512 x 512 pixels',
            'size' => '297 KB',
            'sha256' => '7ac17b0fd6202f42d65c87e93618c2afcc703816792c6630de62fa585325d94d',
        ],
        'body-guards' => [
            'name' => 'Body Guards',
            'file' => 'vip-bodyguard-security-duo1792672760.png',
            'title' => 'vip-bodyguard-security-duo.png',
            'dimensions' => '1363 x 1154 pixels',
            'size' => '979 KB',
            'sha256' => '33779cc16e46533430a79e349b622b81d8213e55805f357dfc11673dd5eb7cae',
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $defaultConn = config('database.default', 'pgsql');
        $dbName = (string) config("database.connections.{$defaultConn}.database");
        $dbHost = (string) config("database.connections.{$defaultConn}.host");
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('=========================================================================');
        $this->info('FUN MOMENT — RESTORE OFFICIAL CATEGORY MEDIA (PROJECT 2)');
        $this->info('=========================================================================');
        $this->info("Runtime Database Target: {$dbName} on {$dbHost}");

        // HARD SAFETY GUARD: Never touch production Project 1 ('funmoments') without explicit force override
        if ($dbName === 'funmoments' && !$force) {
            $this->error('=========================================================================');
            $this->error("CRITICAL SAFETY ABORT: Active database is 'funmoments' (Project 1 Production)!");
            $this->error('Project 1 must remain completely untouched.');
            $this->error("Category media restoration is strictly restricted to Project 2 ('funmoments_staging').");
            $this->error('=========================================================================');
            return 1;
        }

        if ($dbName !== 'funmoments_staging' && !$force) {
            $this->error("TARGET MISMATCH: Expected database 'funmoments_staging', found '{$dbName}'.");
            $this->error('If you are running an isolated local drill, pass --force.');
            return 2;
        }

        if ($dryRun) {
            $this->warn('MODE: DRY-RUN (No database writes will be executed)');
            $this->table(
                ['Slug', 'English Name', 'File Name', 'Dimensions', 'File Size'],
                array_map(function ($slug, $cat) {
                    return [
                        $slug,
                        $cat['name'],
                        $cat['file'],
                        $cat['dimensions'],
                        $cat['size'],
                    ];
                }, array_keys(self::OFFICIAL_CATEGORY_MEDIA), array_values(self::OFFICIAL_CATEGORY_MEDIA))
            );
            return 0;
        }

        $results = [];

        DB::transaction(function () use (&$results) {
            foreach (self::OFFICIAL_CATEGORY_MEDIA as $slug => $mediaData) {
                // 1. Ensure MediaUpload record exists with independent ID (no assumption of P1 ID parity)
                $media = MediaUpload::where('path', $mediaData['file'])->first();
                $mediaCreated = false;

                if (!$media) {
                    $media = MediaUpload::create([
                        'title' => $mediaData['title'],
                        'path' => $mediaData['file'],
                        'size' => $mediaData['size'],
                        'dimensions' => $mediaData['dimensions'],
                        'type' => 'admin',
                        'user_id' => 1,
                    ]);
                    $mediaCreated = true;
                }

                // 2. Locate Category in Project 2 (match canonical by slug and name, update matching)
                $matchedCats = Category::where('slug', $slug)->get();
                if ($matchedCats->isEmpty()) {
                    $matchedCats = Category::where('name', $mediaData['name'])->get();
                }

                $catUpdated = false;
                $catId = null;

                if ($matchedCats->isNotEmpty()) {
                    foreach ($matchedCats as $cat) {
                        $cat->image = (string) $media->id;
                        $cat->mobile_icon = (string) $media->id;
                        $cat->save();
                    }
                    $primaryCat = $matchedCats->firstWhere('name', $mediaData['name']) ?? $matchedCats->sortBy('id')->first();
                    $catId = $primaryCat->id;
                    $catUpdated = true;
                }

                // 3. Check physical file existence on disk
                $filePath = public_path('assets/uploads/media-uploader/' . $mediaData['file']);
                $fileExists = file_exists($filePath);

                $results[] = [
                    'id' => $catId ?? 'N/A',
                    'slug' => $slug,
                    'media_id' => $media->id,
                    'file' => $mediaData['file'],
                    'media_record' => $mediaCreated ? 'Created' : 'Existing',
                    'category_update' => $catUpdated ? 'Associated' : 'Not Found',
                    'file_on_disk' => $fileExists ? 'Present' : 'Missing (Copy Needed)',
                ];
            }
        });

        $this->info("\nCategory Media Restoration Summary:");
        $this->table(
            ['Cat ID', 'Slug', 'Media ID', 'File', 'Media Record', 'Category Link', 'Physical File'],
            $results
        );

        $missingFiles = array_filter($results, fn ($r) => $r['file_on_disk'] !== 'Present');
        if (!empty($missingFiles)) {
            $this->warn("\nNote: " . count($missingFiles) . " physical file(s) are missing from public/assets/uploads/media-uploader/.");
            $this->warn("Ensure the media sync step copies the backed-up files into the persistent upload directory.");
        } else {
            $this->info("\nAll 8 physical media files verified present on disk.");
        }

        $this->info("\n[OK] Category media restoration completed successfully.");
        return 0;
    }
}

