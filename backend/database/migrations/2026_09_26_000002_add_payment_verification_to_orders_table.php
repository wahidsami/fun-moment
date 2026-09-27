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
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'payment_verified_at')) {
                $table->timestamp('payment_verified_at')->nullable()->after('transaction_id');
            }
            if (!Schema::hasColumn('orders', 'payment_details')) {
                $table->text('payment_details')->nullable()->after('payment_verified_at');
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
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'payment_details')) {
                $table->dropColumn('payment_details');
            }
            if (Schema::hasColumn('orders', 'payment_verified_at')) {
                $table->dropColumn('payment_verified_at');
            }
        });
    }
};
