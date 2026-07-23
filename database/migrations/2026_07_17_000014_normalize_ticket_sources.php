<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tickets MODIFY source ENUM('kiosk','public_web','admin_panel') NOT NULL DEFAULT 'public_web'");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_source_check");
            DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_source_check CHECK (source IN ('kiosk','public_web','admin_panel'))");
        }

        DB::table('tickets')->where('source', 'kiosk')->update(['source' => 'public_web']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tickets MODIFY source ENUM('public_web','admin_panel') NOT NULL DEFAULT 'public_web'");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_source_check");
            DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_source_check CHECK (source IN ('public_web','admin_panel'))");
        }
    }

    public function down(): void
    {
        // La normalización de un origen de prueba no debe revertirse.
    }
};
