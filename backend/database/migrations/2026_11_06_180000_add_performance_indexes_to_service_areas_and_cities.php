<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddPerformanceIndexesToServiceAreasAndCities extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('service_cities')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('CREATE INDEX IF NOT EXISTS idx_service_cities_country_status ON service_cities (country_id, status, service_city);');
            } else {
                Schema::table('service_cities', function (Blueprint $table) {
                    $table->index(['country_id', 'status', 'service_city'], 'idx_service_cities_country_status');
                });
            }
        }

        if (Schema::hasTable('service_areas')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('CREATE INDEX IF NOT EXISTS idx_service_areas_city_country_status ON service_areas (service_city_id, country_id, status, service_area);');
            } else {
                Schema::table('service_areas', function (Blueprint $table) {
                    $table->index(['service_city_id', 'country_id', 'status', 'service_area'], 'idx_service_areas_city_country_status');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('service_areas')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS idx_service_areas_city_country_status;');
            } else {
                Schema::table('service_areas', function (Blueprint $table) {
                    $table->dropIndex('idx_service_areas_city_country_status');
                });
            }
        }

        if (Schema::hasTable('service_cities')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS idx_service_cities_country_status;');
            } else {
                Schema::table('service_cities', function (Blueprint $table) {
                    $table->dropIndex('idx_service_cities_country_status');
                });
            }
        }
    }
}
