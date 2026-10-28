<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX storage_locations_case_insensitive_path_unique ON storage_locations (LOWER(zone), LOWER(shelf), LOWER(level), LOWER(bin))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS storage_locations_case_insensitive_path_unique');
        }
    }
};
