<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the master legal terms registry table and seeds the 4 authoritative
     * role/language combinations with display titles and null drafts.
     *
     * @return void
     */
    public function up(): void
    {
        if (!Schema::hasTable('legal_terms')) {
            Schema::create('legal_terms', function (Blueprint $table) {
                $table->id();
                $table->string('role', 20);
                $table->string('language', 10);
                $table->string('title', 255);
                $table->string('draft_title', 255)->nullable();
                $table->longText('draft_content')->nullable();
                $table->unsignedBigInteger('current_version_id')->nullable()->index();
                $table->timestamps();

                $table->unique(['role', 'language'], 'legal_terms_role_language_unique');
            });
        }

        // Seed initial authoritative master document registry records
        $now = now();
        $initialDocuments = [
            [
                'role' => 'buyer',
                'language' => 'ar',
                'title' => 'شروط وأحكام المشتري',
                'draft_title' => null,
                'draft_content' => null,
                'current_version_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'role' => 'buyer',
                'language' => 'en',
                'title' => 'Buyer Terms & Conditions',
                'draft_title' => null,
                'draft_content' => null,
                'current_version_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'role' => 'seller',
                'language' => 'ar',
                'title' => 'شروط وأحكام مقدم الخدمة',
                'draft_title' => null,
                'draft_content' => null,
                'current_version_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'role' => 'seller',
                'language' => 'en',
                'title' => 'Seller Terms & Conditions',
                'draft_title' => null,
                'draft_content' => null,
                'current_version_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($initialDocuments as $doc) {
            $exists = DB::table('legal_terms')
                ->where('role', $doc['role'])
                ->where('language', $doc['language'])
                ->exists();

            if (!$exists) {
                DB::table('legal_terms')->insert($doc);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_terms');
    }
};
