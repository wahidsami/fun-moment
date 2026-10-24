<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCarouselFieldsToSlidersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sliders', function (Blueprint $table) {
            if (!Schema::hasColumn('sliders', 'title_ar')) {
                $table->string('title_ar')->nullable()->after('title');
            }
            if (!Schema::hasColumn('sliders', 'sub_title_ar')) {
                $table->string('sub_title_ar')->nullable()->after('sub_title');
            }
            if (!Schema::hasColumn('sliders', 'btn_text_en')) {
                $table->string('btn_text_en')->nullable()->after('sub_title_ar');
            }
            if (!Schema::hasColumn('sliders', 'btn_text_ar')) {
                $table->string('btn_text_ar')->nullable()->after('btn_text_en');
            }
            if (!Schema::hasColumn('sliders', 'action_type')) {
                $table->string('action_type')->default('none')->after('service_id');
            }
            if (!Schema::hasColumn('sliders', 'action_target')) {
                $table->string('action_target')->nullable()->after('action_type');
            }
            if (!Schema::hasColumn('sliders', 'order')) {
                $table->integer('order')->default(0)->after('action_target');
            }
            if (!Schema::hasColumn('sliders', 'status')) {
                $table->tinyInteger('status')->default(1)->after('order');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sliders', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['title_ar', 'sub_title_ar', 'btn_text_en', 'btn_text_ar', 'action_type', 'action_target', 'order', 'status'] as $col) {
                if (Schema::hasColumn('sliders', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
}
