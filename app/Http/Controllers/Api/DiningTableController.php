<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DiningTableController extends Controller
{
    public function index(Request $request)
    {
        $v = $request->validate(['service_area_id' => 'nullable|integer|exists:service_areas,id', 'status' => 'nullable|in:available,reserved,occupied,cleaning,blocked', 'per_page' => 'nullable|integer|min:1|max:100']);
        $q = DiningTable::with('area')->withCount('reservations')->orderBy('service_area_id')->orderBy('sort_order')->orderBy('code');
        foreach (['service_area_id', 'status'] as $f) if (isset($v[$f])) $q->where($f, $v[$f]);
        return response()->json(isset($v['per_page']) ? $q->paginate($v['per_page']) : $q->get());
    }

    public function store(Request $request) { return response()->json(DiningTable::create($this->validated($request)), 201); }
    public function show(DiningTable $diningTable) { return response()->json($diningTable->load('area')); }
    public function update(Request $request, DiningTable $diningTable) { $diningTable->update($this->validated($request, true, $diningTable)); return response()->json($diningTable->fresh('area')); }
    public function destroy(DiningTable $diningTable)
    {
        if ($diningTable->reservations()->exists()) {
            $diningTable->update(['active' => false, 'reservable' => false, 'status' => 'blocked']);
            return response()->json(['message' => 'La mesa se desactivó para conservar su historial.', 'table' => $diningTable->fresh()]);
        }
        $diningTable->delete(); return response()->json(['message' => 'Mesa eliminada.']);
    }

    private function validated(Request $request, bool $partial = false, ?DiningTable $table = null): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'service_area_id' => "$required|integer|exists:service_areas,id",
            'code' => ["$required", 'string', 'max:40', Rule::unique('dining_tables')->where(fn ($q) => $q->where('service_area_id', $request->input('service_area_id', $table?->service_area_id)))->ignore($table?->id)],
            'name' => 'nullable|string|max:120', 'min_capacity' => 'sometimes|integer|min:1|max:100',
            'max_capacity' => "$required|integer|min:1|max:100", 'status' => 'sometimes|in:available,reserved,occupied,cleaning,blocked',
            'active' => 'sometimes|boolean', 'reservable' => 'sometimes|boolean', 'sort_order' => 'sometimes|integer|min:0|max:100000',
            'lock_version' => 'sometimes|integer|min:0',
        ]);
        $min = $data['min_capacity'] ?? $table?->min_capacity ?? 1; $max = $data['max_capacity'] ?? $table?->max_capacity;
        if ($max !== null && $max < $min) throw ValidationException::withMessages(['max_capacity' => 'La capacidad máxima no puede ser menor que la mínima.']);
        if ($table && isset($data['lock_version']) && $data['lock_version'] !== $table->lock_version) throw ValidationException::withMessages(['lock_version' => 'La mesa fue modificada por otra sesión. Actualiza la vista.']);
        if ($table) $data['lock_version'] = $table->lock_version + 1;
        return $data;
    }
}

