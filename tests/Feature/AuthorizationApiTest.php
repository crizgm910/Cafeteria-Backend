<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_cannot_manage_catalog_or_inventory(): void
    {
        Sanctum::actingAs(User::factory()->withRole('cashier')->create());

        $this->getJson('/api/categories')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->getJson('/api/ingredients')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_manager_can_manage_catalog(): void
    {
        Sanctum::actingAs(User::factory()->withRole('manager')->create());

        $this->postJson('/api/categories', ['name' => 'Temporada'])
            ->assertCreated()
            ->assertJsonPath('slug', 'temporada');

        $this->assertDatabaseHas('categories', ['slug' => 'temporada']);
    }

    public function test_preparation_can_advance_but_cannot_cancel_a_ticket(): void
    {
        Sanctum::actingAs(User::factory()->withRole('preparation')->create());
        $ticket = Ticket::create([
            'ticket_number' => 'TGR-RBAC-1',
            'status' => 'pending',
            'total' => 0,
        ]);

        $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'preparing'])
            ->assertOk();

        $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'cancelled'])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_cashier_can_cancel_but_cannot_advance_preparation(): void
    {
        Sanctum::actingAs(User::factory()->withRole('cashier')->create());
        $ticket = Ticket::create([
            'ticket_number' => 'TGR-RBAC-2',
            'status' => 'pending',
            'total' => 0,
        ]);

        $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'preparing'])
            ->assertForbidden();

        $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'cancelled', 'cancellation_reason' => 'Cliente desistió'])
            ->assertOk();
    }
}
