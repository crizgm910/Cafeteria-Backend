<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\ReservationBlock;
use App\Models\ReservationSchedule;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationCapacityApiTest extends TestCase
{
    use RefreshDatabase;

    private function areaWithTable(int $capacity = 4): array
    {
        $area = ServiceArea::create(['name' => 'Terraza', 'slug' => 'terraza-'.Str::lower(Str::random(8)), 'description' => 'Área exterior']);
        $table = DiningTable::create(['service_area_id' => $area->id, 'code' => 'T01', 'min_capacity' => 1, 'max_capacity' => $capacity]);

        return [$area, $table];
    }

    private function payload(ServiceArea $area, array $overrides = []): array
    {
        return $overrides + ['service_area_id' => $area->id, 'name' => 'Cliente', 'email' => 'cliente@example.com', 'phone' => '5551234567', 'date' => now()->addDay()->format('Y-m-d'), 'time' => '12:00', 'guests' => 2];
    }

    public function test_public_availability_returns_only_compatible_areas_and_slots(): void
    {
        [$area] = $this->areaWithTable();
        $this->getJson('/api/reservation-availability?date='.now()->addDay()->format('Y-m-d').'&guests=2')
            ->assertOk()->assertJsonPath('areas.0.id', $area->id)->assertJsonStructure(['areas' => [['available_slots']]]);
        $this->getJson('/api/reservation-availability?date='.now()->addDay()->format('Y-m-d').'&guests=8')
            ->assertOk()->assertJsonCount(0, 'areas');
    }

    public function test_second_request_cannot_overbook_the_only_table(): void
    {
        [$area] = $this->areaWithTable();
        $payload = $this->payload($area);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/reservations', $payload)->assertCreated();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/reservations', $payload)
            ->assertStatus(422)->assertJsonValidationErrors('time');
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_staff_can_manage_areas_tables_reassign_and_advance_state(): void
    {
        $this->actingAs(User::factory()->withRole()->create(), 'sanctum');
        $areaId = $this->postJson('/api/service-areas', ['name' => 'Privado'])->assertCreated()->json('id');
        $first = $this->postJson('/api/dining-tables', ['service_area_id' => $areaId, 'code' => 'P01', 'min_capacity' => 1, 'max_capacity' => 4])->assertCreated()->json();
        $second = $this->postJson('/api/dining-tables', ['service_area_id' => $areaId, 'code' => 'P02', 'min_capacity' => 1, 'max_capacity' => 6])->assertCreated()->json();

        $reservation = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/reservations', $this->payload(ServiceArea::findOrFail($areaId)))->assertCreated()->json('reservation');
        $this->patchJson('/api/reservations/'.$reservation['id'].'/assignment', ['dining_table_id' => $second['id'], 'lock_version' => $reservation['lock_version']])
            ->assertOk()->assertJsonPath('reservation.table.id', $second['id']);
        $version = Reservation::findOrFail($reservation['id'])->lock_version;
        $this->patchJson('/api/reservations/'.$reservation['id'].'/status', ['status' => 'approved', 'lock_version' => $version])->assertOk();
        $version = Reservation::findOrFail($reservation['id'])->lock_version;
        $this->patchJson('/api/reservations/'.$reservation['id'].'/status', ['status' => 'checked_in', 'lock_version' => $version])->assertOk();
        $this->assertNotSame($first['id'], $second['id']);
    }

    public function test_area_with_history_is_deactivated_instead_of_deleted(): void
    {
        $this->actingAs(User::factory()->withRole()->create(), 'sanctum');
        [$area] = $this->areaWithTable();
        $this->deleteJson('/api/service-areas/'.$area->id)->assertOk()->assertJsonPath('area.active', false);
        $this->assertDatabaseHas('service_areas', ['id' => $area->id, 'active' => false]);
    }

    public function test_hidden_or_non_operational_capacity_is_not_offered(): void
    {
        [$area, $table] = $this->areaWithTable();
        $date = now()->addDay()->format('Y-m-d');

        $area->update(['public_visible' => false]);
        $this->getJson("/api/reservation-availability?date={$date}&guests=2")->assertOk()->assertJsonCount(0, 'areas');

        $area->update(['public_visible' => true]);
        $table->update(['status' => 'cleaning']);
        $this->getJson("/api/reservation-availability?date={$date}&guests=2")->assertOk()->assertJsonCount(0, 'areas');
    }

    public function test_area_specific_closed_day_overrides_global_schedule(): void
    {
        [$area] = $this->areaWithTable();
        $date = now()->addDay();
        ReservationSchedule::create([
            'service_area_id' => $area->id,
            'day_of_week' => $date->dayOfWeek,
            'opens_at' => '07:00',
            'closes_at' => '22:00',
            'slot_interval_minutes' => 30,
            'reservation_duration_minutes' => 90,
            'cleanup_buffer_minutes' => 15,
            'active' => false,
        ]);

        $this->getJson('/api/reservation-availability?date='.$date->format('Y-m-d').'&guests=2')
            ->assertOk()->assertJsonCount(0, 'areas');
    }

    public function test_table_block_does_not_block_other_tables_but_area_block_does(): void
    {
        [$area, $first] = $this->areaWithTable();
        $second = DiningTable::create(['service_area_id' => $area->id, 'code' => 'T02', 'min_capacity' => 1, 'max_capacity' => 4]);
        $date = now()->addDay()->startOfDay();
        ReservationBlock::create(['service_area_id' => $area->id, 'dining_table_id' => $first->id, 'starts_at' => $date->copy()->setTime(11, 0), 'ends_at' => $date->copy()->setTime(15, 0), 'reason' => 'Mantenimiento']);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/reservations', $this->payload($area))
            ->assertCreated()->assertJsonPath('reservation.dining_table_id', $second->id);

        Reservation::query()->delete();
        ReservationBlock::create(['service_area_id' => $area->id, 'starts_at' => $date->copy()->setTime(11, 0), 'ends_at' => $date->copy()->setTime(15, 0), 'reason' => 'Evento privado']);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/reservations', $this->payload($area))
            ->assertUnprocessable()->assertJsonValidationErrors('time');
    }

    public function test_staff_can_create_schedule_and_block_with_consistent_area_table(): void
    {
        $this->actingAs(User::factory()->withRole()->create(), 'sanctum');
        [$area, $table] = $this->areaWithTable();
        [$otherArea] = $this->areaWithTable();
        $date = now()->addDays(2)->startOfDay();

        $this->postJson('/api/reservation-schedules', ['service_area_id' => $area->id, 'day_of_week' => $date->dayOfWeek, 'opens_at' => '08:00', 'closes_at' => '18:00'])
            ->assertCreated();
        $this->postJson('/api/reservation-blocks', ['service_area_id' => $otherArea->id, 'dining_table_id' => $table->id, 'starts_at' => $date->toIso8601String(), 'ends_at' => $date->addHour()->toIso8601String(), 'reason' => 'Prueba'])
            ->assertUnprocessable()->assertJsonValidationErrors('dining_table_id');
    }

    public function test_availability_uses_a_bounded_number_of_queries(): void
    {
        $this->areaWithTable();
        $this->areaWithTable();
        $this->areaWithTable();
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->getJson('/api/reservation-availability?date='.now()->addDay()->format('Y-m-d').'&guests=2')
            ->assertOk()->assertJsonCount(3, 'areas');

        $this->assertLessThanOrEqual(8, $queries, 'La disponibilidad no debe consultar por cada horario o mesa.');
    }
}
