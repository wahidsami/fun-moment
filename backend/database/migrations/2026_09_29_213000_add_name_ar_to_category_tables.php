<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Add name_ar to categories table
        if (!Schema::hasColumn('categories', 'name_ar')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->string('name_ar', 191)->nullable();
            });
        }

        // 2. Add name_ar to subcategories table
        if (!Schema::hasColumn('subcategories', 'name_ar')) {
            Schema::table('subcategories', function (Blueprint $table) {
                $table->string('name_ar', 191)->nullable();
            });
        }

        // 3. Add name_ar to child_categories table
        if (!Schema::hasColumn('child_categories', 'name_ar')) {
            Schema::table('child_categories', function (Blueprint $table) {
                $table->string('name_ar', 191)->nullable();
            });
        }

        // 4. One-time backfill for the 7 approved main categories
        $backfills = [
            1 => ['name_ar' => 'الموسيقى والـ DJ', 'slug' => 'music-dj'],
            2 => ['name_ar' => 'الأطعمة والضيافة', 'slug' => 'food-hospitality'],
            3 => ['name_ar' => 'الصوت والإضاءة', 'slug' => 'sound-lighting'],
            4 => ['name_ar' => 'تجهيز الفعاليات والمعدات', 'slug' => 'event-setup-equipment'],
            5 => ['name_ar' => 'الديكور وتنسيق المناسبات', 'slug' => 'decor-event-styling'],
            6 => ['name_ar' => 'التصوير والفيديو', 'slug' => 'photography-video'],
            7 => ['name_ar' => 'الترفيه والأنشطة', 'slug' => 'entertainment-activities'],
        ];

        foreach ($backfills as $id => $data) {
            DB::table('categories')
                ->where('id', $id)
                ->update(['name_ar' => $data['name_ar']]);

            DB::table('categories')
                ->where('slug', $data['slug'])
                ->update(['name_ar' => $data['name_ar']]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('child_categories', 'name_ar')) {
            Schema::table('child_categories', function (Blueprint $table) {
                $table->dropColumn('name_ar');
            });
        }

        if (Schema::hasColumn('subcategories', 'name_ar')) {
            Schema::table('subcategories', function (Blueprint $table) {
                $table->dropColumn('name_ar');
            });
        }

        if (Schema::hasColumn('categories', 'name_ar')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('name_ar');
            });
        }
    }
};
