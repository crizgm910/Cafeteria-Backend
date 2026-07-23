<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosSaleApiTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cashier = User::factory()->withRole('cashier')->create();
        Sanctum::actingAs($this->cashier);
    }

    public function test_cash_sale_records_change_inventory_and_cash_movement(): void
    {
        $product = $this->product();
        $this->postJson('/api/cash-register/open', ['opening_amount' => 500])->assertCreated();

        $response = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/pos/sales', $this->payload($product, [
                'payment_method' => 'cash',
                'amount_received' => 100,
            ]))
            ->assertCreated()
            ->assertJsonPath('total', 55)
            ->assertJsonPath('payment.status', 'approved')
            ->assertJsonPath('payment.change_amount', '45.00');

        $this->assertDatabaseHas('cash_movements', [
            'ticket_id' => $response->json('ticket_id'),
            'type' => 'sale',
            'amount' => 55,
        ]);
        $this->assertDatabaseHas('ingredients', ['current_stock' => 985]);

        $this->getJson('/api/cash-register/current')
            ->assertJsonPath('data.calculated_expected_cash', 555);
    }

    public function test_terminal_sale_requires_reference_and_does_not_increase_cash(): void
    {
        $product = $this->product();
        $this->postJson('/api/cash-register/open', ['opening_amount' => 500])->assertCreated();

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/pos/sales', $this->payload($product, [
                'payment_method' => 'card_terminal',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('transaction_reference');

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/pos/sales', $this->payload($product, [
                'payment_method' => 'card_terminal',
                'transaction_reference' => 'TERM-12345',
            ]))
            ->assertCreated()
            ->assertJsonPath('payment.transaction_reference', 'TERM-12345');

        $this->assertDatabaseCount('cash_movements', 0);
        $this->getJson('/api/cash-register/current')
            ->assertJsonPath('data.calculated_expected_cash', 500);
    }

    public function test_sale_requires_open_session_and_is_idempotent(): void
    {
        $product = $this->product();
        $key = (string) Str::uuid();
        $payload = $this->payload($product, [
            'payment_method' => 'cash',
            'amount_received' => 60,
        ]);

        $this->withHeader('Idempotency-Key', $key)->postJson('/api/pos/sales', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'POS_SALE_REJECTED');

        $this->postJson('/api/cash-register/open', ['opening_amount' => 0])->assertCreated();
        $ticketId = $this->withHeader('Idempotency-Key', $key)->postJson('/api/pos/sales', $payload)
            ->assertCreated()
            ->json('ticket_id');

        $this->withHeader('Idempotency-Key', $key)->postJson('/api/pos/sales', $payload)
            ->assertOk()
            ->assertJsonPath('ticket_id', $ticketId)
            ->assertJsonPath('idempotent_replay', true);

        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('cash_movements', 1);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_cancelling_cash_sale_records_refund_in_open_cash_session(): void
    {
        $product = $this->product();
        $this->postJson('/api/cash-register/open', ['opening_amount' => 500])->assertCreated();
        $ticketId = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/pos/sales', $this->payload($product, [
                'payment_method' => 'cash',
                'amount_received' => 60,
            ]))->assertCreated()->json('ticket_id');

        $this->patchJson("/api/tickets/{$ticketId}/status", ['status' => 'cancelled', 'cancellation_reason' => 'Venta equivocada'])
            ->assertOk();

        $this->assertDatabaseHas('cash_movements', [
            'ticket_id' => $ticketId,
            'type' => 'refund',
            'amount' => -55,
        ]);
        $this->getJson('/api/cash-register/current')
            ->assertJsonPath('data.calculated_expected_cash', 500);
    }

    public function test_terminal_cancellation_requires_external_refund_reference(): void
    {
        $product = $this->product();
        $this->postJson('/api/cash-register/open', ['opening_amount' => 500])->assertCreated();
        $ticketId = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/pos/sales', $this->payload($product, [
                'payment_method' => 'card_terminal',
                'transaction_reference' => 'SALE-TERM-1',
            ]))->assertCreated()->json('ticket_id');

        $this->patchJson("/api/tickets/{$ticketId}/status", ['status' => 'cancelled', 'cancellation_reason' => 'Cliente desistió'])
            ->assertUnprocessable();

        $this->patchJson("/api/tickets/{$ticketId}/status", [
            'status' => 'cancelled',
            'cancellation_reason' => 'Cliente desistió',
            'refund_reference' => 'REFUND-TERM-1',
        ])->assertOk();

        $this->assertDatabaseHas('payments', [
            'ticket_id' => $ticketId,
            'status' => 'refunded',
            'refund_reference' => 'REFUND-TERM-1',
        ]);
    }

    private function product(): Product
    {
        $category = Category::create([
            'name' => 'Bebidas',
            'slug' => 'bebidas-'.uniqid(),
            'active' => true,
        ]);
        $ingredient = Ingredient::create([
            'sku' => 'COFFEE-'.uniqid(),
            'name' => 'Café',
            'unit_of_measure' => 'g',
            'current_stock' => 1000,
            'minimum_stock' => 100,
            'cost_per_unit' => 1,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'sku' => 'LATTE-'.uniqid(),
            'name' => 'Latte',
            'price' => 55,
            'active' => true,
        ]);
        $product->ingredients()->attach($ingredient->id, ['quantity_required' => 15]);

        return $product;
    }

    private function payload(Product $product, array $overrides): array
    {
        return array_merge([
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'add_ons' => [],
            ]],
            'customer_name' => 'Venta mostrador',
            'order_type' => 'dine_in',
        ], $overrides);
    }
}
