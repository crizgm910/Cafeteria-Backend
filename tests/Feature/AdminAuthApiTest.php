<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_login_validate_session_load_categories_and_logout(): void
    {
        $user = User::factory()->withRole()->create([
            'password' => 'secret-password',
        ]);

        Category::create([
            'name' => 'Café',
            'slug' => 'cafe',
            'active' => true,
        ]);

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['roles', 'permissions']]);

        $headers = [
            'Authorization' => 'Bearer '.$login->json('token'),
        ];

        $this->getJson('/api/user', $headers)
            ->assertOk()
            ->assertJsonPath('id', $user->id);

        $this->getJson('/api/categories', $headers)
            ->assertOk()
            ->assertJsonPath('0.slug', 'cafe');

        $this->getJson('/api/catalog/bootstrap', $headers)
            ->assertOk()
            ->assertJsonStructure(['categories', 'products', 'ingredients'])
            ->assertJsonPath('categories.0.slug', 'cafe');

        $this->postJson('/api/logout', [], $headers)
            ->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada correctamente');

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/user', $headers)->assertUnauthorized();
    }

    public function test_categories_are_not_available_without_authentication(): void
    {
        $this->getJson('/api/categories')->assertUnauthorized();
    }
}
