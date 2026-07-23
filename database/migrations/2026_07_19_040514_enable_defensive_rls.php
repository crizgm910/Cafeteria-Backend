<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLES = [
        'add_ons',
        'audit_events',
        'cache',
        'cache_locks',
        'cash_movements',
        'cash_register_sessions',
        'categories',
        'failed_jobs',
        'ingredients',
        'inventory_transactions',
        'invoices',
        'job_batches',
        'jobs',
        'kitchen_stations',
        'migrations',
        'password_reset_tokens',
        'payments',
        'permission_role',
        'permissions',
        'personal_access_tokens',
        'product_add_ons',
        'product_recipes',
        'products',
        'reservations',
        'role_user',
        'roles',
        'sessions',
        'ticket_activities',
        'ticket_item_add_ons',
        'ticket_items',
        'tickets',
        'users',
        'wastes',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->setRowLevelSecurity('ENABLE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->setRowLevelSecurity('DISABLE');
    }

    private function setRowLevelSecurity(string $action): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TABLES as $table) {
            DB::statement(sprintf('ALTER TABLE "%s" %s ROW LEVEL SECURITY', $table, $action));
        }
    }
};
