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
     * Creates immutable published legal document versions table and adds
     * the foreign key constraint on legal_terms.current_version_id.
     *
     * @return void
     */
    public function up(): void
    {
        if (!Schema::hasTable('legal_term_versions')) {
            Schema::create('legal_term_versions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('legal_term_id');
                $table->unsignedInteger('version_number');
                $table->string('title', 255);
                $table->longText('content');
                $table->string('content_hash', 64);
                $table->unsignedBigInteger('published_by')->nullable();
                $table->timestamp('published_at');
                $table->timestamps();

                $table->unique(['legal_term_id', 'version_number'], 'term_version_unique');
                $table->index('legal_term_id');
                $table->index('published_by');

                $table->foreign('legal_term_id')
                    ->references('id')
                    ->on('legal_terms')
                    ->onDelete('cascade');

                if (Schema::hasTable('admins')) {
                    $table->foreign('published_by')
                        ->references('id')
                        ->on('admins')
                        ->nullOnDelete();
                }
            });
        }

        // Add foreign key constraint to legal_terms.current_version_id
        if (Schema::hasTable('legal_terms') && Schema::hasTable('legal_term_versions')) {
            Schema::table('legal_terms', function (Blueprint $table) {
                $table->foreign('current_version_id')
                    ->references('id')
                    ->on('legal_term_versions')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        if (Schema::hasTable('legal_terms')) {
            // Drop foreign key from legal_terms before dropping legal_term_versions table
            Schema::table('legal_terms', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['current_version_id']);
                }
            });
        }

        Schema::dropIfExists('legal_term_versions');
    }
};
