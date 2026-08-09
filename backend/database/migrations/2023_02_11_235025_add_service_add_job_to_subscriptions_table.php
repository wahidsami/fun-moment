<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddServiceAddJobToSubscriptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('subscriptions')){
            return;
        }
        Schema::table('subscriptions', function (Blueprint $table) {
            if(!Schema::hasColumn('subscriptions','service')){
                $table->bigInteger('service')->default(0)->after('connect');
            }
            if(!Schema::hasColumn('subscriptions','job')){
                $table->bigInteger('job')->default(0)->after('service');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('service');
            $table->dropColumn('job');
        });
    }
}