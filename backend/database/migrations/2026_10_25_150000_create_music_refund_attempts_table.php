<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Create durable music_refund_attempts table for Two-Phase Saga and reconciliation.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('music_refund_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('attempt_uuid', 100)->unique();
            $table->foreignId('order_id')->constrained('music_orders')->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();

            $table->string('destination', 40); // 'original_payment_method', 'customer_wallet'
            $table->string('payment_gateway', 40); // 'paytabs', 'wallet'
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('SAR');
            $table->decimal('subtotal_amount', 10, 2)->default(0.00);
            $table->decimal('vat_amount', 10, 2)->default(0.00);
            $table->decimal('commission_amount', 10, 2)->default(0.00);
            $table->decimal('seller_earning_amount', 10, 2)->default(0.00);
            $table->boolean('is_full_refund')->default(false);

            $table->string('status', 40)->default('initiated'); // 'initiated', 'pending_verification', 'completed', 'failed', 'declined'
            $table->string('idempotency_key', 100)->nullable();
            $table->string('original_transaction_ref', 100)->nullable();
            $table->string('provider_refund_ref', 100)->nullable();
            $table->string('cart_id', 100)->nullable();

            $table->text('reason');
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->text('reconciliation_notes')->nullable();
            $table->integer('reconciliation_attempts')->default(0);

            $table->timestamp('last_reconciled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status'], 'idx_mra_order_status');
            $table->index('status', 'idx_mra_status');
            $table->index('idempotency_key', 'idx_mra_idempotency');
            $table->index('provider_refund_ref', 'idx_mra_provider_ref');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('music_refund_attempts');
    }
};
