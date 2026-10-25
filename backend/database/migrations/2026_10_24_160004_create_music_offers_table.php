<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::create('music_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained('music_tracks')->cascadeOnDelete();
            $table->string('offer_type', 20); // 'free', 'rent', 'sale'
            $table->decimal('price', 10, 2)->default(0.00);
            $table->string('currency', 10)->default('SAR');
            $table->integer('rental_duration_days')->nullable(); // e.g. 7, 14, 30
            $table->string('license_type', 50)->default('in_app_streaming_only');

            // Explicit rights flags enforcing the approved product licensing rules:
            // Sale = permanent in-app listening only. No download, no commercial, no remix.
            $table->boolean('allow_download')->default(false);
            $table->boolean('commercial_use')->default(false);
            $table->boolean('remix_rights')->default(false);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['track_id', 'is_active'], 'idx_music_offers_track_active');
            $table->index(['offer_type', 'is_active'], 'idx_music_offers_type_active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('music_offers');
    }
};
