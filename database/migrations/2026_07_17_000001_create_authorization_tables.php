<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        $now = now();
        $roles = [
            ['name' => 'Propietario', 'slug' => 'owner'],
            ['name' => 'Gerente', 'slug' => 'manager'],
            ['name' => 'Caja', 'slug' => 'cashier'],
            ['name' => 'Preparación', 'slug' => 'preparation'],
            ['name' => 'Inventario', 'slug' => 'inventory'],
        ];

        $permissions = [
            ['name' => 'Ver pedidos', 'slug' => 'tickets.view'],
            ['name' => 'Actualizar preparación de pedidos', 'slug' => 'tickets.update'],
            ['name' => 'Cancelar pedidos', 'slug' => 'tickets.cancel'],
            ['name' => 'Gestionar reservaciones', 'slug' => 'reservations.manage'],
            ['name' => 'Gestionar catálogo', 'slug' => 'catalog.manage'],
            ['name' => 'Ver inventario', 'slug' => 'inventory.view'],
            ['name' => 'Gestionar insumos', 'slug' => 'inventory.manage'],
            ['name' => 'Registrar ajustes de inventario', 'slug' => 'inventory.adjust'],
            ['name' => 'Gestionar usuarios y permisos', 'slug' => 'users.manage'],
            ['name' => 'Operar punto de venta', 'slug' => 'pos.operate'],
            ['name' => 'Gestionar caja', 'slug' => 'cash.manage'],
            ['name' => 'Ver reportes', 'slug' => 'reports.view'],
            ['name' => 'Consultar auditoría', 'slug' => 'audit.view'],
        ];

        DB::table('roles')->insert(array_map(fn (array $role) => $role + [
            'created_at' => $now,
            'updated_at' => $now,
        ], $roles));
        DB::table('permissions')->insert(array_map(fn (array $permission) => $permission + [
            'created_at' => $now,
            'updated_at' => $now,
        ], $permissions));

        $roleIds = DB::table('roles')->pluck('id', 'slug');
        $permissionIds = DB::table('permissions')->pluck('id', 'slug');
        $grants = [
            'owner' => array_keys($permissionIds->all()),
            'manager' => [
                'tickets.view', 'tickets.update', 'tickets.cancel', 'reservations.manage',
                'catalog.manage', 'inventory.view', 'inventory.manage', 'inventory.adjust',
                'pos.operate', 'cash.manage', 'reports.view', 'audit.view',
            ],
            'cashier' => ['tickets.view', 'tickets.cancel', 'pos.operate', 'cash.manage'],
            'preparation' => ['tickets.view', 'tickets.update'],
            'inventory' => ['catalog.manage', 'inventory.view', 'inventory.manage', 'inventory.adjust'],
        ];

        $permissionRoleRows = [];
        foreach ($grants as $roleSlug => $permissionSlugs) {
            foreach ($permissionSlugs as $permissionSlug) {
                $permissionRoleRows[] = [
                    'role_id' => $roleIds[$roleSlug],
                    'permission_id' => $permissionIds[$permissionSlug],
                ];
            }
        }
        DB::table('permission_role')->insert($permissionRoleRows);

        $ownerRoleId = $roleIds['owner'];
        $roleUserRows = DB::table('users')->pluck('id')->map(fn ($userId) => [
            'role_id' => $ownerRoleId,
            'user_id' => $userId,
        ])->all();

        if ($roleUserRows !== []) {
            DB::table('role_user')->insert($roleUserRows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
