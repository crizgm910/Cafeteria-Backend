<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryTransactionController extends Controller
{
    public function index()
    {
        $transactions = InventoryTransaction::with('ingredient')->orderBy('created_at', 'desc')->get();
        return response()->json($transactions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'transaction_type' => 'required|in:sale,restock,waste,adjustment',
            'quantity' => 'required|numeric',
        ]);

        try {
            DB::beginTransaction();

            $ingredient = Ingredient::lockForUpdate()->findOrFail($validated['ingredient_id']);
            
            $quantity = $validated['quantity'];
            
            // Adjust stock based on transaction type
            if (in_array($validated['transaction_type'], ['sale', 'waste'])) {
                $quantity = -$quantity; // Subtract from stock
            } else if ($validated['transaction_type'] === 'adjustment') {
                // If it's an adjustment, the quantity provided could be interpreted as absolute or relative.
                // We'll treat adjustment as a positive/negative relative amount, but our validation said min: 0.01.
                // For simplicity, let's treat "adjustment" as overriding the stock or just pass the exact amount as +/-.
                // In this implementation, let's allow negative numbers in validation if adjustment? 
                // Wait, if it's an adjustment, we might need to know if it's a positive or negative adjustment.
                // Let's stick to standard behavior: we assume the frontend sends the *difference* and we just add it, 
                // BUT the validation says min 0.01. Let's just remove the min:0.01 for adjustment, or handle it manually.
                // Let's change the validation rule.
            }
            // Actually, if it's 'adjustment', let's say it can be negative. We will validate that below.

            $newStock = $ingredient->current_stock + $quantity;

            if ($newStock < 0) {
                return response()->json(['message' => 'Insufficient stock for this transaction.'], 400);
            }

            $transaction = InventoryTransaction::create([
                'ingredient_id' => $ingredient->id,
                'transaction_type' => $validated['transaction_type'],
                'quantity' => $quantity,
                'stock_after_transaction' => $newStock,
            ]);

            $ingredient->current_stock = $newStock;
            $ingredient->save();

            DB::commit();

            return response()->json([
                'transaction' => $transaction,
                'ingredient' => $ingredient
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to process transaction', 'error' => $e->getMessage()], 500);
        }
    }
}
