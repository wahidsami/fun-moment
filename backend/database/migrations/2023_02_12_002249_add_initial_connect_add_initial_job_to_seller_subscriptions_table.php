<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInitialConnectAddInitialJobToSellerSubscriptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('seller_subscriptions')){
            return;
        }

        Schema::table('seller_subscriptions', function (Blueprint $table) {
            if(!Schema::hasColumn('seller_subscriptions','service')){
                $table->bigInteger('initial_service')->default(0)->after('initial_connect');
            }

            if(!Schema::hasColumn('seller_subscriptions','service')){
                $table->bigInteger('initial_job')->default(0)->after('initial_service');
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
        Schema::table('seller_subscriptions', function (Blueprint $table) {
            $table->dropColumn('initial_service');
            $table->dropColumn('initial_job');
        });
    }
}