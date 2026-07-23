<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        match (DB::getDriverName()) {
            'mysql' => DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','approved','declined','refunded','cancelled') NOT NULL DEFAULT 'pending'"),
            'pgsql' => DB::statement(<<<'SQL'
                DO $$
                BEGIN
                    ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check;
                    ALTER TABLE payments ADD CONSTRAINT payments_status_check
                        CHECK (status IN ('pending','approved','declined','refunded','cancelled'));
                END $$
                SQL),
            default => null,
        };
    }

    public function down(): void
    {
        DB::table('payments')->where('status', 'cancelled')->update(['status' => 'declined']);
        match (DB::getDriverName()) {
            'mysql' => DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','approved','declined','refunded') NOT NULL DEFAULT 'pending'"),
            'pgsql' => DB::statement(<<<'SQL'
                DO $$
                BEGIN
                    ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check;
                    ALTER TABLE payments ADD CONSTRAINT payments_status_check
                        CHECK (status IN ('pending','approved','declined','refunded'));
                END $$
                SQL),
            default => null,
        };
    }
};
