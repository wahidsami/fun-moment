<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('wallets')) {
            Schema::create('wallets', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->unique();
                $table->unsignedBigInteger('buyer_id')->nullable()->index();
                $table->decimal('balance', 14, 2)->default(0.00);
                $table->decimal('pending_balance', 14, 2)->default(0.00);
                $table->decimal('total_earned', 14, 2)->default(0.00);
                $table->decimal('total_spent', 14, 2)->default(0.00);
                $table->smallInteger('status')->default(1)->comment('1: active, 0: frozen/suspended');
                $table->string('currency', 10)->default('SAR');
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });

            // Enforce non-negative balance constraints in PostgreSQL
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_wallet_balance_non_negative CHECK (balance >= 0);');
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_wallet_pending_balance_non_negative CHECK (pending_balance >= 0);');
        }

        if (!Schema::hasTable('wallet_histories')) {
            Schema::create('wallet_histories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('wallet_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('buyer_id')->nullable()->index();
                $table->string('entry_type', 30)->default('credit')->comment('credit, debit, hold, release, refund, adjustment, payout');
                $table->decimal('amount', 14, 2);
                $table->decimal('balance_before', 14, 2)->default(0.00);
                $table->decimal('balance_after', 14, 2)->default(0.00);
                $table->string('payment_gateway', 50)->nullable()->default('wallet');
                $table->string('payment_status', 30)->default('pending')->comment('pending, complete, cancelled, rejected');
                $table->smallInteger('status')->default(1);
                $table->string('reference_type', 50)->nullable()->comment('order, payout_request, deposit, admin_adjustment, job_hire');
                $table->string('reference_id', 100)->nullable();
                $table->string('transaction_id', 100)->unique();
                $table->text('description_en')->nullable();
                $table->text('description_ar')->nullable();
                $table->string('manual_payment_image', 191)->nullable();
                $table->unsignedBigInteger('admin_id')->nullable()->index();
                $table->text('admin_note')->nullable();
                $table->text('metadata')->nullable();
                $table->timestamps();

                $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_histories');
        Schema::dropIfExists('wallets');
    }
};
