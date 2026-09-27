<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriptionTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->string('title', 191);
                $table->string('type', 50)->comment('monthly, yearly, lifetime');
                $table->decimal('price', 12, 2)->default(0.00);
                $table->bigInteger('connect')->default(0);
                $table->bigInteger('service')->default(0);
                $table->bigInteger('job')->default(0);
                $table->text('description')->nullable();
                $table->tinyInteger('status')->default(1)->comment('1=active, 0=inactive');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('seller_subscriptions')) {
            Schema::create('seller_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('seller_id')->unique()->index();
                $table->unsignedBigInteger('subscription_id')->nullable()->index();
                $table->string('type', 50)->default('monthly');
                $table->decimal('price', 12, 2)->default(0.00);
                $table->bigInteger('connect')->default(0);
                $table->bigInteger('service')->default(0);
                $table->bigInteger('job')->default(0);
                $table->bigInteger('initial_connect')->default(0);
                $table->bigInteger('initial_service')->default(0);
                $table->bigInteger('initial_job')->default(0);
                $table->dateTime('expire_date')->nullable();
                $table->string('payment_gateway', 50)->nullable();
                $table->string('payment_status', 50)->default('pending');
                $table->tinyInteger('status')->default(1)->comment('1=active, 0=expired/inactive');
                $table->timestamps();

                $table->foreign('seller_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('subscription_histories')) {
            Schema::create('subscription_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('seller_id')->index();
                $table->unsignedBigInteger('subscription_id')->nullable()->index();
                $table->string('type', 50)->nullable();
                $table->decimal('price', 12, 2)->default(0.00);
                $table->bigInteger('connect')->default(0);
                $table->bigInteger('service')->default(0);
                $table->bigInteger('job')->default(0);
                $table->dateTime('expire_date')->nullable();
                $table->string('payment_gateway', 50)->nullable();
                $table->string('payment_status', 50)->default('pending');
                $table->tinyInteger('status')->default(1);
                $table->timestamps();

                $table->foreign('seller_id')->references('id')->on('users')->onDelete('cascade');
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
        Schema::dropIfExists('subscription_histories');
        Schema::dropIfExists('seller_subscriptions');
        Schema::dropIfExists('subscriptions');
    }
}
