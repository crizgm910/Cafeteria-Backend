<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceArea;
use App\Services\ReservationAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ReservationAvailabilityController extends Controller
{
    public function __invoke(Request $request, ReservationAvailabilityService $availability)
    {
        $v = $request->validate(['date' => 'required|date_format:Y-m-d|after_or_equal:today', 'guests' => 'required|integer|min:1|max:20', 'service_area_id' => 'nullable|integer|exists:service_areas,id']);
        $date = CarbonImmutable::createFromFormat('Y-m-d', $v['date'], config('app.timezone'))->startOfDay();
        $areas = ServiceArea::where('active', true)->where('public_visible', true)->where('reservable', true)
            ->when($v['service_area_id'] ?? null, fn ($q, $id) => $q->whereKey($id))->orderBy('sort_order')->orderBy('name')->get();
        $slots = $availability->slotsForAreas($areas, $date, (int) $v['guests']);
        $areas = $areas->map(fn (ServiceArea $area) => ['id' => $area->id, 'name' => $area->name, 'description' => $area->description, 'image_url' => $area->image_url, 'available_slots' => $slots[$area->id] ?? []])
            ->filter(fn (array $area) => $area['available_slots'] !== [])->values();

        return response()->json(['date' => $v['date'], 'guests' => (int) $v['guests'], 'areas' => $areas]);
    }
}
