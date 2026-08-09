<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsServiceOnToServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('services', 'is_service_on')) {
            Schema::table('services', function (Blueprint $table) {
                $table->tinyInteger('is_service_on')->default(1);
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
        if (Schema::hasColumn('services', 'is_service_on')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('is_service_on');
            });
        }
    }
}
