<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ChangeDataTypeOfServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->changeColumnType('services', 'category_id', 'bigint(11)', 'BIGINT');
        $this->changeColumnType('services', 'subcategory_id', 'bigint(11)', 'BIGINT');
        $this->changeColumnType('services', 'seller_id', 'bigint(11)', 'BIGINT');
        $this->changeColumnType('services', 'service_city_id', 'bigint(11)', 'BIGINT');
    }

    public function changeColumnType($table, $column, $mysqlType, $pgsqlType)
    {
        if (!Schema::hasColumn($table, $column)) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE {$pgsqlType}");
            return;
        }

        DB::statement("ALTER TABLE {$table} CHANGE {$column} {$column} {$mysqlType}");
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->changeColumnType('services', 'category_id', 'tinyint(11)', 'SMALLINT');
        $this->changeColumnType('services', 'subcategory_id', 'tinyint(11)', 'SMALLINT');
        $this->changeColumnType('services', 'seller_id', 'tinyint(11)', 'SMALLINT');
        $this->changeColumnType('services', 'service_city_id', 'tinyint(11)', 'SMALLINT');
    }
}
