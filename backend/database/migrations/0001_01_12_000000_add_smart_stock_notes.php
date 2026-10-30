<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('tracking_mode', 24)->default('QUANTITY');
            $table->string('availability_status', 24)->default('UNKNOWN');
            $table->text('notes')->nullable();
            $table->boolean('sku_is_generated')->default(false);
            $table->timestampTz('archived_at')->nullable()->index();
            $table->boolean('reorder_needed')->default(false);
            $table->text('reorder_note')->nullable();
            $table->timestampTz('status_updated_at')->nullable();
            $table->foreignId('status_updated_by')->nullable()->constrained('users');
            $table->unsignedBigInteger('stock_version')->default(0);
        });
        Schema::create('product_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action', 32);
            $table->uuid('idempotency_key')->unique();
            $table->char('request_fingerprint', 64);
            $table->jsonb('before_data')->nullable();
            $table->jsonb('after_data');
            $table->jsonb('response_data');
            $table->text('reason')->nullable();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements');
            $table->timestampTz('created_at');
            $table->index(['product_id', 'id']);
        });
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_tracking_mode_valid CHECK (tracking_mode IN ('QUANTITY','AVAILABILITY_ONLY'))");
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_availability_valid CHECK (availability_status IN ('IN_STOCK','OUT_OF_STOCK','UNKNOWN'))");
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_valid');
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_valid CHECK (movement_type IN ('opening','receipt','reversal','correction','sale'))");
    }

    public function down(): void
    {
        // A rollback must never silently discard the new ledger or user metadata.
        if (DB::table('product_events')->exists() || DB::table('stock_movements')->where('movement_type', 'sale')->exists()
            || DB::table('products')->where('tracking_mode', '<>', 'QUANTITY')->orWhere('availability_status', '<>', 'UNKNOWN')->orWhereNotNull('notes')->orWhereNotNull('archived_at')->orWhere('reorder_needed', true)->orWhereNotNull('reorder_note')->orWhere('sku_is_generated', true)->orWhereNotNull('status_updated_at')->orWhereNotNull('status_updated_by')->orWhere('stock_version', '>', 0)->exists()) {
            throw new RuntimeException('Smart Stock data exists. Restore a verified backup into a separate database instead of discarding history.');
        }
        Schema::drop('product_events');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_valid');
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_valid CHECK (movement_type IN ('opening','receipt','reversal','correction'))");
        DB::statement('ALTER TABLE products DROP CONSTRAINT products_tracking_mode_valid');
        DB::statement('ALTER TABLE products DROP CONSTRAINT products_availability_valid');
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_updated_by');
            $table->dropColumn(['tracking_mode', 'availability_status', 'notes', 'sku_is_generated', 'archived_at', 'reorder_needed', 'reorder_note', 'status_updated_at', 'stock_version']);
        });
    }
};
