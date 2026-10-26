<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Create music_albums table for first-party and curated album collections.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('music_albums', function (Blueprint $table) {
            $table->id();
            $table->string('title_en', 255);
            $table->string('title_ar', 255)->nullable();
            $table->string('artist_name', 255);
            $table->string('cover_image', 255)->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->foreignId('genre_id')->constrained('music_genres')->restrictOnDelete();

            $table->string('status', 30)->default('draft'); // draft, published, archived
            $table->string('owner_type', 20)->default('admin'); // admin, seller
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_featured')->default(false);
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'released_at'], 'idx_music_albums_catalog');
            $table->index('genre_id', 'idx_music_albums_genre');
            $table->index('owner_type', 'idx_music_albums_owner');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('music_albums');
    }
};
