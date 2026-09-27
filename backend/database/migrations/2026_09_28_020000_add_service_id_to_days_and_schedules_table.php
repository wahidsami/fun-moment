<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddServiceIdToDaysAndSchedulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('days') && !Schema::hasColumn('days', 'service_id')) {
            Schema::table('days', function (Blueprint $table) {
                $table->unsignedBigInteger('service_id')->nullable()->after('seller_id')->index();
                $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('schedules') && !Schema::hasColumn('schedules', 'service_id')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->unsignedBigInteger('service_id')->nullable()->after('seller_id')->index();
                $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('schedules') && Schema::hasColumn('schedules', 'service_id')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropForeign(['service_id']);
                $table->dropColumn('service_id');
            });
        }

        if (Schema::hasTable('days') && Schema::hasColumn('days', 'service_id')) {
            Schema::table('days', function (Blueprint $table) {
                $table->dropForeign(['service_id']);
                $table->dropColumn('service_id');
            });
        }
    }
}
