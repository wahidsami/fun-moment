<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Aligns seller_verifies.seller_id from varchar/string to BIGINT
     * to ensure strict PostgreSQL 17 type compatibility with users.id (bigint).
     */
    public function up(): void
    {
        if (!Schema::hasTable('seller_verifies') || !Schema::hasColumn('seller_verifies', 'seller_id')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // 1. Inspect existing column data_type
            $columnInfo = DB::selectOne("
                SELECT data_type 
                FROM information_schema.columns 
                WHERE table_name = 'seller_verifies' AND column_name = 'seller_id'
            ");

            $currentType = strtolower($columnInfo->data_type ?? '');

            // Idempotency: If already bigint, ensure index and return safely
            if ($currentType === 'bigint') {
                DB::statement('CREATE INDEX IF NOT EXISTS seller_verifies_seller_id_index ON seller_verifies (seller_id)');
                return;
            }

            // 2. Pre-conversion safety assertion: verify all non-null seller_id values are numeric and within BIGINT range
            $invalidRecord = DB::selectOne("
                SELECT count(*) as cnt 
                FROM seller_verifies 
                WHERE seller_id IS NOT NULL 
                  AND trim(seller_id::text) != '' 
                  AND (seller_id::text !~ '^[0-9]+$' OR seller_id::text::numeric > 9223372036854775807 OR seller_id::text::numeric < -9223372036854775808)
            ");

            $invalidCount = (int) ($invalidRecord->cnt ?? 0);
            if ($invalidCount > 0) {
                throw new \RuntimeException(
                    "Safety abort: Found {$invalidCount} invalid non-numeric or out-of-range seller_id values in seller_verifies table."
                );
            }

            // 3. Perform atomic type conversion preserving all values with safe lock timeout
            DB::statement("SET lock_timeout = '5s'");
            DB::statement("
                ALTER TABLE seller_verifies 
                ALTER COLUMN seller_id TYPE bigint 
                USING (NULLIF(trim(seller_id::text), '')::bigint)
            ");

            // 4. Add index on seller_id for optimal Eloquent eager-loading performance
            DB::statement('CREATE INDEX IF NOT EXISTS seller_verifies_seller_id_index ON seller_verifies (seller_id)');

        } elseif ($driver === 'mysql') {
            Schema::table('seller_verifies', function (Blueprint $table) {
                $table->unsignedBigInteger('seller_id')->nullable()->change();
                $table->index('seller_id', 'seller_verifies_seller_id_index');
            });
        } elseif ($driver === 'sqlite') {
            DB::statement('CREATE INDEX IF NOT EXISTS seller_verifies_seller_id_index ON seller_verifies (seller_id)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('seller_verifies') || !Schema::hasColumn('seller_verifies', 'seller_id')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("SET lock_timeout = '5s'");
            DB::statement('DROP INDEX IF EXISTS seller_verifies_seller_id_index');

            DB::statement("
                ALTER TABLE seller_verifies 
                ALTER COLUMN seller_id TYPE varchar(191) 
                USING (seller_id::text)
            ");
        } elseif ($driver === 'mysql') {
            Schema::table('seller_verifies', function (Blueprint $table) {
                $table->dropIndex('seller_verifies_seller_id_index');
                $table->string('seller_id', 191)->nullable()->change();
            });
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS seller_verifies_seller_id_index');
        }
    }
};

