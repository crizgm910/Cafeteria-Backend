<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReservationSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationScheduleController extends Controller
{
    public function index() { return response()->json(ReservationSchedule::with('area')->orderByRaw('case when service_area_id is null then 0 else 1 end')->orderBy('service_area_id')->orderBy('day_of_week')->get()); }
    public function store(Request $request) { return response()->json(ReservationSchedule::create($this->validated($request)), 201); }
    public function update(Request $request, ReservationSchedule $reservationSchedule) { $reservationSchedule->update($this->validated($request, true, $reservationSchedule)); return response()->json($reservationSchedule->fresh('area')); }
    public function destroy(ReservationSchedule $reservationSchedule) { $reservationSchedule->delete(); return response()->json(['message' => 'Horario eliminado.']); }

    private function validated(Request $request, bool $partial = false, ?ReservationSchedule $schedule = null): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'service_area_id' => 'nullable|integer|exists:service_areas,id', 'day_of_week' => "$required|integer|min:0|max:6",
            'opens_at' => "$required|date_format:H:i", 'closes_at' => "$required|date_format:H:i",
            'slot_interval_minutes' => 'sometimes|integer|min:5|max:240', 'reservation_duration_minutes' => 'sometimes|integer|min:15|max:720',
            'cleanup_buffer_minutes' => 'sometimes|integer|min:0|max:240', 'active' => 'sometimes|boolean',
        ]);
        $open = $data['opens_at'] ?? $schedule?->opens_at; $close = $data['closes_at'] ?? $schedule?->closes_at;
        if ($open && $close && $close <= $open) throw ValidationException::withMessages(['closes_at' => 'La hora de cierre debe ser posterior a la apertura.']);
        $area = array_key_exists('service_area_id', $data) ? $data['service_area_id'] : $schedule?->service_area_id;
        $day = $data['day_of_week'] ?? $schedule?->day_of_week;
        if (ReservationSchedule::where('service_area_id', $area)->where('day_of_week', $day)->when($schedule, fn ($q) => $q->where('id', '!=', $schedule->id))->exists()) throw ValidationException::withMessages(['day_of_week' => 'Ya existe un horario para esa área y día.']);
        return $data;
    }
}
