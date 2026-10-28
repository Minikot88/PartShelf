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
            $table->string('image_path')->nullable();
        });

        Schema::create('product_fitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('make', 100);
            $table->string('model', 120);
            $table->unsignedSmallInteger('year');
            $table->string('engine', 100)->default('');
            $table->timestamps();
            $table->unique(['product_id', 'make', 'model', 'year', 'engine'], 'product_fitments_vehicle_unique');
            $table->index(['make', 'model', 'year']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX products_sku_case_insensitive_unique ON products (LOWER(sku))');
            DB::statement('CREATE UNIQUE INDEX categories_parent_name_case_insensitive_unique ON categories (COALESCE(parent_id, 0), LOWER(name))');
            DB::statement('ALTER TABLE product_fitments ADD CONSTRAINT product_fitments_year_valid CHECK (year BETWEEN 1886 AND 2200)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS products_sku_case_insensitive_unique');
            DB::statement('DROP INDEX IF EXISTS categories_parent_name_case_insensitive_unique');
        }

        Schema::dropIfExists('product_fitments');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
