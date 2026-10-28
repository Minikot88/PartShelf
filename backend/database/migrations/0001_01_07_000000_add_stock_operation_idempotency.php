<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable()->unique('stock_movements_idempotency_key_unique');
            $table->char('request_fingerprint', 64)->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_delta_non_zero');
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_delta_valid CHECK ((movement_type = 'opening' AND quantity_delta >= 0) OR (movement_type <> 'opening' AND quantity_delta <> 0))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_delta_valid');
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_delta_non_zero CHECK (quantity_delta <> 0)');
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropUnique('stock_movements_idempotency_key_unique');
            $table->dropColumn(['idempotency_key', 'request_fingerprint']);
        });
    }
};
