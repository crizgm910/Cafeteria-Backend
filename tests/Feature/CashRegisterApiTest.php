<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashRegisterApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->withRole('cashier')->create());
    }

    public function test_cashier_can_open_move_and_close_a_balanced_session(): void
    {
        $sessionId = $this->postJson('/api/cash-register/open', ['opening_amount' => 500])
            ->assertCreated()
            ->assertJsonPath('data.calculated_expected_cash', 500)
            ->json('data.id');

        $this->postJson('/api/cash-register/movements', [
            'type' => 'deposit',
            'amount' => 100,
            'note' => 'Cambio adicional',
        ])->assertCreated()
            ->assertJsonPath('data.calculated_expected_cash', 600);

        $this->postJson('/api/cash-register/movements', [
            'type' => 'withdrawal',
            'amount' => 50,
            'note' => 'Retiro autorizado',
        ])->assertCreated()
            ->assertJsonPath('data.calculated_expected_cash', 550);

        $this->postJson('/api/cash-register/close', [
            'counted_cash' => 550,
        ])->assertOk()
            ->assertJsonPath('data.id', $sessionId)
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.difference', '0.00');

        $this->getJson('/api/cash-register/current')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_duplicate_open_and_movement_without_session_are_rejected(): void
    {
        $this->postJson('/api/cash-register/open', ['opening_amount' => 0])->assertCreated();
        $this->postJson('/api/cash-register/open', ['opening_amount' => 0])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CASH_SESSION_ALREADY_OPEN');

        $this->postJson('/api/cash-register/close', ['counted_cash' => 0])->assertOk();
        $this->postJson('/api/cash-register/movements', [
            'type' => 'deposit',
            'amount' => 10,
            'note' => 'Sin turno',
        ])->assertStatus(409)
            ->assertJsonPath('code', 'CASH_SESSION_REQUIRED');
    }

    public function test_preparation_role_cannot_access_cash_register(): void
    {
        Sanctum::actingAs(User::factory()->withRole('preparation')->create());

        $this->getJson('/api/cash-register/current')->assertForbidden();
    }
}
