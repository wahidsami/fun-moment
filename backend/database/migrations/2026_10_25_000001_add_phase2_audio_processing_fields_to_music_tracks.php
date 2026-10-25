<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add Phase 2 audio media pipeline and stream rendition fields to music_tracks.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('music_tracks', function (Blueprint $table) {
            $table->string('audio_path_stream', 255)->nullable()->after('audio_path_preview');
            $table->string('media_status', 30)->default('pending')->after('audio_path_stream');
            $table->text('media_error')->nullable()->after('media_status');

            $table->string('audio_mime_type', 100)->nullable()->after('audio_format');
            $table->string('audio_codec', 50)->nullable()->after('audio_mime_type');
            $table->integer('audio_bitrate_kbps')->nullable()->after('audio_codec');
            $table->integer('audio_sample_rate')->nullable()->after('audio_bitrate_kbps');
            $table->smallInteger('audio_channels')->nullable()->after('audio_sample_rate');

            $table->integer('preview_version')->default(1)->after('preview_duration_seconds');
            $table->string('original_filename', 255)->nullable()->after('artist_name');

            $table->index(['media_status', 'status'], 'idx_music_tracks_media_ready');
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
            $table->dropIndex('idx_music_tracks_media_ready');

            $table->dropColumn([
                'audio_path_stream',
                'media_status',
                'media_error',
                'audio_mime_type',
                'audio_codec',
                'audio_bitrate_kbps',
                'audio_sample_rate',
                'audio_channels',
                'preview_version',
                'original_filename',
            ]);
        });
    }
};
