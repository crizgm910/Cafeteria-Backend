<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            ['name' => 'Gestionar áreas de reservación', 'slug' => 'reservation_areas.manage'],
            ['name' => 'Gestionar mesas de reservación', 'slug' => 'reservation_tables.manage'],
            ['name' => 'Gestionar horarios y bloqueos de reservación', 'slug' => 'reservation_blocks.manage'],
        ];
        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(['slug' => $permission['slug']], $permission + ['created_at' => $now, 'updated_at' => $now]);
        }
        $permissionIds = DB::table('permissions')->whereIn('slug', array_column($permissions, 'slug'))->pluck('id');
        $roleIds = DB::table('roles')->whereIn('slug', ['owner', 'manager'])->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('slug', [
            'reservation_areas.manage', 'reservation_tables.manage', 'reservation_blocks.manage',
        ])->delete();
    }
};

