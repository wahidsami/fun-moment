<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates user terms acceptances table for durable, auditable legal consent records.
     *
     * @return void
     */
    public function up(): void
    {
        if (!Schema::hasTable('user_terms_acceptances')) {
            Schema::create('user_terms_acceptances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('role', 20);
                $table->unsignedBigInteger('legal_term_id');
                $table->unsignedBigInteger('legal_term_version_id');
                $table->unsignedInteger('version_number');
                $table->string('content_hash', 64);
                $table->string('language', 10);
                $table->timestamp('accepted_at');
                $table->timestamps();

                $table->index('user_id');
                $table->index('role');
                $table->index('legal_term_id');
                $table->index('legal_term_version_id');
                $table->index(['user_id', 'legal_term_id'], 'user_term_lookup_idx');

                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }

                $table->foreign('legal_term_id')
                    ->references('id')
                    ->on('legal_terms')
                    ->onDelete('cascade');

                $table->foreign('legal_term_version_id')
                    ->references('id')
                    ->on('legal_term_versions')
                    ->onDelete('restrict');
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
        Schema::dropIfExists('user_terms_acceptances');
    }
};
