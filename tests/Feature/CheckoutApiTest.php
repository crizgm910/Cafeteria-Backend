<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\AddOn;
use App\Models\KitchenStation;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class CheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create an admin user and authenticate
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $this->withHeader('Idempotency-Key', (string) Str::uuid());
    }

    private function createProductWithDependencies($active = true)
    {
        $category = Category::create(['name' => 'Test Cat', 'slug' => 'test-cat-'.uniqid()]);
        $station = KitchenStation::create(['name' => 'Bar']);
        
        $ingredient = Ingredient::create([
            'sku' => 'ING-TEST-'.uniqid(), 
            'name' => 'Milk', 
            'unit_of_measure' => 'ml', 
            'current_stock' => 1000, 
            'minimum_stock' => 100, 
            'cost_per_unit' => 1
        ]);

        $product = Product::create([
            'sku' => 'PROD-'.uniqid(),
            'name' => 'Latte',
            'description' => '...',
            'price' => 50,
            'category_id' => $category->id,
            'kitchen_station_id' => $station->id,
            'active' => $active
        ]);

        // Attach ingredient to product recipe
        $product->ingredients()->attach($ingredient->id, ['quantity_required' => 200]);

        return $product;
    }

    private function createAddOnWithDependencies()
    {
        $ingredient = Ingredient::create([
            'sku' => 'ING-ADD-'.uniqid(), 
            'name' => 'Syrup', 
            'unit_of_measure' => 'ml', 
            'current_stock' => 500, 
            'minimum_stock' => 50, 
            'cost_per_unit' => 2
        ]);

        return AddOn::create([
            'name' => 'Vanilla Syrup',
            'price_adjustment' => 10,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => 20
        ]);
    }

    public function test_rejects_invalid_payment_method()
    {
        $product = $this->createProductWithDependencies();

        $response = $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ],
            'payment_method' => 'bitcoin',
            'customer_name' => 'Cliente Prueba',
            'customer_phone' => '5551234567',
            'order_type' => 'dine_in'
        ]);

        $response->assertStatus(422); // Validation error
        $response->assertJsonValidationErrors(['payment_method']);
    }

    public function test_rejects_inactive_product()
    {
        $product = $this->createProductWithDependencies(false);

        $response = $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ],
            'payment_method' => 'pay_at_pickup',
            'customer_name' => 'Cliente Prueba',
            'customer_phone' => '5551234567',
            'order_type' => 'dine_in'
        ]);

        $response->assertStatus(422); // Reverted inside transaction due to Exception
        $response->assertJson(['error' => 'El producto solicitado no existe o está inactivo.']);
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('ticket_items', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_rejects_duplicate_add_ons()
    {
        $product = $this->createProductWithDependencies();
        $addOn = $this->createAddOnWithDependencies();
        $product->addOns()->attach($addOn->id);

        $response = $this->postJson('/api/checkout', [
            'items' => [
                [
                    'product_id' => $product->id, 
                    'quantity' => 1,
                    'add_ons' => [$addOn->id, $addOn->id]
                ]
            ],
            'payment_method' => 'pay_at_pickup',
            'customer_name' => 'Cliente Prueba',
            'customer_phone' => '5551234567',
            'order_type' => 'dine_in'
        ]);

        $response->assertStatus(422); // distinct validation error
        $response->assertJsonValidationErrors(['items.0.add_ons.0', 'items.0.add_ons.1']);
    }

    public function test_rejects_unassociated_add_on()
    {
        $product = $this->createProductWithDependencies();
        $addOn = $this->createAddOnWithDependencies();
        // We do NOT attach $addOn to $product!

        $response = $this->postJson('/api/checkout', [
            'items' => [
                [
                    'product_id' => $product->id, 
                    'quantity' => 1,
                    'add_ons' => [$addOn->id]
                ]
            ],
            'payment_method' => 'pay_at_pickup',
            'customer_name' => 'Cliente Prueba',
            'customer_phone' => '5551234567',
            'order_type' => 'dine_in'
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'El complemento solicitado no pertenece a este producto.']);
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('ticket_items', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertDatabaseHas('ingredients', [
            'id' => $product->ingredients()->first()->id,
            'current_stock' => 1000,
        ]);
    }

    public function test_successful_checkout_with_associated_add_on()
    {
        $product = $this->createProductWithDependencies();
        $addOn = $this->createAddOnWithDependencies();
        $product->addOns()->attach($addOn->id);

        $response = $this->postJson('/api/checkout', [
            'items' => [
                [
                    'product_id' => $product->id, 
                    'quantity' => 2,
                    'add_ons' => [$addOn->id]
                ]
            ],
            'payment_method' => 'pay_at_pickup',
            'customer_name' => 'Cliente Prueba',
            'customer_phone' => '5551234567',
            'customer_email' => 'cliente@example.com',
            'order_type' => 'takeout'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseHas('ticket_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'subtotal' => 120 // (50 * 2) + (10 * 2) = 120
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'transaction_type' => 'sale',
            'quantity' => -400 // 200 * 2 for product ingredient
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'transaction_type' => 'sale',
            'quantity' => -40 // 20 * 2 for addOn ingredient
        ]);
        $this->assertDatabaseHas('payments', [
            'gateway_provider' => 'pay_at_pickup',
            'status' => 'pending',
            'amount' => 120,
        ]);
        $this->assertDatabaseHas('tickets', [
            'customer_phone' => '5551234567',
            'customer_email' => 'cliente@example.com',
            'source' => 'public_web',
        ]);
    }

    public function test_replaying_same_idempotency_key_does_not_duplicate_effects(): void
    {
        $product = $this->createProductWithDependencies();
        $key = (string) Str::uuid();
        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'pay_at_pickup',
            'customer_name' => 'Cliente Prueba',
            'customer_phone' => '5551234567',
            'order_type' => 'takeout',
        ];

        $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/checkout', $payload)
            ->assertCreated()
            ->assertJsonPath('idempotent_replay', false)
            ->assertJsonStructure(['tracking_token']);

        $this->withHeader('Idempotency-Key', $key)->postJson('/api/checkout', $payload)
            ->assertOk()
            ->assertJsonPath('ticket_id', $first->json('ticket_id'))
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('tracking_token', $first->json('tracking_token'));

        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('inventory_transactions', 1);

        $ticketNumber = $first->json('ticket_number');
        $token = urlencode($first->json('tracking_token'));
        $this->getJson("/api/orders/{$ticketNumber}/status?token={$token}")
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->getJson("/api/orders/{$ticketNumber}/status?token=".str_repeat('x', 48))
            ->assertNotFound()
            ->assertJsonPath('code', 'ORDER_NOT_FOUND');
    }

    public function test_reusing_idempotency_key_with_different_payload_is_rejected(): void
    {
        $product = $this->createProductWithDependencies();
        $key = (string) Str::uuid();
        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'pay_at_pickup',
            'customer_name' => 'Cliente Prueba',
            'customer_phone' => '5551234567',
            'order_type' => 'takeout',
        ];

        $this->withHeader('Idempotency-Key', $key)->postJson('/api/checkout', $payload)->assertCreated();

        $payload['items'][0]['quantity'] = 2;
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/checkout', $payload)
            ->assertStatus(409)
            ->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');

        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }
}
