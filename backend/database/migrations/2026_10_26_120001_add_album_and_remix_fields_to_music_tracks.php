<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add album assignment, track order, and relational remix attribution to music_tracks.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('music_tracks', function (Blueprint $table) {
            $table->foreignId('album_id')->nullable()->after('genre_id')->constrained('music_albums')->nullOnDelete();
            $table->smallInteger('track_number')->nullable()->after('album_id');
            $table->foreignId('original_track_id')->nullable()->after('rights_declaration_type')->constrained('music_tracks')->nullOnDelete();

            $table->index(['album_id', 'track_number'], 'idx_music_tracks_album_order');
            $table->index('original_track_id', 'idx_music_tracks_original_track');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('music_tracks', function (Blueprint $table) {
            $table->dropIndex('idx_music_tracks_album_order');
            $table->dropIndex('idx_music_tracks_original_track');
            $table->dropForeign(['album_id']);
            $table->dropForeign(['original_track_id']);
            $table->dropColumn(['album_id', 'track_number', 'original_track_id']);
        });
    }
};
