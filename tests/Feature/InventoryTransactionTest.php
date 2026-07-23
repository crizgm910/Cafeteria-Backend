<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create an admin user and authenticate
        $user = User::factory()->withRole()->create();
        $this->actingAs($user, 'sanctum');
    }

    public function test_can_process_restock()
    {
        $ingredient = Ingredient::create(['sku' => 'TEST-'.uniqid(), 'name' => 'Test', 'unit_of_measure' => 'kg', 'current_stock' => 10, 'minimum_stock' => 1, 'cost_per_unit' => 10]);

        $response = $this->postJson('/api/inventory/transactions', [
            'ingredient_id' => $ingredient->id,
            'transaction_type' => 'restock',
            'quantity' => 5,
            'reason' => 'Prueba automatizada',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('inventory_transactions', [
            'ingredient_id' => $ingredient->id,
            'transaction_type' => 'restock',
            'quantity' => 5,
            'stock_after_transaction' => 15,
        ]);
        $this->assertDatabaseHas('ingredients', ['id' => $ingredient->id, 'current_stock' => 15]);
    }

    public function test_can_process_sale()
    {
        $ingredient = Ingredient::create(['sku' => 'TEST-'.uniqid(), 'name' => 'Test', 'unit_of_measure' => 'kg', 'current_stock' => 10, 'minimum_stock' => 1, 'cost_per_unit' => 10]);

        $response = $this->postJson('/api/inventory/transactions', [
            'ingredient_id' => $ingredient->id,
            'transaction_type' => 'sale',
            'quantity' => 2,
            'reason' => 'Prueba automatizada',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('inventory_transactions', [
            'ingredient_id' => $ingredient->id,
            'transaction_type' => 'sale',
            'quantity' => -2,
            'stock_after_transaction' => 8,
        ]);
        $this->assertDatabaseHas('ingredients', ['id' => $ingredient->id, 'current_stock' => 8]);
    }

    public function test_can_process_waste()
    {
        $ingredient = Ingredient::create(['sku' => 'TEST-'.uniqid(), 'name' => 'Test', 'unit_of_measure' => 'kg', 'current_stock' => 10, 'minimum_stock' => 1, 'cost_per_unit' => 10]);

        $response = $this->postJson('/api/inventory/transactions', [
            'ingredient_id' => $ingredient->id,
            'transaction_type' => 'waste',
            'quantity' => 3,
            'reason' => 'Prueba automatizada',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('inventory_transactions', [
            'ingredient_id' => $ingredient->id,
            'transaction_type' => 'waste',
            'quantity' => -3,
            'stock_after_transaction' => 7,
        ]);
        $this->assertDatabaseHas('ingredients', ['id' => $ingredient->id, 'current_stock' => 7]);
    }

    public function test_can_process_positive_adjustment()
    {
        $ingredient = Ingredient::create(['sku' => 'TEST-'.uniqid(), 'name' => 'Test', 'unit_of_measure' => 'kg', 'current_stock' => 10, 'minimum_stock' => 1, 'cost_per_unit' => 10]);

        $response = $this->postJson('/api/inventory/transactions', [
            'ingredient_id' => $ingredient->id,
            'transaction_type' => 'adjustment',
            'quantity' => 4,
            'reason' => 'Prueba automatizada',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('inventory_transactions', [
            'ingredient_id' => $ingredient->id,
            'transaction_type' => 'adjustment',
            'quantity' => 4,
            'stock_after_transaction' => 14,
        ]);
        $this->assertDatabaseHas('ingredients', ['id' => $ingredient->id, 'current_stock' => 14]);
    }

    public function test_can_process_negative_adjustment()
    {
        $ingredient = Ingredient::create(['sku' => 'TEST-'.uniqid(), 'name' => 'Test', 'unit_of_measure' => 'kg', 'current_stock' => 10, 'minimum_stock' => 1, 'cost_per_unit' => 10]);

        $response = $this->postJson('/api/inventory/transactions', [
            'ingredient_id' => $ingredient->id,
            'transaction_type' => 'adjustment',
            'quantity' => -4,
            'reason' => 'Prueba automatizada',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('inventory_transactions', [
            'ingredient_id' => $ingredient->id,
            'transaction_type' => 'adjustment',
            'quantity' => -4,
            'stock_after_transaction' => 6,
        ]);
        $this->assertDatabaseHas('ingredients', ['id' => $ingredient->id, 'current_stock' => 6]);
    }

    public function test_rejects_zero_quantity()
    {
        $ingredient = Ingredient::create(['sku' => 'TEST-'.uniqid(), 'name' => 'Test', 'unit_of_measure' => 'kg', 'current_stock' => 10, 'minimum_stock' => 1, 'cost_per_unit' => 10]);

        $response = $this->postJson('/api/inventory/transactions', [
            'ingredient_id' => $ingredient->id,
            'transaction_type' => 'restock',
            'quantity' => 0,
            'reason' => 'Prueba automatizada',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['message' => 'La cantidad no puede ser cero.']);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_rejects_negative_quantity_for_restock_sale_waste()
    {
        $ingredient = Ingredient::create(['sku' => 'TEST-'.uniqid(), 'name' => 'Test', 'unit_of_measure' => 'kg', 'current_stock' => 10, 'minimum_stock' => 1, 'cost_per_unit' => 10]);

        $types = ['restock', 'sale', 'waste'];

        foreach ($types as $type) {
            $response = $this->postJson('/api/inventory/transactions', [
                'ingredient_id' => $ingredient->id,
                'transaction_type' => $type,
                'quantity' => -5,
                'reason' => 'Prueba automatizada',
            ]);

            $response->assertStatus(400);
            $response->assertJson(['message' => 'La cantidad debe ser positiva para este tipo de transacción.']);
        }
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_rejects_transaction_resulting_in_negative_stock_and_rollbacks()
    {
        $ingredient = Ingredient::create(['sku' => 'TEST-'.uniqid(), 'name' => 'Test', 'unit_of_measure' => 'kg', 'current_stock' => 5, 'minimum_stock' => 1, 'cost_per_unit' => 10]);

        $response = $this->postJson('/api/inventory/transactions', [
            'ingredient_id' => $ingredient->id,
            'transaction_type' => 'sale',
            'quantity' => 10, // Try to sell 10 but only 5 in stock
            'reason' => 'Prueba automatizada',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Error al procesar la transacción', 'error' => 'Stock insuficiente para esta transacción.']);
        
        // Assert rollback: no transaction created, stock remains the same
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertDatabaseHas('ingredients', ['id' => $ingredient->id, 'current_stock' => 5]);
    }

    public function test_manual_movement_records_actor_reason_and_stock_before(): void
    {
        $ingredient = Ingredient::create(['sku' => 'TRACE-'.uniqid(), 'name' => 'Trazable', 'unit_of_measure' => 'kg', 'current_stock' => 10, 'minimum_stock' => 1, 'cost_per_unit' => 10]);

        $this->postJson('/api/inventory/transactions', [
            'ingredient_id' => $ingredient->id,
            'transaction_type' => 'restock',
            'quantity' => 2,
            'reason' => 'Compra a proveedor',
            'notes' => 'Factura de prueba',
        ])->assertCreated();

        $this->assertDatabaseHas('inventory_transactions', [
            'ingredient_id' => $ingredient->id,
            'stock_before_transaction' => 10,
            'stock_after_transaction' => 12,
            'reason' => 'Compra a proveedor',
        ]);
        $this->assertDatabaseMissing('inventory_transactions', ['user_id' => null]);
    }

    public function test_ingredient_crud_cannot_change_current_stock_directly(): void
    {
        $ingredient = Ingredient::create(['sku' => 'LOCK-'.uniqid(), 'name' => 'Protegido', 'unit_of_measure' => 'kg', 'current_stock' => 10, 'minimum_stock' => 1, 'cost_per_unit' => 10]);

        $this->putJson('/api/ingredients/'.$ingredient->id, ['current_stock' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_stock');

        $this->assertDatabaseHas('ingredients', ['id' => $ingredient->id, 'current_stock' => 10]);
    }
}
