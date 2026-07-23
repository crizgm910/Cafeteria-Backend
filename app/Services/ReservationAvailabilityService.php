<?php

namespace App\Services;

use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\ReservationBlock;
use App\Models\ReservationSchedule;
use App\Models\ServiceArea;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReservationAvailabilityService
{
    public const BLOCKING_STATUSES = ['pending', 'approved', 'checked_in', 'seated'];

    public function slots(ServiceArea $area, CarbonImmutable $date, int $guests): array
    {
        return $this->slotsForAreas(collect([$area]), $date, $guests)[$area->id] ?? [];
    }

    public function slotsForAreas(Collection $areas, CarbonImmutable $date, int $guests): array
    {
        $areas = $areas->filter(fn (ServiceArea $area) => $area->active && $area->public_visible && $area->reservable)->values();
        if ($areas->isEmpty()) {
            return [];
        }

        $areaIds = $areas->pluck('id');
        $schedules = ReservationSchedule::where('day_of_week', $date->dayOfWeek)
            ->where(fn ($query) => $query->whereNull('service_area_id')->orWhereIn('service_area_id', $areaIds))->get();
        $globalSchedule = $schedules->firstWhere('service_area_id', null);
        $scheduleByArea = $areas->mapWithKeys(fn (ServiceArea $area) => [
            $area->id => $schedules->firstWhere('service_area_id', $area->id) ?? $globalSchedule,
        ]);
        $activeSchedules = $scheduleByArea->filter(fn ($schedule) => $schedule?->active);
        if ($activeSchedules->isEmpty()) {
            return $areas->mapWithKeys(fn ($area) => [$area->id => []])->all();
        }

        $tables = DiningTable::whereIn('service_area_id', $areaIds)
            ->where('active', true)->where('reservable', true)->where('status', 'available')
            ->where('min_capacity', '<=', $guests)->where('max_capacity', '>=', $guests)
            ->orderBy('max_capacity')->orderBy('sort_order')->orderBy('id')->get();
        if ($tables->isEmpty()) {
            return $areas->mapWithKeys(fn ($area) => [$area->id => []])->all();
        }

        $windowStart = $date->setTimeFromTimeString($activeSchedules->min('opens_at'));
        $windowEnd = $date->setTimeFromTimeString($activeSchedules->max('closes_at'));
        $tableIds = $tables->pluck('id');
        $blocks = ReservationBlock::where('active', true)
            ->where(fn ($query) => $query
                ->where(fn ($areaBlock) => $areaBlock->whereIn('service_area_id', $areaIds)->whereNull('dining_table_id'))
                ->orWhereIn('dining_table_id', $tableIds))
            ->where('starts_at', '<', $windowEnd)->where('ends_at', '>', $windowStart)
            ->get(['service_area_id', 'dining_table_id', 'starts_at', 'ends_at']);
        $reservations = Reservation::whereIn('dining_table_id', $tableIds)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->where('starts_at', '<', $windowEnd)->where('ends_at', '>', $windowStart)
            ->get(['dining_table_id', 'starts_at', 'ends_at']);

        return $areas->mapWithKeys(function (ServiceArea $area) use ($date, $scheduleByArea, $tables, $blocks, $reservations) {
            $schedule = $scheduleByArea->get($area->id);
            $areaTables = $tables->where('service_area_id', $area->id)->values();
            if (! $schedule?->active || $areaTables->isEmpty()) {
                return [$area->id => []];
            }

            $areaTableIds = $areaTables->pluck('id');
            $areaBlocks = $blocks->filter(fn (ReservationBlock $block) => ($block->dining_table_id === null && $block->service_area_id === $area->id)
                || $areaTableIds->contains($block->dining_table_id));
            $areaReservations = $reservations->whereIn('dining_table_id', $areaTableIds);
            $cursor = $date->setTimeFromTimeString($schedule->opens_at);
            $close = $date->setTimeFromTimeString($schedule->closes_at);
            $occupiedMinutes = $schedule->reservation_duration_minutes + $schedule->cleanup_buffer_minutes;
            $slots = [];

            while ($cursor->addMinutes($occupiedMinutes)->lessThanOrEqualTo($close)) {
                $end = $cursor->addMinutes($occupiedMinutes);
                $hasCapacity = $areaTables->contains(function (DiningTable $table) use ($areaBlocks, $areaReservations, $cursor, $end) {
                    $blocked = $areaBlocks->contains(fn (ReservationBlock $block) => ($block->dining_table_id === null || $block->dining_table_id === $table->id)
                        && $block->starts_at->lessThan($end) && $block->ends_at->greaterThan($cursor));
                    if ($blocked) {
                        return false;
                    }

                    return ! $areaReservations->contains(fn (Reservation $reservation) => $reservation->dining_table_id === $table->id
                        && $reservation->starts_at->lessThan($end) && $reservation->ends_at->greaterThan($cursor));
                });
                if ($cursor->isFuture() && $hasCapacity) {
                    $slots[] = $cursor->format('H:i');
                }
                $cursor = $cursor->addMinutes($schedule->slot_interval_minutes);
            }

            return [$area->id => $slots];
        })->all();
    }

    public function interval(ServiceArea $area, CarbonImmutable $start): array
    {
        $schedule = $this->scheduleFor($area, $start->dayOfWeek);
        if (! $schedule || ! $schedule->active) {
            throw ValidationException::withMessages(['time' => 'El área no acepta reservaciones ese día.']);
        }
        $open = $start->startOfDay()->setTimeFromTimeString($schedule->opens_at);
        $close = $start->startOfDay()->setTimeFromTimeString($schedule->closes_at);
        $end = $start->addMinutes($schedule->reservation_duration_minutes + $schedule->cleanup_buffer_minutes);
        if ($start->lessThan($open) || $end->greaterThan($close)) {
            throw ValidationException::withMessages(['time' => 'El horario solicitado está fuera del horario reservable.']);
        }

        return [$start, $end];
    }

    public function assign(ServiceArea $area, CarbonImmutable $start, int $guests): array
    {
        [$start, $end] = $this->interval($area, $start);
        $table = $this->availableTables($area, $start, $guests, true, $end)->first();
        if (! $table) {
            throw ValidationException::withMessages(['time' => 'Ya no existe una mesa disponible para ese horario y número de personas.']);
        }

        return [$table, $start, $end];
    }

    public function isTableAvailable(DiningTable $table, CarbonImmutable $start, CarbonImmutable $end, ?int $ignoreReservationId = null): bool
    {
        if (! $table->active || ! $table->reservable || $table->status !== 'available') {
            return false;
        }

        $blocked = ReservationBlock::where('active', true)
            ->where(fn ($q) => $q->where('dining_table_id', $table->id)
                ->orWhere(fn ($areaBlock) => $areaBlock->where('service_area_id', $table->service_area_id)->whereNull('dining_table_id')))
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists();
        if ($blocked) {
            return false;
        }

        return ! Reservation::where('dining_table_id', $table->id)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->when($ignoreReservationId, fn ($q) => $q->where('id', '!=', $ignoreReservationId))
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists();
    }

    private function availableTables(ServiceArea $area, CarbonImmutable $start, int $guests, bool $lock, ?CarbonImmutable $end = null): Collection
    {
        if (! $area->active || ! $area->public_visible || ! $area->reservable) {
            return collect();
        }
        if ($this->areaBlocked($area, $start, $end ?? $start->addMinutes(120))) {
            return collect();
        }

        $query = DiningTable::where('service_area_id', $area->id)
            ->where('active', true)->where('reservable', true)->where('status', 'available')
            ->where('min_capacity', '<=', $guests)->where('max_capacity', '>=', $guests)
            ->orderBy('max_capacity')->orderBy('sort_order')->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        $end ??= $this->interval($area, $start)[1];

        return $query->get()->filter(fn (DiningTable $table) => $this->isTableAvailable($table, $start, $end))->values();
    }

    private function scheduleFor(ServiceArea $area, int $day): ?ReservationSchedule
    {
        return ReservationSchedule::where('day_of_week', $day)
            ->where(fn ($q) => $q->where('service_area_id', $area->id)->orWhereNull('service_area_id'))
            ->orderByRaw('case when service_area_id is null then 1 else 0 end')->first();
    }

    private function areaBlocked(ServiceArea $area, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return ReservationBlock::where('active', true)->where('service_area_id', $area->id)
            ->whereNull('dining_table_id')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists();
    }
}
