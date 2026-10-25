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
        Schema::create('music_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('track_id')->constrained('music_tracks')->cascadeOnDelete();
            $table->foreignId('offer_id')->constrained('music_offers')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('music_orders')->nullOnDelete();

            $table->string('access_type', 30)->default('stream_only'); // strictly 'stream_only'
            $table->string('license_type', 50)->default('in_app_streaming_only');

            // Explicit rights constraints:
            $table->boolean('allow_download')->default(false);
            $table->boolean('commercial_use')->default(false);
            $table->boolean('remix_rights')->default(false);

            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('expires_at')->nullable(); // NULL = perpetual in-app streaming for Sale; non-null for Rent
            $table->boolean('is_active')->default(true);

            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'track_id', 'is_active'], 'idx_music_entitlements_lookup');
            $table->index(['expires_at', 'is_active'], 'idx_music_entitlements_expires');
            $table->index('order_id', 'idx_music_entitlements_order');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('music_entitlements');
    }
};
