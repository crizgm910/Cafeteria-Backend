<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $indexes = $this->indexes();

        foreach ($indexes as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->index($column);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse($this->indexes(), true) as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                foreach (array_reverse($columns) as $column) {
                    $table->dropIndex([$column]);
                }
            });
        }
    }

    private function indexes(): array
    {
        return [
            'add_ons' => ['ingredient_id'],
            'audit_events' => ['user_id'],
            'cash_movements' => ['cash_register_session_id', 'user_id'],
            'cash_register_sessions' => ['closed_by', 'opened_by'],
            'inventory_transactions' => ['ingredient_id'],
            'invoices' => ['ticket_id'],
            'payments' => ['confirmed_by', 'ticket_id'],
            'permission_role' => ['role_id'],
            'product_add_ons' => ['add_on_id'],
            'product_recipes' => ['ingredient_id'],
            'products' => ['category_id', 'kitchen_station_id'],
            'role_user' => ['user_id'],
            'ticket_activities' => ['ticket_id', 'user_id'],
            'ticket_item_add_ons' => ['add_on_id', 'ticket_item_id'],
            'ticket_items' => ['kitchen_station_id', 'product_id', 'ticket_id'],
            'wastes' => ['ingredient_id'],
        ];
    }
};
