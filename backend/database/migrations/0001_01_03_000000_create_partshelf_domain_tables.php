<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['parent_id', 'name']);
        });

        Schema::create('storage_locations', function (Blueprint $table) {
            $table->id();
            $table->string('zone', 64);
            $table->string('shelf', 64);
            $table->string('level', 64);
            $table->string('bin', 64);
            $table->timestamps();
            $table->unique(['zone', 'shelf', 'level', 'bin'], 'storage_locations_path_unique');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100)->unique();
            $table->string('name', 255);
            $table->string('part_number', 120)->nullable();
            $table->string('base_unit', 40);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('storage_location_id')->nullable()->constrained('storage_locations')->restrictOnDelete();
            $table->decimal('min_stock_quantity', 14, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestampTz('last_movement_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('movement_type', 24);
            $table->decimal('quantity_delta', 14, 3);
            $table->text('reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('occurred_at');
            $table->timestamps();
            $table->index(['product_id', 'occurred_at']);
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('status', 16)->default('open');
            $table->foreignId('started_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('storage_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->decimal('reference_quantity', 14, 3);
            $table->timestampTz('reference_at');
            $table->timestamps();
            $table->unique(['stock_count_id', 'product_id']);
        });

        Schema::create('stock_count_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_item_id')->constrained('stock_count_items')->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->decimal('counted_quantity', 14, 3);
            $table->foreignId('counted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('counted_at');
            $table->timestamps();
            $table->unique(['stock_count_item_id', 'attempt_number']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->text('reason')->nullable();
            $table->timestampTz('created_at');
            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_min_stock_non_negative CHECK (min_stock_quantity >= 0)');
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_valid CHECK (movement_type IN ('opening', 'receipt', 'reversal', 'correction'))");
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_delta_non_zero CHECK (quantity_delta <> 0)');
            DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT inventory_balances_quantity_non_negative CHECK (quantity >= 0)');
            DB::statement("ALTER TABLE stock_counts ADD CONSTRAINT stock_counts_status_valid CHECK (status IN ('open', 'completed'))");
            DB::statement('ALTER TABLE stock_count_items ADD CONSTRAINT stock_count_items_reference_non_negative CHECK (reference_quantity >= 0)');
            DB::statement('ALTER TABLE stock_count_attempts ADD CONSTRAINT stock_count_attempts_number_positive CHECK (attempt_number > 0)');
            DB::statement('ALTER TABLE stock_count_attempts ADD CONSTRAINT stock_count_attempts_quantity_non_negative CHECK (counted_quantity >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('stock_count_attempts');
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('products');
        Schema::dropIfExists('storage_locations');
        Schema::dropIfExists('categories');
    }
};
