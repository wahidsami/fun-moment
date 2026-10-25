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
        Schema::create('music_orders', function (Blueprint $table) {
            $table->id();
            $table->string('invoice', 64)->unique();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('track_id')->constrained('music_tracks')->restrictOnDelete();
            $table->foreignId('offer_id')->constrained('music_offers')->restrictOnDelete();

            $table->string('offer_type', 20); // 'rent', 'sale'
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('SAR');
            $table->decimal('commission_rate', 5, 2)->default(0.00);
            $table->decimal('commission_amount', 10, 2)->default(0.00);
            $table->decimal('seller_earning', 10, 2)->default(0.00);

            $table->string('payment_gateway', 50); // 'paytabs', 'wallet', 'manual_payment'
            $table->string('payment_status', 30)->default('pending'); // 'pending', 'completed', 'failed', 'refunded', 'cancelled'
            $table->string('transaction_id', 100)->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->json('payment_details')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('refund_reason')->nullable();
            $table->timestamps();

            $table->index('buyer_id', 'idx_music_orders_buyer');
            $table->index('seller_id', 'idx_music_orders_seller');
            $table->index('track_id', 'idx_music_orders_track');
            $table->index('payment_status', 'idx_music_orders_status');
            $table->index('created_at', 'idx_music_orders_created');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('music_orders');
    }
};
