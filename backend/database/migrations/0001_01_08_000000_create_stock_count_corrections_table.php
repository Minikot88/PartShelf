<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_count_items', function (Blueprint $table) {
            $table->unsignedBigInteger('reference_movement_id')->nullable();
            $table->foreign('reference_movement_id')->references('id')->on('stock_movements')->restrictOnDelete();
        });
        Schema::table('stock_count_attempts', function (Blueprint $table) {
            $table->unsignedBigInteger('movement_watermark_id')->nullable();
            $table->foreign('movement_watermark_id')->references('id')->on('stock_movements')->restrictOnDelete();
        });
        Schema::create('stock_count_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_attempt_id')->unique()->constrained('stock_count_attempts')->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->char('request_fingerprint', 64);
            $table->decimal('quantity_delta', 14, 3);
            $table->decimal('balance_before', 14, 3);
            $table->decimal('balance_after', 14, 3);
            $table->text('reason');
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_corrections');
        Schema::table('stock_count_attempts', function (Blueprint $table) {
            $table->dropForeign(['movement_watermark_id']);
            $table->dropColumn('movement_watermark_id');
        });
        Schema::table('stock_count_items', function (Blueprint $table) {
            $table->dropForeign(['reference_movement_id']);
            $table->dropColumn('reference_movement_id');
        });
    }
};
