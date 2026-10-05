<?php

use Illuminate\Database\Migrations\Migration;

class SeedSaudiLocationsMasterData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        require_once database_path('seeds/SaudiLocationMasterDataSeeder.php');
        $seeder = new \Database\Seeders\SaudiLocationMasterDataSeeder();
        $seeder->run();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Master data is additive and preserved; no destructive drop.
    }
}
