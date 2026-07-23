<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KdsWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_kds_transitions_record_item_timestamps_and_actor(): void
    {
        $barista = User::factory()->withRole('preparation')->create();
        Sanctum::actingAs($barista);
        $category = Category::create(['name' => 'Café', 'slug' => 'cafe', 'active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'sku' => 'KDS-1', 'name' => 'Latte', 'price' => 50, 'active' => true,
        ]);
        $ticket = Ticket::create(['ticket_number' => 'TGR-KDS', 'status' => 'pending', 'total' => 50]);
        $item = TicketItem::create([
            'ticket_id' => $ticket->id, 'product_id' => $product->id,
            'quantity' => 1, 'unit_price' => 50, 'subtotal' => 50, 'kds_status' => 'pending',
        ]);

        $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'preparing'])->assertOk();
        $item->refresh();
        $this->assertSame('preparing', $item->kds_status);
        $this->assertNotNull($item->kds_started_at);

        $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'ready'])->assertOk();
        $item->refresh();
        $this->assertSame('ready', $item->kds_status);
        $this->assertNotNull($item->kds_completed_at);
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'user_id' => $barista->id,
            'action' => 'Estado actualizado a READY',
        ]);
    }
}
