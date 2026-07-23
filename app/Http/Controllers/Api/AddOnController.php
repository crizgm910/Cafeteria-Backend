<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AddOn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AddOnController extends Controller
{
    public function index()
    {
        return response()->json(AddOn::with(['ingredient', 'recipeItems.ingredient'])
            ->withCount(['products', 'categories', 'ticketItems'])
            ->orderBy('sort_order')->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $addOn = DB::transaction(function () use ($validated) {
            $recipe = $validated['recipe'] ?? [];
            unset($validated['recipe']);
            $validated += [
                'ingredient_id' => null,
                'quantity_required' => 0,
                'active' => true,
                'public_visible' => true,
                'sort_order' => 0,
            ];
            $addOn = AddOn::create($validated);
            $this->syncRecipe($addOn, $recipe);
            return $addOn;
        });

        return response()->json($this->detail($addOn), 201);
    }

    public function show(AddOn $addOn)
    {
        return response()->json($addOn->load(['ingredient', 'recipeItems.ingredient', 'products:id,name']));
    }

    public function update(Request $request, AddOn $addOn)
    {
        $validated = $this->validated($request, true);
        DB::transaction(function () use ($addOn, $validated): void {
            $hasRecipe = array_key_exists('recipe', $validated);
            $recipe = $validated['recipe'] ?? [];
            unset($validated['recipe']);
            $addOn->update($validated);
            if ($hasRecipe) $this->syncRecipe($addOn, $recipe);
        });

        return response()->json($this->detail($addOn->fresh()));
    }

    public function destroy(AddOn $addOn)
    {
        $addOn->update(['active' => false]);

        return response()->json(['message' => 'Complemento desactivado correctamente.']);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes|' : 'required|';
        $nameRules = [$partial ? 'sometimes' : 'required', 'string', 'max:100', Rule::unique('add_ons', 'name')->ignore($request->route('add_on'))];

        return $request->validate([
            'name' => $nameRules,
            'description' => 'nullable|string|max:500',
            'price_adjustment' => $sometimes.'numeric|min:0',
            'ingredient_id' => 'nullable|exists:ingredients,id',
            'quantity_required' => 'sometimes|numeric|min:0',
            'active' => 'sometimes|boolean',
            'public_visible' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:9999',
            'recipe' => 'sometimes|array',
            'recipe.*.ingredient_id' => 'required|integer|distinct|exists:ingredients,id',
            'recipe.*.quantity_required' => 'required|numeric|gt:0',
        ]);
    }

    private function syncRecipe(AddOn $addOn, array $recipe): void
    {
        $addOn->recipeItems()->delete();
        if ($recipe) $addOn->recipeItems()->createMany($recipe);
    }

    private function detail(AddOn $addOn): AddOn
    {
        return $addOn->load(['ingredient', 'recipeItems.ingredient'])
            ->loadCount(['products', 'categories', 'ticketItems']);
    }
}
