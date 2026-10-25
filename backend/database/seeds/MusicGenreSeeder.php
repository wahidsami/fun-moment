<?php

namespace Database\Seeders;

use App\MusicGenre;
use Illuminate\Database\Seeder;

class MusicGenreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Idempotent: Only inserts genres if the slug does not exist,
     * preserving administrator customizations and translations.
     *
     * @return void
     */
    public function run()
    {
        $genres = [
            [
                'name_en' => 'House',
                'name_ar' => 'هاوس',
                'slug' => 'house',
                'icon' => 'disc',
                'description_en' => 'Electronic dance rhythms with steady beats, deep baselines, and groovy synths.',
                'description_ar' => 'إيقاعات رقص إلكترونية مع نغمات جهير عميقة وتوليفات حماسية.',
                'sort_order' => 1,
                'status' => 1,
            ],
            [
                'name_en' => 'Techno',
                'name_ar' => 'تكنو',
                'slug' => 'techno',
                'icon' => 'activity',
                'description_en' => 'Hypnotic repetitive beats and futuristic industrial soundscapes.',
                'description_ar' => 'إيقاعات تكرارية حيوية وأصوات إلكترونية متطورة ومميزة.',
                'sort_order' => 2,
                'status' => 1,
            ],
            [
                'name_en' => 'Hip-Hop & R&B',
                'name_ar' => 'هيب هوب وآر أند بي',
                'slug' => 'hip-hop-rnb',
                'icon' => 'mic',
                'description_en' => 'Urban grooves, lyrical tracks, and smooth melodies.',
                'description_ar' => 'إيقاعات معاصرة ونغمات سلسة مع موسيقى حديثة.',
                'sort_order' => 3,
                'status' => 1,
            ],
            [
                'name_en' => 'Arabic Pop & Remix',
                'name_ar' => 'بوب وريمكس عربي',
                'slug' => 'arabic-pop-remix',
                'icon' => 'music',
                'description_en' => 'Top regional Arabic hits, club edits, and oriental dance remixes.',
                'description_ar' => 'أفضل الأغاني العربية وإصدارات الريمكس الحصرية والمميزة.',
                'sort_order' => 4,
                'status' => 1,
            ],
            [
                'name_en' => 'EDM & Dance',
                'name_ar' => 'موسيقى الرقص الإلكترونية',
                'slug' => 'edm-dance',
                'icon' => 'zap',
                'description_en' => 'High-energy festival anthems, progressive drops, and electro beats.',
                'description_ar' => 'موسيقى حماسية وإيقاعات احتفالية للمناسبات الكبرى.',
                'sort_order' => 5,
                'status' => 1,
            ],
            [
                'name_en' => 'Khaleeji & Oriental Fusion',
                'name_ar' => 'فيوجن وخليجي تقليدي',
                'slug' => 'khaleeji-fusion',
                'icon' => 'radio',
                'description_en' => 'Authentic oud, darbuka, and Khaleeji instruments blended with modern electronic arrangements.',
                'description_ar' => 'أصالة العود والإيقاعات الشرقية والخليجية ممتزجة مع التوزيع الحديث.',
                'sort_order' => 6,
                'status' => 1,
            ],
            [
                'name_en' => 'Chillout & Lounge',
                'name_ar' => 'تشيل أوت ولاونج',
                'slug' => 'chillout-lounge',
                'icon' => 'coffee',
                'description_en' => 'Relaxing ambient backgrounds, downtempo grooves, and sunset acoustics.',
                'description_ar' => 'موسيقى هادئة وأجواء مريحة تناسب الاسترخاء واللقاءات الخاصة.',
                'sort_order' => 7,
                'status' => 1,
            ],
        ];

        foreach ($genres as $genreData) {
            MusicGenre::firstOrCreate(
                ['slug' => $genreData['slug']],
                $genreData
            );
        }
    }
}
