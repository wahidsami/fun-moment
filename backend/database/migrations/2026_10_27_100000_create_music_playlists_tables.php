<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('music_playlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('cover_image', 255)->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index('user_id', 'idx_music_playlists_user');
        });

        Schema::create('music_playlist_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained('music_playlists')->cascadeOnDelete();
            $table->foreignId('track_id')->constrained('music_tracks')->cascadeOnDelete();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->unique(['playlist_id', 'track_id'], 'uq_playlist_track');
            $table->index(['playlist_id', 'position'], 'idx_playlist_tracks_order');
            $table->index('track_id', 'idx_playlist_tracks_track');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_playlist_tracks');
        Schema::dropIfExists('music_playlists');
    }
};
