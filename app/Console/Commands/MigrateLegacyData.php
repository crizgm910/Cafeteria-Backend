<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class MigrateLegacyData extends Command
{
    protected $signature = 'tgr:migrate-legacy-data {--dry-run : Solo compara disponibilidad y conteos}';
    protected $description = 'Copia datos de negocio del MySQL heredado al PostgreSQL oficial vacío';

    private const TABLES = [
        'users', 'kitchen_stations', 'categories', 'ingredients', 'products', 'tickets',
        'product_recipes', 'ticket_items', 'add_ons', 'product_add_ons',
        'ticket_item_add_ons', 'payments', 'invoices', 'inventory_transactions',
        'wastes', 'reservations', 'ticket_activities',
    ];

    private const SERIAL_TABLES = [
        'users', 'kitchen_stations', 'categories', 'ingredients', 'products', 'add_ons',
        'reservations', 'ticket_activities',
    ];

    public function handle(): int
    {
        $source = DB::connection('legacy_mysql');
        $target = DB::connection();
        if ($source->getDriverName() !== 'mysql' || $target->getDriverName() !== 'pgsql') {
            $this->error('Se requiere legacy_mysql como origen y pgsql como destino predeterminado.');
            return self::FAILURE;
        }

        $counts = [];
        foreach (self::TABLES as $table) {
            if (! Schema::connection('legacy_mysql')->hasTable($table) || ! Schema::hasTable($table)) {
                throw new RuntimeException("Falta la tabla requerida {$table} en origen o destino.");
            }
            $counts[$table] = $source->table($table)->count();
        }
        $this->table(['Tabla', 'Origen'], collect($counts)->map(fn ($count, $table) => [$table, $count])->values());
        if ($this->option('dry-run')) return self::SUCCESS;

        foreach (self::TABLES as $table) {
            if ($target->table($table)->exists()) {
                throw new RuntimeException("El destino no está vacío: {$table} ya contiene registros.");
            }
        }

        $target->transaction(function () use ($source, $target): void {
            foreach (self::TABLES as $table) {
                $this->copyTable($source, $target, $table);
            }
            $this->assignLegacyUsersToOwnerRole($target);
            $this->resetPostgresSequences($target);
        }, 3);

        $this->info('Migración de datos completada. Ejecuta tgr:verify-legacy-migration antes del corte.');
        return self::SUCCESS;
    }

    private function copyTable(Connection $source, Connection $target, string $table): void
    {
        $sourceColumns = Schema::connection('legacy_mysql')->getColumnListing($table);
        $targetColumns = Schema::getColumnListing($table);
        $columns = array_values(array_intersect($sourceColumns, $targetColumns));
        $orderColumn = in_array('id', $sourceColumns, true) ? 'id' : $sourceColumns[0];

        $source->table($table)->orderBy($orderColumn)->chunk(500, function ($rows) use ($target, $table, $columns): void {
            $payload = $rows->map(function ($row) use ($table, $columns) {
                $data = Arr::only((array) $row, $columns);
                if ($table === 'tickets' && ($data['source'] ?? null) === 'kiosk') $data['source'] = 'public_web';
                if ($table === 'users' && isset($data['email'])) $data['email'] = mb_strtolower(trim($data['email']));
                return $data;
            })->all();
            if ($payload !== []) $target->table($table)->insert($payload);
        });

        $this->line("Copiada {$table}");
    }

    private function assignLegacyUsersToOwnerRole(Connection $target): void
    {
        $ownerRoleId = $target->table('roles')->where('slug', 'owner')->value('id');
        if (! $ownerRoleId) throw new RuntimeException('No existe el rol owner en el destino.');
        $rows = $target->table('users')->pluck('id')->map(fn ($userId) => [
            'role_id' => $ownerRoleId,
            'user_id' => $userId,
        ])->all();
        if ($rows !== []) $target->table('role_user')->insertOrIgnore($rows);
    }

    private function resetPostgresSequences(Connection $target): void
    {
        foreach (self::SERIAL_TABLES as $table) {
            $target->statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), GREATEST(COALESCE(MAX(id), 1), 1), MAX(id) IS NOT NULL) FROM {$table}");
        }
    }
}
