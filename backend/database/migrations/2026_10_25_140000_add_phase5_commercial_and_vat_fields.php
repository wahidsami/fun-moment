<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add Phase 5 commercial, VAT, seller tax profile, and financial ledger tables.
     *
     * @return void
     */
    public function up()
    {
        // 1. Seller Tax Profile Fields on users table
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('vat_registered')->default(false)->after('business_registration');
            $table->string('vat_registration_number', 30)->nullable()->after('vat_registered');
            $table->string('vat_verification_status', 30)->default('unverified')->after('vat_registration_number');
            $table->string('tax_residence_country', 2)->default('SA')->after('vat_verification_status');
            $table->timestamp('tax_profile_verified_at')->nullable()->after('tax_residence_country');
            $table->text('tax_profile_notes')->nullable()->after('tax_profile_verified_at');

            $table->index('vat_verification_status', 'idx_users_vat_status');
            $table->index('vat_registration_number', 'idx_users_vat_number');
        });

        // 2. Financial Snapshot, VAT & Refund Fields on music_orders table
        Schema::table('music_orders', function (Blueprint $table) {
            $table->decimal('subtotal', 10, 2)->default(0.00)->after('offer_type');
            $table->decimal('vat_rate', 5, 2)->default(0.00)->after('subtotal');
            $table->decimal('vat_amount', 10, 2)->default(0.00)->after('vat_rate');
            $table->decimal('commission_vat_amount', 10, 2)->default(0.00)->after('commission_amount');
            $table->string('tax_treatment', 40)->default('deemed_supplier')->after('commission_vat_amount');
            $table->string('price_display_mode', 20)->default('exclusive')->after('tax_treatment');

            $table->decimal('refund_amount', 10, 2)->default(0.00)->after('refund_reason');
            $table->decimal('refund_amount_wallet', 10, 2)->default(0.00)->after('refund_amount');
            $table->decimal('refund_amount_gateway', 10, 2)->default(0.00)->after('refund_amount_wallet');
            $table->string('refund_destination', 40)->nullable()->after('refund_amount_gateway'); // 'customer_wallet', 'original_payment_method'
            $table->string('refund_id', 100)->nullable()->after('refund_destination');
            $table->json('refund_metadata')->nullable()->after('refund_id');
            $table->json('financial_snapshot')->nullable()->after('refund_metadata');
        });

        // 3. Dedicated Immutable Music Seller Ledger Entries Table
        Schema::create('music_seller_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('music_orders')->restrictOnDelete();
            $table->foreignId('track_id')->constrained('music_tracks')->restrictOnDelete();

            $table->string('entry_type', 40); // 'earning', 'refund_reversal', 'adjustment', 'payout_hold'
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('commission_deducted', 10, 2)->default(0.00);
            $table->decimal('vat_deducted', 10, 2)->default(0.00);
            $table->decimal('net_earning', 10, 2);
            $table->string('currency', 10)->default('SAR');
            $table->string('status', 30)->default('payable'); // 'payable', 'pending', 'held', 'reversed', 'settled'
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('seller_id', 'idx_music_seller_ledger_seller');
            $table->index('order_id', 'idx_music_seller_ledger_order');
            $table->index('track_id', 'idx_music_seller_ledger_track');
            $table->index('entry_type', 'idx_music_seller_ledger_type');
            $table->index('status', 'idx_music_seller_ledger_status');
            $table->index('created_at', 'idx_music_seller_ledger_created');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('music_seller_ledger_entries');

        Schema::table('music_orders', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal',
                'vat_rate',
                'vat_amount',
                'commission_vat_amount',
                'tax_treatment',
                'price_display_mode',
                'refund_amount',
                'refund_amount_wallet',
                'refund_amount_gateway',
                'refund_destination',
                'refund_id',
                'refund_metadata',
                'financial_snapshot',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_vat_status');
            $table->dropIndex('idx_users_vat_number');
            $table->dropColumn([
                'vat_registered',
                'vat_registration_number',
                'vat_verification_status',
                'tax_residence_country',
                'tax_profile_verified_at',
                'tax_profile_notes',
            ]);
        });
    }
};
