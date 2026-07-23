<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddOnApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->withRole('manager')->create());
    }

    public function test_manager_can_create_list_and_edit_an_add_on_with_recipe(): void
    {
        $ingredient = Ingredient::create([
            'sku' => 'ING-CANELA', 'name' => 'Canela', 'unit_of_measure' => 'gramos',
            'current_stock' => 100, 'minimum_stock' => 10, 'cost_per_unit' => 1,
        ]);

        $created = $this->postJson('/api/add-ons', [
            'name' => 'Canela extra',
            'description' => 'Porción adicional',
            'price_adjustment' => 8,
            'active' => true,
            'public_visible' => true,
            'sort_order' => 2,
            'recipe' => [[
                'ingredient_id' => $ingredient->id,
                'quantity_required' => 3,
            ]],
        ])->assertCreated()
            ->assertJsonPath('name', 'Canela extra')
            ->assertJsonPath('recipe_items.0.ingredient.name', 'Canela')
            ->assertJsonPath('products_count', 0)
            ->assertJsonPath('categories_count', 0);

        $id = $created->json('id');

        $this->getJson('/api/add-ons')->assertOk()
            ->assertJsonPath('0.id', $id)
            ->assertJsonStructure(['0' => ['products_count', 'categories_count', 'ticket_items_count']]);

        $this->putJson("/api/add-ons/{$id}", [
            'name' => 'Canela premium',
            'price_adjustment' => 10,
            'recipe' => [[
                'ingredient_id' => $ingredient->id,
                'quantity_required' => 4,
            ]],
        ])->assertOk()
            ->assertJsonPath('name', 'Canela premium')
            ->assertJsonPath('recipe_items.0.quantity_required', '4.00');

        $this->assertDatabaseHas('add_on_recipes', [
            'add_on_id' => $id,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => 4,
        ]);
    }

    public function test_add_on_names_are_unique_and_delete_is_reversible_deactivation(): void
    {
        $first = $this->postJson('/api/add-ons', [
            'name' => 'Vainilla', 'price_adjustment' => 12,
        ])->assertCreated();

        $this->postJson('/api/add-ons', [
            'name' => 'Vainilla', 'price_adjustment' => 15,
        ])->assertUnprocessable()->assertJsonValidationErrors('name');

        $id = $first->json('id');
        $this->deleteJson("/api/add-ons/{$id}")->assertOk();
        $this->assertDatabaseHas('add_ons', ['id' => $id, 'active' => false]);

        $this->putJson("/api/add-ons/{$id}", ['active' => true])
            ->assertOk()->assertJsonPath('active', true);
    }
}
