<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category')->get();
        return response()->json($products);
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
        $product->load('category');

        return response()->json($product, 201);
    }

    public function show($id)
    {
        $product = Product::with('category')->findOrFail($id);
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
        $product->load('category');

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
