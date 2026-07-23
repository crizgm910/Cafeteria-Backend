<?php

namespace Tests\Feature;

use App\Models\AddOn;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddOnInheritanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_defaults_are_resolved_in_public_menu(): void
    {
        [$category, $product] = $this->product();
        $addOn = AddOn::create(['name' => 'Vainilla', 'price_adjustment' => 10, 'active' => true]);
        $category->addOns()->attach($addOn->id, [
            'visible' => true, 'selected_by_default' => true, 'price_override' => 15, 'sort_order' => 2,
        ]);

        $this->getJson('/api/menu')->assertOk()
            ->assertJsonPath('0.products.0.add_ons.0.id', $addOn->id)
            ->assertJsonPath('0.products.0.add_ons.0.effective_price', 15)
            ->assertJsonPath('0.products.0.add_ons.0.selected_by_default', true)
            ->assertJsonPath('0.products.0.add_ons.0.source', 'category');
    }

    public function test_product_can_hide_an_inherited_add_on(): void
    {
        [$category, $product] = $this->product();
        $addOn = AddOn::create(['name' => 'Canela', 'price_adjustment' => 0, 'active' => true]);
        $category->addOns()->attach($addOn->id, ['visible' => true, 'selected_by_default' => false, 'sort_order' => 0]);
        $product->addOns()->attach($addOn->id, ['visible' => false]);

        $this->getJson('/api/menu')->assertOk()->assertJsonCount(0, '0.products.0.add_ons');

        Sanctum::actingAs(User::factory()->withRole('manager')->create());
        $this->getJson("/api/products/{$product->id}/configuration")->assertOk()
            ->assertJsonPath('add_ons.0.visible', false)
            ->assertJsonPath('add_ons.0.source', 'product');
    }

    public function test_category_configuration_endpoint_persists_defaults(): void
    {
        [$category] = $this->product();
        $addOn = AddOn::create(['name' => 'Almendra', 'price_adjustment' => 12, 'active' => true]);
        Sanctum::actingAs(User::factory()->withRole('manager')->create());

        $this->putJson("/api/categories/{$category->id}/add-ons", ['add_ons' => [[
            'add_on_id' => $addOn->id, 'visible' => true, 'selected_by_default' => true,
            'price_override' => 8, 'sort_order' => 1, 'override_recipe' => false,
        ]]])->assertOk()->assertJsonPath('0.configured', true);

        $this->assertDatabaseHas('category_add_ons', [
            'category_id' => $category->id, 'add_on_id' => $addOn->id,
            'selected_by_default' => true, 'price_override' => 8,
        ]);
    }

    public function test_category_update_saves_its_add_ons_in_one_request(): void
    {
        [$category] = $this->product();
        $addOn = AddOn::create(['name' => 'Helado extra', 'price_adjustment' => 18, 'active' => true]);
        Sanctum::actingAs(User::factory()->withRole('manager')->create());

        $this->putJson("/api/categories/{$category->id}", [
            'name' => 'Postres especiales',
            'active' => true,
            'add_ons' => [[
                'add_on_id' => $addOn->id,
                'visible' => true,
                'selected_by_default' => false,
                'price_override' => 20,
                'sort_order' => 1,
                'override_recipe' => false,
            ]],
        ])->assertOk()->assertJsonPath('name', 'Postres especiales');

        $this->assertDatabaseHas('category_add_ons', [
            'category_id' => $category->id,
            'add_on_id' => $addOn->id,
            'visible' => true,
            'price_override' => 20,
        ]);
    }

    public function test_unconfigured_add_ons_are_not_reported_as_visible(): void
    {
        [$category] = $this->product();
        AddOn::create(['name' => 'No relacionado', 'price_adjustment' => 12, 'active' => true]);
        Sanctum::actingAs(User::factory()->withRole('manager')->create());

        $this->getJson("/api/categories/{$category->id}/add-ons")
            ->assertOk()
            ->assertJsonPath('0.configured', false)
            ->assertJsonPath('0.visible', false);
    }

    public function test_product_recipe_and_price_override_drive_checkout(): void
    {
        [$category, $product] = $this->product();
        $globalIngredient = $this->ingredient('GLOBAL');
        $specialIngredient = $this->ingredient('SPECIAL');
        $addOn = AddOn::create(['name' => 'Shot especial', 'price_adjustment' => 10, 'active' => true]);
        $addOn->recipeItems()->create(['ingredient_id' => $globalIngredient->id, 'quantity_required' => 3]);
        $category->addOns()->attach($addOn->id, ['visible' => true, 'selected_by_default' => false, 'sort_order' => 0]);
        $product->addOns()->attach($addOn->id, [
            'visible' => true, 'price_override' => 20, 'override_recipe' => true,
        ]);
        DB::table('product_add_on_recipes')->insert([
            'product_id' => $product->id, 'add_on_id' => $addOn->id,
            'ingredient_id' => $specialIngredient->id, 'quantity_required' => 4,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'add_ons' => [$addOn->id]]],
            'payment_method' => 'pay_at_pickup', 'customer_name' => 'Prueba',
            'customer_phone' => '5551234567', 'order_type' => 'takeout',
        ])->assertCreated()->assertJsonPath('total', 140);

        $this->assertDatabaseHas('inventory_transactions', ['ingredient_id' => $specialIngredient->id, 'quantity' => -8]);
        $this->assertDatabaseMissing('inventory_transactions', ['ingredient_id' => $globalIngredient->id, 'reference_id' => $response->json('ticket_id')]);
        $this->assertDatabaseHas('ticket_item_add_on_consumptions', [
            'add_on_id' => $addOn->id, 'ingredient_id' => $specialIngredient->id, 'quantity_consumed' => 8,
        ]);
    }

    public function test_hidden_add_on_is_rejected_even_if_sent_manually(): void
    {
        [$category, $product] = $this->product();
        $addOn = AddOn::create(['name' => 'Oculto', 'price_adjustment' => 1, 'active' => true]);
        $category->addOns()->attach($addOn->id, ['visible' => false, 'selected_by_default' => false, 'sort_order' => 0]);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'add_ons' => [$addOn->id]]],
            'payment_method' => 'pay_at_pickup', 'customer_name' => 'Prueba',
            'customer_phone' => '5551234567', 'order_type' => 'takeout',
        ])->assertUnprocessable()->assertJsonPath('error', 'El complemento solicitado no pertenece a este producto.');
    }

    public function test_category_recipe_override_can_consume_multiple_ingredients(): void
    {
        [$category, $product] = $this->product();
        $globalIngredient = $this->ingredient('GLOBAL-MULTI');
        $syrup = $this->ingredient('SYRUP');
        $topping = $this->ingredient('TOPPING');
        $addOn = AddOn::create(['name' => 'Especial de categoría', 'price_adjustment' => 10, 'active' => true]);
        $addOn->recipeItems()->create(['ingredient_id' => $globalIngredient->id, 'quantity_required' => 9]);
        $category->addOns()->attach($addOn->id, [
            'visible' => true, 'selected_by_default' => false, 'sort_order' => 0, 'override_recipe' => true,
        ]);
        DB::table('category_add_on_recipes')->insert([
            [
                'category_id' => $category->id, 'add_on_id' => $addOn->id,
                'ingredient_id' => $syrup->id, 'quantity_required' => 2,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'category_id' => $category->id, 'add_on_id' => $addOn->id,
                'ingredient_id' => $topping->id, 'quantity_required' => 5,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $ticketId = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'add_ons' => [$addOn->id]]],
            'payment_method' => 'pay_at_pickup', 'customer_name' => 'Prueba',
            'customer_phone' => '5551234567', 'order_type' => 'takeout',
        ])->assertCreated()->json('ticket_id');

        $this->assertDatabaseHas('inventory_transactions', ['ingredient_id' => $syrup->id, 'quantity' => -4, 'reference_id' => $ticketId]);
        $this->assertDatabaseHas('inventory_transactions', ['ingredient_id' => $topping->id, 'quantity' => -10, 'reference_id' => $ticketId]);
        $this->assertDatabaseMissing('inventory_transactions', ['ingredient_id' => $globalIngredient->id, 'reference_id' => $ticketId]);
        $this->assertDatabaseCount('ticket_item_add_on_consumptions', 2);
    }

    public function test_cancellation_restores_original_add_on_consumption_after_recipe_changes(): void
    {
        [$category, $product] = $this->product();
        $original = $this->ingredient('ORIGINAL');
        $replacement = $this->ingredient('REPLACEMENT');
        $addOn = AddOn::create(['name' => 'Receta mutable', 'price_adjustment' => 5, 'active' => true]);
        $addOn->recipeItems()->create(['ingredient_id' => $original->id, 'quantity_required' => 6]);
        $category->addOns()->attach($addOn->id, ['visible' => true, 'selected_by_default' => false, 'sort_order' => 0]);

        $ticketId = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'add_ons' => [$addOn->id]]],
            'payment_method' => 'pay_at_pickup', 'customer_name' => 'Prueba',
            'customer_phone' => '5551234567', 'order_type' => 'takeout',
        ])->assertCreated()->json('ticket_id');

        $this->assertDatabaseHas('ingredients', ['id' => $original->id, 'current_stock' => 994]);
        $addOn->recipeItems()->delete();
        $addOn->recipeItems()->create(['ingredient_id' => $replacement->id, 'quantity_required' => 20]);

        Sanctum::actingAs(User::factory()->withRole('manager')->create());
        $this->patchJson("/api/tickets/{$ticketId}/status", [
            'status' => 'cancelled', 'cancellation_reason' => 'Prueba de restauración histórica',
        ])->assertOk();

        $this->assertDatabaseHas('ingredients', ['id' => $original->id, 'current_stock' => 1000]);
        $this->assertDatabaseHas('ingredients', ['id' => $replacement->id, 'current_stock' => 1000]);
        $this->assertDatabaseHas('inventory_transactions', [
            'ingredient_id' => $original->id, 'transaction_type' => 'adjustment',
            'quantity' => 6, 'reference_id' => $ticketId,
        ]);
    }

    private function product(): array
    {
        $category = Category::create(['name' => 'Bebidas', 'slug' => 'bebidas-'.uniqid(), 'active' => true]);
        $productIngredient = $this->ingredient('BASE');
        $product = Product::create([
            'category_id' => $category->id, 'sku' => 'P-'.uniqid(), 'name' => 'Latte',
            'price' => 50, 'active' => true,
        ]);
        $product->ingredients()->attach($productIngredient->id, ['quantity_required' => 10]);
        return [$category, $product];
    }

    private function ingredient(string $name): Ingredient
    {
        return Ingredient::create([
            'sku' => 'I-'.$name.'-'.uniqid(), 'name' => $name, 'unit_of_measure' => 'ml',
            'current_stock' => 1000, 'minimum_stock' => 1, 'cost_per_unit' => 1,
        ]);
    }
}
