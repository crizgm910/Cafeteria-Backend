<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryTransactionController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'ingredient_id' => 'nullable|integer|exists:ingredients,id',
            'transaction_type' => 'nullable|in:sale,restock,waste,adjustment',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $query = InventoryTransaction::with(['ingredient', 'user:id,name'])->orderByDesc('created_at')->orderByDesc('id');
        foreach (['ingredient_id', 'transaction_type'] as $filter) {
            if (isset($validated[$filter])) $query->where($filter, $validated[$filter]);
        }

        return response()->json(isset($validated['per_page'])
            ? $query->paginate($validated['per_page'])
            : $query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'transaction_type' => 'required|in:sale,restock,waste,adjustment',
            'quantity' => 'required|numeric',
            'reason' => 'required|string|max:120',
            'notes' => 'nullable|string|max:1000',
            'reference_id' => 'nullable|uuid',
        ]);

        $quantity = (float) $validated['quantity'];
        $type = $validated['transaction_type'];

        if ($quantity == 0) {
            return response()->json(['message' => 'La cantidad no puede ser cero.'], 400);
        }

        if (in_array($type, ['sale', 'restock', 'waste']) && $quantity < 0) {
            return response()->json(['message' => 'La cantidad debe ser positiva para este tipo de transacción.'], 400);
        }

        try {
            $result = DB::transaction(function () use ($request, $validated, $quantity, $type) {
                $ingredient = Ingredient::lockForUpdate()->findOrFail($validated['ingredient_id']);
                $stockBefore = (float) $ingredient->current_stock;

                $appliedQuantity = $quantity;
                if (in_array($type, ['sale', 'waste'])) {
                    $appliedQuantity = -$quantity;
                }

                $newStock = $ingredient->current_stock + $appliedQuantity;

                if ($newStock < 0) {
                    throw new \Exception('Stock insuficiente para esta transacción.');
                }

                $transaction = InventoryTransaction::create([
                    'ingredient_id' => $ingredient->id,
                    'transaction_type' => $type,
                    'quantity' => $appliedQuantity,
                    'stock_before_transaction' => $stockBefore,
                    'stock_after_transaction' => $newStock,
                    'user_id' => $request->user()->id,
                    'reason' => $validated['reason'],
                    'notes' => $validated['notes'] ?? null,
                    'reference_id' => $validated['reference_id'] ?? null,
                ]);

                $ingredient->current_stock = $newStock;
                $ingredient->save();

                return [
                    'transaction' => $transaction,
                    'ingredient' => $ingredient
                ];
            });

            return response()->json($result, 201);

        } catch (\Exception $e) {
            $status = $e->getMessage() === 'Stock insuficiente para esta transacción.' ? 400 : 500;
            return response()->json(['message' => 'Error al procesar la transacción', 'error' => $e->getMessage()], $status);
        }
    }
}
