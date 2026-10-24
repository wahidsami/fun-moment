<?php

namespace Database\Seeders;

use App\Slider;
use Illuminate\Database\Seeder;

class HomeCarouselSeeder extends Seeder
{
    /**
     * Seed initial promotional slides preserving current app content.
     */
    public function run()
    {
        $initialSlides = [
            [
                'title' => 'Book a DJ',
                'title_ar' => 'حجز دي جي وموسيقى حية',
                'sub_title' => 'Premium entertainment for Riyadh and Saudi Arabia',
                'sub_title_ar' => 'ترفيه متميز للرياض والمملكة العربية السعودية',
                'background_image' => 'assets/images/app/home/fun_moment_home_hero_01.png',
                'btn_text_en' => 'Book Now',
                'btn_text_ar' => 'احجز الآن',
                'action_type' => 'none',
                'action_target' => null,
                'order' => 0,
                'status' => 1,
            ],
            [
                'title' => 'DJ Performance & Events',
                'title_ar' => 'عروض وحفلات الدي جي الحصرية',
                'sub_title' => 'Everything you need for music and events',
                'sub_title_ar' => 'كل ما تحتاجه للموسيقى والفعاليات',
                'background_image' => 'assets/images/app/home/fun_moment_dj_performance_01.png',
                'btn_text_en' => 'Explore',
                'btn_text_ar' => 'استكشف',
                'action_type' => 'none',
                'action_target' => null,
                'order' => 1,
                'status' => 1,
            ],
            [
                'title' => 'Rent Party Equipment',
                'title_ar' => 'تأجير معدات وأجهزة الحفلات',
                'sub_title' => 'Everything you need for music and events',
                'sub_title_ar' => 'كل ما تحتاجه لتجهيز الحفل باحترافية',
                'background_image' => 'assets/images/app/equipment/fun_moment_equipment_hero_01.png',
                'btn_text_en' => 'View Equipment',
                'btn_text_ar' => 'عرض المعدات',
                'action_type' => 'none',
                'action_target' => null,
                'order' => 2,
                'status' => 1,
            ],
            [
                'title' => 'Riyadh Nights',
                'title_ar' => 'ليالي الرياض الترفيهية',
                'sub_title' => 'Premium entertainment for Riyadh and Saudi Arabia',
                'sub_title_ar' => 'أجواء استثنائية وعروض ترفيهية متكاملة',
                'background_image' => 'assets/images/app/home/fun_moment_dj_riyadh_skyline_01.png',
                'btn_text_en' => 'Discover',
                'btn_text_ar' => 'اكتشف المزيد',
                'action_type' => 'none',
                'action_target' => null,
                'order' => 3,
                'status' => 1,
            ],
            [
                'title' => 'Discover Your Next Event',
                'title_ar' => 'اكتشف فعاليتك القادمة',
                'sub_title' => 'Everything you need for music and events',
                'sub_title_ar' => 'أفضل المنظمين ومزودي الخدمات في المملكة',
                'background_image' => 'assets/images/app/home/fun_moment_riyadh_event_01.png',
                'btn_text_en' => 'Find Out',
                'btn_text_ar' => 'تصفح الآن',
                'action_type' => 'none',
                'action_target' => null,
                'order' => 4,
                'status' => 1,
            ],
            [
                'title' => 'Sound & Stage Lighting',
                'title_ar' => 'أنظمة الصوت وإضاءات المسارح',
                'sub_title' => 'Everything you need for music and events',
                'sub_title_ar' => 'تقنيات إضاءة وصوت متطورة لجميع المناسبات',
                'background_image' => 'assets/images/app/equipment/fun_moment_party_lighting_01.png',
                'btn_text_en' => 'View Services',
                'btn_text_ar' => 'عرض الخدمات',
                'action_type' => 'none',
                'action_target' => null,
                'order' => 5,
                'status' => 1,
            ],
        ];

        // If existing slider with ID 1 is the placeholder "default-slider.jpg", update it
        $first = Slider::find(1);
        if ($first && ($first->background_image === 'default-slider.jpg' || empty($first->title_ar))) {
            $first->update($initialSlides[0]);
            array_shift($initialSlides);
        }

        // Insert remaining slides if count < 6
        if (Slider::count() < 6) {
            foreach ($initialSlides as $slideData) {
                if (!Slider::where('title', $slideData['title'])->exists()) {
                    Slider::create($slideData);
                }
            }
        }
    }
}
