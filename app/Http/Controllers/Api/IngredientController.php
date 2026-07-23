<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:120',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $query = Ingredient::orderBy('name')->orderBy('id');
        if ($search = trim($validated['search'] ?? '')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
        }

        return response()->json(isset($validated['per_page'])
            ? $query->paginate($validated['per_page'])
            : $query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|unique:ingredients,sku',
            'name' => 'required|string|max:100',
            'unit_of_measure' => 'required|string|max:20',
            'current_stock' => 'prohibited',
            'minimum_stock' => 'nullable|numeric|min:0',
            'cost_per_unit' => 'nullable|numeric|min:0',
        ]);

        $ingredient = Ingredient::create($validated + ['current_stock' => 0]);
        return response()->json($ingredient, 201);
    }

    public function show($id)
    {
        $ingredient = Ingredient::findOrFail($id);
        return response()->json($ingredient);
    }

    public function update(Request $request, $id)
    {
        $ingredient = Ingredient::findOrFail($id);

        $validated = $request->validate([
            'sku' => 'sometimes|string|unique:ingredients,sku,' . $ingredient->id,
            'name' => 'sometimes|string|max:100',
            'unit_of_measure' => 'sometimes|string|max:20',
            'current_stock' => 'prohibited',
            'minimum_stock' => 'sometimes|numeric|min:0',
            'cost_per_unit' => 'sometimes|numeric|min:0',
        ]);

        $ingredient->update($validated);
        return response()->json($ingredient);
    }

    public function destroy($id)
    {
        $ingredient = Ingredient::findOrFail($id);
        $ingredient->delete();
        return response()->json(['message' => 'Ingredient deleted successfully']);
    }
}
