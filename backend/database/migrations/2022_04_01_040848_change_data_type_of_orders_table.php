<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ChangeDataTypeOfOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->changeColumnType('orders', 'city', 'bigint(11)', 'BIGINT');
        $this->changeColumnType('orders', 'country', 'bigint(11)', 'BIGINT');
        $this->changeColumnType('orders', 'area', 'bigint(11)', 'BIGINT');
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
        $this->changeColumnType('orders', 'city', 'tinyint(11)', 'SMALLINT');
        $this->changeColumnType('orders', 'country', 'tinyint(11)', 'SMALLINT');
        $this->changeColumnType('orders', 'area', 'tinyint(11)', 'SMALLINT');
    }
}
