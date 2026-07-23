<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceArea;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceAreaController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate(['search' => 'nullable|string|max:120', 'per_page' => 'nullable|integer|min:1|max:100']);
        $query = ServiceArea::withCount(['tables', 'reservations'])->orderBy('sort_order')->orderBy('name')->orderBy('id');
        if ($search = trim($validated['search'] ?? '')) $query->where('name', 'like', "%{$search}%");
        return response()->json(isset($validated['per_page']) ? $query->paginate($validated['per_page']) : $query->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        return response()->json(ServiceArea::create($data), 201);
    }

    public function show(ServiceArea $serviceArea) { return response()->json($serviceArea->load('tables')); }

    public function update(Request $request, ServiceArea $serviceArea)
    {
        $data = $this->validated($request, true);
        if (isset($data['name']) && $data['name'] !== $serviceArea->name) $data['slug'] = $this->uniqueSlug($data['name'], $serviceArea->id);
        $serviceArea->update($data);
        return response()->json($serviceArea->fresh());
    }

    public function destroy(ServiceArea $serviceArea)
    {
        if ($serviceArea->reservations()->exists() || $serviceArea->tables()->exists()) {
            $serviceArea->update(['active' => false, 'public_visible' => false, 'reservable' => false]);
            return response()->json(['message' => 'El área se desactivó para conservar su historial.', 'area' => $serviceArea->fresh()]);
        }
        $serviceArea->delete();
        return response()->json(['message' => 'Área eliminada.']);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'name' => "$required|string|max:120", 'description' => 'nullable|string|max:1000',
            'image_url' => 'nullable|string|max:500', 'active' => 'sometimes|boolean',
            'public_visible' => 'sometimes|boolean', 'reservable' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:100000',
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignore = null): string
    {
        $base = Str::slug($name) ?: 'area'; $slug = $base; $suffix = 2;
        while (ServiceArea::where('slug', $slug)->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists()) $slug = $base.'-'.$suffix++;
        return $slug;
    }
}

