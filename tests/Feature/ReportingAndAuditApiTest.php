<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportingAndAuditApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->withRole('owner')->create();
        Sanctum::actingAs($this->owner);
    }

    public function test_daily_report_is_calculated_by_server(): void
    {
        $delivered = Ticket::create([
            'ticket_number' => 'REPORT-1',
            'status' => 'delivered',
            'total' => 100,
        ]);
        Ticket::create([
            'ticket_number' => 'REPORT-2',
            'status' => 'cancelled',
            'total' => 50,
        ]);
        Payment::create([
            'ticket_id' => $delivered->id,
            'gateway_provider' => 'cash',
            'amount' => 100,
            'status' => 'approved',
            'evidence_type' => 'cashier_confirmation',
            'paid_at' => now(),
            'confirmed_by' => $this->owner->id,
        ]);
        CashRegisterSession::create([
            'opened_by' => $this->owner->id,
            'closed_by' => $this->owner->id,
            'opening_amount' => 500,
            'expected_cash' => 600,
            'counted_cash' => 590,
            'difference' => -10,
            'status' => 'closed',
            'opened_at' => now(),
            'closed_at' => now(),
        ]);

        $this->getJson('/api/reports/daily?date='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('orders.total', 2)
            ->assertJsonPath('orders.delivered', 1)
            ->assertJsonPath('orders.cancelled', 1)
            ->assertJsonPath('orders.gross_non_cancelled', 100)
            ->assertJsonPath('payments.gross_collected', 100)
            ->assertJsonPath('payments.refunded_total', 0)
            ->assertJsonPath('payments.net_collected', 100)
            ->assertJsonPath('payments.methods.0.gateway_provider', 'cash')
            ->assertJsonPath('cash.difference_total', -10);
    }

    public function test_sensitive_model_changes_create_queryable_audit_events(): void
    {
        $category = Category::create([
            'name' => 'Inicial',
            'slug' => 'inicial',
            'active' => true,
        ]);
        $category->update(['name' => 'Actualizada']);

        $this->getJson('/api/audit-events?resource_type=Category')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.action', 'updated')
            ->assertJsonPath('data.0.before_data.name', 'Inicial')
            ->assertJsonPath('data.0.after_data.name', 'Actualizada')
            ->assertJsonPath('data.0.user_id', $this->owner->id);
    }

    public function test_cashier_cannot_read_reports_or_audit(): void
    {
        Sanctum::actingAs(User::factory()->withRole('cashier')->create());

        $this->getJson('/api/reports/daily')->assertForbidden();
        $this->getJson('/api/audit-events')->assertForbidden();
    }
}
