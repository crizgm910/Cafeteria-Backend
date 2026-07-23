<?php

namespace Tests\Feature;

use App\Models\AddOn;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductConfigurationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->withRole('manager')->create());
    }

    public function test_recipe_and_add_ons_can_be_configured_for_a_product(): void
    {
        $product = $this->product();
        $milk = $this->ingredient('MILK');
        $coffee = $this->ingredient('COFFEE');
        $addOn = AddOn::create([
            'name' => 'Extra café',
            'price_adjustment' => 12,
            'ingredient_id' => $coffee->id,
            'quantity_required' => 5,
        ]);

        $this->putJson("/api/products/{$product->id}/recipe", [
            'ingredients' => [
                ['ingredient_id' => $milk->id, 'quantity_required' => 200],
                ['ingredient_id' => $coffee->id, 'quantity_required' => 15],
            ],
        ])->assertOk()
            ->assertJsonPath('is_sellable', true)
            ->assertJsonCount(2, 'ingredients');

        $this->putJson("/api/products/{$product->id}/add-ons", [
            'add_on_ids' => [$addOn->id],
        ])->assertOk()
            ->assertJsonPath('add_ons.0.id', $addOn->id);

        $this->assertDatabaseHas('product_recipes', [
            'product_id' => $product->id,
            'ingredient_id' => $milk->id,
            'quantity_required' => 200,
        ]);
        $this->assertDatabaseHas('product_add_ons', [
            'product_id' => $product->id,
            'add_on_id' => $addOn->id,
        ]);
    }

    public function test_recipe_rejects_zero_quantities_and_duplicate_ingredients(): void
    {
        $product = $this->product();
        $ingredient = $this->ingredient('INVALID');

        $this->putJson("/api/products/{$product->id}/recipe", [
            'ingredients' => [
                ['ingredient_id' => $ingredient->id, 'quantity_required' => 0],
                ['ingredient_id' => $ingredient->id, 'quantity_required' => 1],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'ingredients.0.ingredient_id',
                'ingredients.1.ingredient_id',
                'ingredients.0.quantity_required',
            ]);
    }

    public function test_public_menu_excludes_active_products_without_recipe(): void
    {
        $product = $this->product();

        $this->getJson('/api/menu')
            ->assertOk()
            ->assertJsonCount(0, '0.products');

        $product->ingredients()->attach($this->ingredient('READY')->id, ['quantity_required' => 1]);

        $this->getJson('/api/menu')
            ->assertOk()
            ->assertJsonPath('0.products.0.id', $product->id);
    }

    public function test_public_menu_excludes_products_without_stock_for_one_portion(): void
    {
        $product = $this->product();
        $ingredient = $this->ingredient('LIMITED');
        $ingredient->update(['current_stock' => 0]);
        $product->ingredients()->attach($ingredient->id, ['quantity_required' => 1]);

        $this->getJson('/api/menu')
            ->assertOk()
            ->assertJsonCount(0, '0.products');

        $ingredient->update(['current_stock' => 1]);

        $this->getJson('/api/menu')
            ->assertOk()
            ->assertJsonPath('0.products.0.id', $product->id);
    }

    public function test_add_on_is_deactivated_instead_of_deleted(): void
    {
        $addOn = AddOn::create([
            'name' => 'Vainilla',
            'price_adjustment' => 10,
            'quantity_required' => 0,
        ]);

        $this->deleteJson("/api/add-ons/{$addOn->id}")
            ->assertOk();

        $this->assertDatabaseHas('add_ons', [
            'id' => $addOn->id,
            'active' => false,
        ]);
    }

    private function product(): Product
    {
        $category = Category::create([
            'name' => 'Bebidas',
            'slug' => 'bebidas-'.uniqid(),
            'active' => true,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'sku' => 'PRODUCT-'.uniqid(),
            'name' => 'Latte',
            'price' => 55,
            'active' => true,
        ]);
    }

    private function ingredient(string $suffix): Ingredient
    {
        return Ingredient::create([
            'sku' => 'ING-'.$suffix.'-'.uniqid(),
            'name' => 'Ingrediente '.$suffix,
            'unit_of_measure' => 'ml',
            'current_stock' => 1000,
            'minimum_stock' => 10,
            'cost_per_unit' => 1,
        ]);
    }
}
