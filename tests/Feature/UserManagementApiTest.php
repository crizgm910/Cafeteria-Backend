<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_list_and_update_staff(): void
    {
        $owner = User::factory()->withRole('owner')->create();
        Sanctum::actingAs($owner);

        $created = $this->postJson('/api/users', [
            'name' => 'Caja Principal',
            'email' => 'CAJA@EXAMPLE.COM',
            'password' => 'password-seguro',
            'password_confirmation' => 'password-seguro',
            'role_slugs' => ['cashier'],
        ])->assertCreated()
            ->assertJsonPath('email', 'caja@example.com')
            ->assertJsonPath('roles.0.slug', 'cashier');

        $id = $created->json('id');
        $this->getJson('/api/users?per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->patchJson("/api/users/{$id}", [
            'name' => 'Caja Turno A',
            'role_slugs' => ['cashier', 'preparation'],
            'active' => false,
        ])->assertOk()
            ->assertJsonPath('name', 'Caja Turno A')
            ->assertJsonPath('active', false);

        $this->assertDatabaseHas('users', ['id' => $id, 'active' => false]);
    }

    public function test_manager_cannot_manage_users(): void
    {
        Sanctum::actingAs(User::factory()->withRole('manager')->create());

        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_last_active_owner_cannot_remove_owner_role_or_disable_self(): void
    {
        $owner = User::factory()->withRole('owner')->create();
        Sanctum::actingAs($owner);

        $this->patchJson("/api/users/{$owner->id}", ['role_slugs' => ['manager']])
            ->assertUnprocessable();
        $this->patchJson("/api/users/{$owner->id}", ['active' => false])
            ->assertUnprocessable();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->withRole('cashier')->create([
            'email' => 'inactive@example.com',
            'password' => 'password-seguro',
            'active' => false,
        ]);

        $this->postJson('/api/login', [
            'email' => 'inactive@example.com',
            'password' => 'password-seguro',
        ])->assertUnauthorized();
    }
}
