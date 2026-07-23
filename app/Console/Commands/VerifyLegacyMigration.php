<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyLegacyMigration extends Command
{
    protected $signature = 'tgr:verify-legacy-migration';
    protected $description = 'Compara conteos y saldos críticos entre MySQL heredado y PostgreSQL';

    private const TABLES = [
        'users', 'kitchen_stations', 'categories', 'ingredients', 'products', 'tickets',
        'product_recipes', 'ticket_items', 'add_ons', 'product_add_ons',
        'ticket_item_add_ons', 'payments', 'invoices', 'inventory_transactions',
        'wastes', 'reservations', 'ticket_activities',
    ];

    public function handle(): int
    {
        $source = DB::connection('legacy_mysql');
        $target = DB::connection();
        $failed = false;
        $rows = [];
        foreach (self::TABLES as $table) {
            $sourceCount = $source->table($table)->count();
            $targetCount = $target->table($table)->count();
            $matches = $sourceCount === $targetCount;
            $failed = $failed || ! $matches;
            $rows[] = [$table, $sourceCount, $targetCount, $matches ? 'OK' : 'DIFERENCIA'];
        }
        $this->table(['Tabla', 'MySQL', 'PostgreSQL', 'Resultado'], $rows);

        $checks = [
            'total_stock' => ['ingredients', 'current_stock'],
            'ticket_total' => ['tickets', 'total'],
            'payment_total' => ['payments', 'amount'],
        ];
        foreach ($checks as $name => [$table, $column]) {
            $sourceValue = round((float) $source->table($table)->sum($column), 4);
            $targetValue = round((float) $target->table($table)->sum($column), 4);
            $matches = $sourceValue === $targetValue;
            $failed = $failed || ! $matches;
            $this->line("{$name}: MySQL={$sourceValue}; PostgreSQL={$targetValue}; ".($matches ? 'OK' : 'DIFERENCIA'));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
