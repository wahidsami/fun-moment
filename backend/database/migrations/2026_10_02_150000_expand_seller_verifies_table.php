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
        Schema::table('seller_verifies', function (Blueprint $table) {
            // Individual Provider fields
            $table->string('national_id_number', 50)->nullable()->after('national_id');
            $table->string('national_id_document', 255)->nullable()->after('national_id_number');
            $table->string('license_number', 50)->nullable()->after('national_id_document');
            $table->string('license_document', 255)->nullable()->after('license_number');
            $table->boolean('is_band_or_group')->default(false)->after('license_document');
            $table->string('band_name', 191)->nullable()->after('is_band_or_group');
            $table->integer('band_members_count')->nullable()->after('band_name');

            // Company Provider fields
            $table->string('company_name', 191)->nullable()->after('band_members_count');
            $table->string('cr_number', 50)->nullable()->after('company_name');
            $table->string('cr_document', 255)->nullable()->after('cr_number');
            $table->string('contact_person_name', 191)->nullable()->after('cr_document');
            $table->string('contact_person_email', 191)->nullable()->after('contact_person_name');
            $table->string('contact_person_phone', 50)->nullable()->after('contact_person_email');

            // Verification Audit & Lifecycle fields
            $table->text('rejection_reason')->nullable()->after('status');
            $table->unsignedBigInteger('verified_by')->nullable()->after('rejection_reason');
            $table->timestamp('verified_at')->nullable()->after('verified_by');

            // Indexes for fast lookup
            $table->index('cr_number');
            $table->index('national_id_number');
            $table->index('status');

            // Foreign key to admins
            $table->foreign('verified_by')->references('id')->on('admins')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('seller_verifies', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropIndex(['cr_number']);
            $table->dropIndex(['national_id_number']);
            $table->dropIndex(['status']);

            $table->dropColumn([
                'national_id_number',
                'national_id_document',
                'license_number',
                'license_document',
                'is_band_or_group',
                'band_name',
                'band_members_count',
                'company_name',
                'cr_number',
                'cr_document',
                'contact_person_name',
                'contact_person_email',
                'contact_person_phone',
                'rejection_reason',
                'verified_by',
                'verified_at',
            ]);
        });
    }
};
