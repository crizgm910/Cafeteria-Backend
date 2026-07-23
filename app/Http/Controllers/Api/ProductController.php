<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:120',
            'category_id' => 'nullable|integer|exists:categories,id',
            'active' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $query = Product::with(['category', 'ingredients:id,name,unit_of_measure'])
            ->orderBy('name')->orderBy('id');
        if ($search = trim($validated['search'] ?? '')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
        }
        foreach (['category_id', 'active'] as $filter) {
            if (array_key_exists($filter, $validated)) $query->where($filter, $validated[$filter]);
        }

        if (isset($validated['per_page'])) {
            $products = $query->paginate($validated['per_page']);
            $products->getCollection()->each->append('is_sellable');
            return response()->json($products);
        }

        return response()->json($query->get()->each->append('is_sellable'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'sku' => 'required|string|unique:products,sku',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'image_url' => 'nullable|string',
            'active' => 'boolean'
        ]);

        if (!isset($validated['active'])) {
            $validated['active'] = true;
        }

        $product = Product::create($validated);
        $product->load(['category', 'ingredients:id,name,unit_of_measure'])->append('is_sellable');

        return response()->json($product, 201);
    }

    public function show($id)
    {
        $product = Product::with(['category', 'ingredients:id,name,unit_of_measure'])->findOrFail($id)
            ->append('is_sellable');
        return response()->json($product);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'sku' => 'sometimes|string|unique:products,sku,' . $product->id,
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'image_url' => 'nullable|string',
            'active' => 'boolean'
        ]);

        $product->update($validated);
        $product->load(['category', 'ingredients:id,name,unit_of_measure'])->append('is_sellable');

        return response()->json($product);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        // Soft delete equivalent by setting active to false
        $product->update(['active' => false]);
        return response()->json(['message' => 'Product deactivated successfully']);
    }
}
