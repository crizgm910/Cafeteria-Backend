<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketStatusApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->withRole()->create(), 'sanctum');
    }

    public function test_cancelling_restores_inventory_and_refunds_payment(): void
    {
        $ingredient = Ingredient::create([
            'sku' => 'CANCEL-ING', 'name' => 'Café', 'unit_of_measure' => 'g',
            'current_stock' => 80, 'minimum_stock' => 10, 'cost_per_unit' => 1,
        ]);
        $ticket = Ticket::create(['ticket_number' => 'TGR-CANCEL', 'status' => 'pending', 'total' => 100]);
        InventoryTransaction::create([
            'ingredient_id' => $ingredient->id, 'transaction_type' => 'sale',
            'quantity' => -20, 'reference_id' => $ticket->id, 'stock_after_transaction' => 80,
        ]);
        $ticket->payments()->create([
            'amount' => 100, 'gateway_provider' => 'cash', 'status' => 'approved',
        ]);

        $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'cancelled', 'cancellation_reason' => 'Error de captura'])
            ->assertOk()
            ->assertJsonPath('ticket.status', 'cancelled');

        $this->assertDatabaseHas('ingredients', ['id' => $ingredient->id, 'current_stock' => 100]);
        $this->assertDatabaseHas('inventory_transactions', [
            'ingredient_id' => $ingredient->id, 'transaction_type' => 'adjustment',
            'quantity' => 20, 'reference_id' => $ticket->id, 'stock_after_transaction' => 100,
        ]);
        $this->assertDatabaseHas('payments', ['ticket_id' => $ticket->id, 'status' => 'refunded']);
    }

    public function test_terminal_states_cannot_transition_or_restore_twice(): void
    {
        $ticket = Ticket::create(['ticket_number' => 'TGR-DONE', 'status' => 'cancelled', 'total' => 0]);

        $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'pending'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Transición inválida de cancelled a pending.');
    }

    public function test_cancelling_an_unpaid_order_cancels_its_pending_payment(): void
    {
        $ticket = Ticket::create(['ticket_number' => 'TGR-UNPAID', 'status' => 'pending', 'total' => 90]);
        $ticket->payments()->create([
            'amount' => 90, 'gateway_provider' => 'pay_at_pickup', 'status' => 'pending',
        ]);

        $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'cancelled', 'cancellation_reason' => 'Cliente desistió'])
            ->assertOk();

        $this->assertDatabaseHas('payments', [
            'ticket_id' => $ticket->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_status_cannot_skip_from_pending_to_delivered(): void
    {
        $ticket = Ticket::create(['ticket_number' => 'TGR-SKIP', 'status' => 'pending', 'total' => 0]);

        $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'delivered'])
            ->assertStatus(422);
    }
}
