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
        Schema::create('music_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('title_en', 255);
            $table->string('title_ar', 255)->nullable();
            $table->string('artist_name', 255);
            $table->string('owner_type', 20)->default('seller'); // 'seller' or 'admin'
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('genre_id')->constrained('music_genres')->restrictOnDelete();

            $table->string('cover_image', 255)->nullable();
            $table->string('audio_path_original', 255);
            $table->string('audio_path_preview', 255)->nullable();

            $table->integer('duration_seconds')->default(0);
            $table->integer('preview_duration_seconds')->default(20);
            $table->bigInteger('file_size_bytes')->default(0);
            $table->string('audio_format', 10)->default('mp3');
            $table->integer('bpm')->nullable();
            $table->string('track_key', 10)->nullable();

            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();

            $table->string('rights_declaration_type', 50)->default('original_composition'); // original_composition, licensed_remix, public_domain
            $table->boolean('rights_agreement_accepted')->default(false);
            $table->text('rights_notes')->nullable();
            $table->timestamp('declared_at')->nullable();
            $table->string('declared_ip', 45)->nullable();

            $table->string('status', 30)->default('draft'); // draft, pending_review, approved, rejected, suspended
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->boolean('is_featured')->default(false);
            $table->integer('featured_order')->default(0);
            $table->bigInteger('play_count')->default(0);

            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'released_at'], 'idx_music_tracks_catalog');
            $table->index(['is_featured', 'featured_order'], 'idx_music_tracks_featured');
            $table->index('seller_id', 'idx_music_tracks_seller');
            $table->index('genre_id', 'idx_music_tracks_genre');
            $table->index('owner_type', 'idx_music_tracks_owner_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('music_tracks');
    }
};
