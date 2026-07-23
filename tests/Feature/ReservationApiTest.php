<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;
use App\Models\ServiceArea;
use App\Models\DiningTable;

class ReservationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Idempotency-Key', (string) Str::uuid());
    }

    public function test_rejects_past_or_invalid_reservation_dates_and_times(): void
    {
        $base = ['name' => 'Cliente', 'email' => 'cliente@example.com', 'phone' => '5551234567', 'guests' => 2, 'service_area_id' => 999999];

        $this->postJson('/api/reservations', $base + ['date' => now()->subDay()->format('Y-m-d'), 'time' => '12:00'])
            ->assertStatus(422)->assertJsonValidationErrors('date');
        $this->postJson('/api/reservations', $base + ['date' => now()->format('Y-m-d'), 'time' => 'mediodía'])
            ->assertStatus(422)->assertJsonValidationErrors('time');
    }

    public function test_completed_status_is_supported_by_schema_and_api(): void
    {
        $this->actingAs(User::factory()->withRole()->create(), 'sanctum');
        $reservation = Reservation::create([
            'name' => 'Cliente', 'email' => 'cliente@example.com',
            'date' => now()->addDay()->format('Y-m-d'), 'time' => '12:00',
            'guests' => 2, 'status' => 'ready',
        ]);

        $this->patchJson("/api/reservations/{$reservation->id}/status", ['status' => 'completed'])
            ->assertOk()->assertJsonPath('reservation.status', 'completed');
    }

    public function test_repeated_reservation_request_is_idempotent(): void
    {
        $area = ServiceArea::create(['name' => 'Salón', 'slug' => 'salon']);
        DiningTable::create(['service_area_id' => $area->id, 'code' => 'S01', 'min_capacity' => 1, 'max_capacity' => 4]);
        $key = (string) Str::uuid();
        $payload = [
            'service_area_id' => $area->id, 'name' => 'Cliente', 'email' => 'CLIENTE@example.com', 'phone' => '5551234567',
            'date' => now()->addDay()->format('Y-m-d'), 'time' => '12:00', 'guests' => 2,
        ];

        $firstId = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/reservations', $payload)->assertCreated()->json('reservation.id');
        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/reservations', $payload)
            ->assertOk()->assertJsonPath('reservation.id', $firstId)->assertJsonPath('idempotent_replay', true);

        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseHas('reservations', ['email' => 'cliente@example.com']);
    }
}
