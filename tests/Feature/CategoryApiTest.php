<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->withRole()->create());
    }

    public function test_staff_can_create_list_show_and_update_a_category(): void
    {
        $create = $this->postJson('/api/categories', [
            'name' => 'Café de Autor',
            'active' => true,
        ])->assertCreated()
            ->assertJsonPath('name', 'Café de Autor')
            ->assertJsonPath('slug', 'cafe-de-autor')
            ->assertJsonPath('active', true)
            ->assertJsonPath('products_count', 0);

        $categoryId = $create->json('id');

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('0.id', $categoryId)
            ->assertJsonPath('0.products_count', 0);

        $this->getJson("/api/categories/{$categoryId}")
            ->assertOk()
            ->assertJsonPath('name', 'Café de Autor');

        $this->putJson("/api/categories/{$categoryId}", [
            'name' => 'Bebidas de Autor',
            'active' => false,
        ])->assertOk()
            ->assertJsonPath('name', 'Bebidas de Autor')
            ->assertJsonPath('slug', 'bebidas-de-autor')
            ->assertJsonPath('active', false);

        $this->assertDatabaseHas('categories', [
            'id' => $categoryId,
            'name' => 'Bebidas de Autor',
            'active' => false,
        ]);
    }

    public function test_category_name_must_be_unique(): void
    {
        Category::create([
            'name' => 'Postres',
            'slug' => 'postres',
            'active' => true,
        ]);

        $this->postJson('/api/categories', [
            'name' => 'Postres',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_empty_category_can_be_deleted(): void
    {
        $category = Category::create([
            'name' => 'Temporada',
            'slug' => 'temporada',
            'active' => true,
        ]);

        $this->deleteJson("/api/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Categoría eliminada correctamente.');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $category = Category::create([
            'name' => 'Bebidas',
            'slug' => 'bebidas',
            'active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'sku' => 'CAFE-01',
            'name' => 'Café',
            'price' => 50,
            'active' => true,
        ]);

        $this->deleteJson("/api/categories/{$category->id}")
            ->assertStatus(409)
            ->assertJsonPath('products_count', 1);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_inactive_categories_are_hidden_from_public_menu(): void
    {
        Category::create([
            'name' => 'Visible',
            'slug' => 'visible',
            'active' => true,
        ]);
        Category::create([
            'name' => 'Oculta',
            'slug' => 'oculta',
            'active' => false,
        ]);

        $this->getJson('/api/menu')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.slug', 'visible');
    }
}
