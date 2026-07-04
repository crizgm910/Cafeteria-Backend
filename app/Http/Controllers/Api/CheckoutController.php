<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\AddOn;
use App\Models\Ingredient;
use App\Models\Ticket;
use App\Models\TicketItem;

class CheckoutController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validar la entrada (payload del Kiosko)
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string|max:500',
            'items.*.add_ons' => 'nullable|array',
            'items.*.add_ons.*' => 'exists:add_ons,id',
            'payment_method' => 'required|string',
        ]);

        try {
            // 2. Iniciar Transacción ACID Segura
            DB::beginTransaction();

            // Variables para acumular totales
            $totalAmount = 0;
            
            // Creamos el Ticket en la base de datos (Status 'pending' por defecto)
            $ticket = Ticket::create([
                'total_amount' => 0, // Se actualizará al final
                'status' => 'pending',
                'order_type' => 'dine_in', // O 'takeout', esto puede venir del request
            ]);

            foreach ($validated['items'] as $item) {
                // Recuperar el producto para el precio base y su estación
                $product = Product::findOrFail($item['product_id']);
                $quantity = $item['quantity'];
                
                // Calcular subtotal del producto base
                $subtotal = $product->price * $quantity;

                // Crear el Ticket Item
                $ticketItem = TicketItem::create([
                    'ticket_id' => $ticket->id,
                    'product_id' => $product->id,
                    'kitchen_station_id' => $product->kitchen_station_id,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'subtotal' => $subtotal,
                    'notes' => $item['notes'] ?? null,
                    'kds_status' => 'pending'
                ]);

                // Descontar inventario de la receta del producto con BLOQUEO PESIMISTA
                foreach ($product->ingredients as $recipeIngredient) {
                    $qtyToDeduct = $recipeIngredient->pivot->quantity_required * $quantity;
                    
                    // Bloqueo pesimista: Evita que otra venta modifique este ingrediente al mismo tiempo
                    $ingredient = Ingredient::where('id', $recipeIngredient->id)->lockForUpdate()->first();
                    
                    if ($ingredient->current_stock < $qtyToDeduct) {
                        throw new \Exception("Inventario insuficiente para el producto: " . $product->name);
                    }
                    $ingredient->decrement('current_stock', $qtyToDeduct);
                    
                    // Registro de auditoría (Transaction)
                    $ingredient->inventoryTransactions()->create([
                        'type' => 'sale',
                        'quantity' => -$qtyToDeduct,
                        'reason' => 'Venta en ticket ' . $ticket->id
                    ]);
                }

                // Procesar Complementos (Add-Ons) si los hay
                if (!empty($item['add_ons'])) {
                    foreach ($item['add_ons'] as $addOnId) {
                        $addOn = AddOn::findOrFail($addOnId);
                        
                        // Añadir costo del complemento (multiplicado por la cantidad del platillo)
                        $addOnTotalCost = $addOn->price_adjustment * $quantity;
                        $subtotal += $addOnTotalCost;
                        
                        // Guardar en ticket_item_add_ons
                        $ticketItem->addOns()->attach($addOn->id, ['price_charged' => $addOn->price_adjustment]);

                        // Descontar inventario del complemento si aplica
                        if ($addOn->ingredient_id && $addOn->quantity_required > 0) {
                            $qtyToDeductAddOn = $addOn->quantity_required * $quantity;
                            $ingredientAddOn = Ingredient::where('id', $addOn->ingredient_id)->lockForUpdate()->first();
                            
                            if ($ingredientAddOn->current_stock < $qtyToDeductAddOn) {
                                throw new \Exception("Inventario insuficiente para el complemento: " . $addOn->name);
                            }
                            $ingredientAddOn->decrement('current_stock', $qtyToDeductAddOn);
                            
                            $ingredientAddOn->inventoryTransactions()->create([
                                'type' => 'sale',
                                'quantity' => -$qtyToDeductAddOn,
                                'reason' => 'Complemento en ticket ' . $ticket->id
                            ]);
                        }
                    }
                }
                
                // Actualizar el subtotal final del item si tuvo complementos
                if (!empty($item['add_ons'])) {
                   $ticketItem->update(['subtotal' => $subtotal]);
                }

                $totalAmount += $subtotal;
            }

            // Actualizar el Ticket con el total final
            $ticket->update(['total_amount' => $totalAmount]);

            // Guardar Pago
            $ticket->payments()->create([
                'amount' => $totalAmount,
                'method' => $validated['payment_method'],
                'status' => 'completed'
            ]);

            // 3. Todo salió bien, confirmar la transacción
            DB::commit();

            return response()->json([
                'message' => 'Checkout completado con éxito',
                'ticket_id' => $ticket->id,
                'total' => $totalAmount
            ], 201);

        } catch (\Exception $e) {
            // 4. Hubo un error (ej. falta inventario), revertir ABSOLUTAMENTE TODO
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,paid,preparing,ready,delivered,cancelled',
        ]);

        $ticket = Ticket::where('id', $id)->orWhere('ticket_number', $id)->firstOrFail();
        $ticket->status = $validated['status'];
        $ticket->save();

        return response()->json([
            'message' => 'Estado del pedido actualizado a: ' . $ticket->status,
            'ticket' => $ticket
        ]);
    }
}
