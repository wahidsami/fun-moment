<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add historical attribution and snapshot preservation fields to music tables.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('music_tracks', function (Blueprint $table) {
            $table->unsignedBigInteger('submitted_by_user_id')->nullable()->after('seller_id');
            $table->string('original_creator_name', 255)->nullable()->after('submitted_by_user_id');
            $table->unsignedBigInteger('rights_accepted_by_user_id')->nullable()->after('rights_agreement_accepted');
            $table->string('rights_accepted_by_name', 255)->nullable()->after('rights_accepted_by_user_id');

            $table->index('submitted_by_user_id', 'idx_music_tracks_submitted_by');
            $table->index('rights_accepted_by_user_id', 'idx_music_tracks_rights_user');
        });

        Schema::table('music_orders', function (Blueprint $table) {
            $table->string('seller_name', 255)->nullable()->after('seller_id');
            $table->string('seller_email', 255)->nullable()->after('seller_name');
            $table->string('buyer_name', 255)->nullable()->after('buyer_id');
            $table->string('buyer_email', 255)->nullable()->after('buyer_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('music_orders', function (Blueprint $table) {
            $table->dropColumn(['seller_name', 'seller_email', 'buyer_name', 'buyer_email']);
        });

        Schema::table('music_tracks', function (Blueprint $table) {
            $table->dropIndex('idx_music_tracks_submitted_by');
            $table->dropIndex('idx_music_tracks_rights_user');
            $table->dropColumn([
                'submitted_by_user_id',
                'original_creator_name',
                'rights_accepted_by_user_id',
                'rights_accepted_by_name',
            ]);
        });
    }
};
