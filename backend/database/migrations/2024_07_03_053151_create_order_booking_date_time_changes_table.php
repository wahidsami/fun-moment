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
        Schema::create('order_booking_date_time_changes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('order_id');
            $table->string('date')->nullable();
            $table->string('schedule')->nullable();
            $table->longText('rejection_reason')->nullable();
            $table->boolean('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_booking_date_time_changes');
    }
};
