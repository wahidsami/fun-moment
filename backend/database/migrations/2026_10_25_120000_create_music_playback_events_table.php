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
        Schema::create('music_playback_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('track_id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('guest_session_id', 64)->nullable()->index();
            $table->string('playback_session_id', 64)->index();
            $table->string('idempotency_key', 128)->unique();
            $table->string('event_type', 32); // playback_started, listening_progress, preview_completed, full_track_completed, playback_failed
            $table->string('playback_mode', 16)->default('preview'); // preview, full
            $table->decimal('duration_seconds', 8, 2)->default(0.00);
            $table->decimal('position_seconds', 8, 2)->default(0.00);
            $table->boolean('counted_play')->default(false)->index();
            $table->timestamp('client_timestamp')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('track_id')
                ->references('id')
                ->on('music_tracks')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['track_id', 'created_at'], 'idx_playback_track_created');
            $table->index(['playback_session_id', 'event_type'], 'idx_playback_session_event');
            $table->index(['event_type', 'created_at'], 'idx_playback_event_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_playback_events');
    }
};
