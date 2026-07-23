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

class PaymentCollectionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_order_must_be_collected_before_preparation_and_collection_is_idempotent(): void
    {
        $product = $this->product();
        $checkout = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/checkout', [
                'items' => [['product_id' => $product->id, 'quantity' => 1, 'add_ons' => []]],
                'payment_method' => 'pay_at_pickup',
                'customer_name' => 'Cliente', 'customer_phone' => '5512345678',
                'order_type' => 'takeout',
            ])->assertCreated();
        $ticketId = $checkout->json('ticket_id');

        $barista = User::factory()->withRole('preparation')->create();
        Sanctum::actingAs($barista);
        $this->patchJson("/api/tickets/{$ticketId}/status", ['status' => 'preparing'])
            ->assertUnprocessable();

        $cashier = User::factory()->withRole('cashier')->create();
        Sanctum::actingAs($cashier);
        $this->postJson('/api/cash-register/open', ['opening_amount' => 100])->assertCreated();
        $key = (string) Str::uuid();
        $payload = ['payment_method' => 'cash', 'amount_received' => 60];

        $this->withHeader('Idempotency-Key', $key)
            ->postJson("/api/tickets/{$ticketId}/collect-payment", $payload)
            ->assertOk()
            ->assertJsonPath('payment.status', 'approved')
            ->assertJsonPath('payment.evidence_type', 'cashier_confirmation')
            ->assertJsonPath('payment.change_amount', '10.00');
        $this->withHeader('Idempotency-Key', $key)
            ->postJson("/api/tickets/{$ticketId}/collect-payment", $payload)
            ->assertOk();

        $this->assertDatabaseHas('tickets', ['id' => $ticketId, 'status' => 'paid']);
        $this->assertDatabaseCount('cash_movements', 1);

        Sanctum::actingAs($barista);
        $this->patchJson("/api/tickets/{$ticketId}/status", ['status' => 'preparing'])->assertOk();
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Bebidas', 'slug' => 'bebidas', 'active' => true]);
        $ingredient = Ingredient::create([
            'sku' => 'PAY-CF', 'name' => 'Café', 'unit_of_measure' => 'g',
            'current_stock' => 100, 'minimum_stock' => 5, 'cost_per_unit' => 1,
        ]);
        $product = Product::create([
            'category_id' => $category->id, 'sku' => 'PAY-LATTE',
            'name' => 'Latte', 'price' => 50, 'active' => true,
        ]);
        $product->ingredients()->attach($ingredient->id, ['quantity_required' => 10]);

        return $product;
    }
}
