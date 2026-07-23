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
use App\Models\InventoryTransaction;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use Illuminate\Support\Str;
use Illuminate\Database\UniqueConstraintViolationException;
use DomainException;
use Throwable;
use App\Services\AddOnConfigurationResolver;

class CheckoutController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => 'nullable|in:pending,paid,preparing,ready,delivered,cancelled',
            'search' => 'nullable|string|max:120',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $query = Ticket::with(['items.product', 'items.addOns', 'activities', 'payments']);
        
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if ($search = trim($validated['search'] ?? '')) {
            $query->where(fn ($q) => $q->where('ticket_number', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%"));
        }

        $query->orderByDesc('created_at')->orderByDesc('id');
        return response()->json(isset($validated['per_page'])
            ? $query->paginate($validated['per_page'])
            : $query->get());
    }

    public function store(Request $request, AddOnConfigurationResolver $addOnResolver)
    {
        $request->merge([
            'idempotency_key' => trim((string) $request->header('Idempotency-Key')),
        ]);

        $validated = $request->validate([
            'idempotency_key' => 'required|string|min:16|max:100',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string|max:500',
            'items.*.add_ons' => 'nullable|array',
            'items.*.add_ons.*' => 'exists:add_ons,id|distinct',
            'payment_method' => 'required|string|in:pay_at_pickup',
            'customer_name' => 'required|string|max:100',
            'customer_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{7,30}$/'],
            'customer_email' => 'nullable|email:rfc|max:255',
            'order_type' => 'required|in:takeout,dine_in',
        ]);

        $idempotencyKey = $validated['idempotency_key'];
        $requestFingerprint = hash('sha256', json_encode([
            'items' => $validated['items'],
            'payment_method' => $validated['payment_method'],
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'customer_email' => $validated['customer_email'] ?? null,
            'order_type' => $validated['order_type'],
        ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

        if ($existing = Ticket::where('idempotency_key', $idempotencyKey)->first()) {
            return $this->idempotentResponse($existing, $requestFingerprint, true);
        }

        try {
            $ticket = DB::transaction(function () use ($validated, $idempotencyKey, $requestFingerprint, $addOnResolver) {
            // Variables para acumular totales
            $totalAmount = 0;
            
            // Creamos el Ticket en la base de datos (Status 'pending' por defecto)
            $ticket = Ticket::create([
                'ticket_number' => 'TGR-' . strtoupper(Str::random(6)),
                'total' => 0, // Se actualizará al final
                'status' => 'pending',
                'source' => 'public_web',
                'order_type' => $validated['order_type'],
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'customer_email' => $validated['customer_email'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $requestFingerprint,
                'tracking_token' => Str::random(48),
            ]);

            // Activity Log inicial
            $ticket->activities()->create([
                'action' => 'Pedido recibido en sistema',
                'author' => 'System',
                'user_id' => null,
            ]);

            foreach ($validated['items'] as $item) {
                // Recuperar el producto para el precio base y su estación asegurando que esté activo
                $product = Product::where('active', 1)->find($item['product_id']);

                if (!$product) {
                    throw new DomainException("El producto solicitado no existe o está inactivo.");
                }

                if ($product->ingredients->isEmpty()) {
                    throw new DomainException("El producto no tiene una receta activa y no está disponible para venta.");
                }

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
                        throw new DomainException("Inventario insuficiente para el producto: " . $product->name);
                    }
                    $ingredient->decrement('current_stock', $qtyToDeduct);
                    
                    // Registro de auditoría (Transaction)
                    $ingredient->inventoryTransactions()->create([
                        'transaction_type' => 'sale',
                        'quantity' => -$qtyToDeduct,
                        'reference_id' => $ticket->id,
                        'stock_after_transaction' => $ingredient->current_stock
                    ]);
                }

                // Procesar Complementos (Add-Ons) si los hay
                if (!empty($item['add_ons'])) {
                    $allowedAddOns = $addOnResolver->allowed($product);

                    foreach ($item['add_ons'] as $addOnId) {
                        $configuration = $allowedAddOns->get((int) $addOnId);
                        if (!$configuration) {
                            throw new DomainException("El complemento solicitado no pertenece a este producto.");
                        }

                        $addOnTotalCost = $configuration['effective_price'] * $quantity;
                        $subtotal += $addOnTotalCost;
                        $ticketItem->addOns()->attach($configuration['id'], [
                            'name_snapshot' => $configuration['name'],
                            'price_charged' => $configuration['effective_price'],
                        ]);

                        foreach ($configuration['recipe'] as $recipe) {
                            $qtyToDeductAddOn = $recipe->quantity_required * $quantity;
                            $ingredientAddOn = Ingredient::where('id', $recipe->ingredient_id)->lockForUpdate()->first();
                            if ($ingredientAddOn->current_stock < $qtyToDeductAddOn) {
                                throw new DomainException("Inventario insuficiente para el complemento: " . $configuration['name']);
                            }
                            $ingredientAddOn->decrement('current_stock', $qtyToDeductAddOn);
                            $ingredientAddOn->inventoryTransactions()->create([
                                'transaction_type' => 'sale',
                                'quantity' => -$qtyToDeductAddOn,
                                'reference_id' => $ticket->id,
                                'stock_after_transaction' => $ingredientAddOn->current_stock
                            ]);
                            DB::table('ticket_item_add_on_consumptions')->insert([
                                'ticket_item_id' => $ticketItem->id,
                                'add_on_id' => $configuration['id'],
                                'ingredient_id' => $recipe->ingredient_id,
                                'quantity_consumed' => $qtyToDeductAddOn,
                                'created_at' => now(), 'updated_at' => now(),
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
            $ticket->update([
                'subtotal' => $totalAmount,
                'tax' => 0,
                'discount' => 0,
                'total' => $totalAmount,
            ]);

            // En el canal público el pago se confirma posteriormente en caja.
            $ticket->payments()->create([
                'amount' => $totalAmount,
                'gateway_provider' => 'pay_at_pickup',
                'status' => 'pending',
                'evidence_type' => 'awaiting_collection',
            ]);

            return $ticket->fresh();
            });

            return $this->idempotentResponse($ticket, $requestFingerprint, false);
        } catch (UniqueConstraintViolationException $e) {
            $existing = Ticket::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $this->idempotentResponse($existing, $requestFingerprint, true);
            }

            report($e);
            return response()->json(['error' => 'No fue posible completar el pedido.'], 500);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => 'No fue posible completar el checkout.'], 500);
        }
    }

    private function idempotentResponse(Ticket $ticket, string $requestFingerprint, bool $replayed)
    {
        if (! hash_equals((string) $ticket->request_fingerprint, $requestFingerprint)) {
            return response()->json([
                'message' => 'La clave de idempotencia ya fue utilizada con otro pedido.',
                'code' => 'IDEMPOTENCY_CONFLICT',
            ], 409);
        }

        $paymentStatus = $ticket->payments()->value('status') ?? 'pending';

        return response()->json([
            'message' => $replayed ? 'Pedido recuperado sin duplicarlo.' : 'Pedido registrado. Paga al recoger.',
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subtotal' => (float) $ticket->subtotal,
            'tax' => (float) $ticket->tax,
            'discount' => (float) $ticket->discount,
            'total' => (float) $ticket->total,
            'payment_status' => $paymentStatus,
            'payment_message' => $paymentStatus === 'approved'
                ? 'Pago registrado en caja.'
                : 'Pago pendiente al recoger.',
            'idempotent_replay' => $replayed,
            'tracking_token' => $ticket->tracking_token,
        ], $replayed ? 200 : 201);
    }

    public function publicStatus(Request $request, string $ticketNumber)
    {
        $validated = $request->validate([
            'token' => 'required|string|min:32|max:100',
        ]);

        $ticket = Ticket::where('ticket_number', $ticketNumber)->first();
        if (! $ticket || ! $ticket->tracking_token
            || ! hash_equals($ticket->tracking_token, $validated['token'])) {
            return response()->json([
                'message' => 'No se encontró un pedido con esas credenciales.',
                'code' => 'ORDER_NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status,
            'order_type' => $ticket->order_type,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,paid,preparing,ready,delivered,cancelled',
            'refund_reference' => 'nullable|string|max:255',
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $requiredPermission = $validated['status'] === 'cancelled' ? 'tickets.cancel' : 'tickets.update';
        if (! $request->user()?->hasPermission($requiredPermission)) {
            return response()->json([
                'message' => 'No tienes permiso para realizar esta transición.',
                'code' => 'FORBIDDEN',
            ], 403);
        }
        if ($validated['status'] === 'cancelled' && empty(trim($validated['cancellation_reason'] ?? ''))) {
            return response()->json([
                'message' => 'Debes indicar el motivo de la cancelación.',
                'errors' => ['cancellation_reason' => ['El motivo de cancelación es obligatorio.']],
            ], 422);
        }

        try {
            $ticket = DB::transaction(function () use ($request, $id, $validated) {
            $ticket = Ticket::where(function ($query) use ($id) {
                $query->where('id', $id)->orWhere('ticket_number', $id);
            })->lockForUpdate()->firstOrFail();

            $transitions = [
                'pending' => ['paid', 'preparing', 'cancelled'],
                'paid' => ['preparing', 'cancelled'],
                'preparing' => ['pending', 'ready', 'cancelled'],
                'ready' => ['preparing', 'delivered', 'cancelled'],
                'delivered' => [],
                'cancelled' => [],
            ];

            if (!in_array($validated['status'], $transitions[$ticket->status] ?? [], true)) {
                throw new DomainException("Transición inválida de {$ticket->status} a {$validated['status']}.");
            }
            if ($validated['status'] === 'preparing' && $ticket->payments()->where('status', 'pending')->exists()) {
                throw new DomainException('El pedido debe cobrarse antes de enviarlo a preparación.');
            }

            if ($validated['status'] === 'cancelled') {
                $sales = InventoryTransaction::where('reference_id', $ticket->id)
                    ->where('transaction_type', 'sale')
                    ->selectRaw('ingredient_id, SUM(quantity) as quantity_sold')
                    ->groupBy('ingredient_id')
                    ->get();

                foreach ($sales as $sale) {
                    $ingredient = Ingredient::lockForUpdate()->findOrFail($sale->ingredient_id);
                    $quantityToRestore = abs((float) $sale->quantity_sold);
                    $ingredient->increment('current_stock', $quantityToRestore);
                    $ingredient->refresh();
                    $ingredient->inventoryTransactions()->create([
                        'transaction_type' => 'adjustment',
                        'quantity' => $quantityToRestore,
                        'reference_id' => $ticket->id,
                        'stock_after_transaction' => $ingredient->current_stock,
                    ]);
                }

                foreach ($ticket->payments()->lockForUpdate()->get() as $payment) {
                    if ($payment->status === 'pending') {
                        $payment->update(['status' => 'cancelled']);
                        continue;
                    }
                    if ($payment->status !== 'approved') continue;

                    if ($payment->gateway_provider === 'cash' && $payment->confirmed_by) {
                        $saleMovement = CashMovement::where('ticket_id', $ticket->id)
                            ->where('type', 'sale')
                            ->lockForUpdate()
                            ->first();
                        if (! $saleMovement) {
                            throw new DomainException('La venta en efectivo no tiene un movimiento de caja conciliable.');
                        }
                        $refundSession = CashRegisterSession::where('open_user_id', $request->user()->id)
                            ->lockForUpdate()
                            ->first();
                        if (! $refundSession) {
                            throw new DomainException('Debes abrir un turno de caja para registrar la devolución en efectivo.');
                        }
                        $refundSession->movements()->create([
                            'user_id' => $request->user()->id,
                            'ticket_id' => $ticket->id,
                            'type' => 'refund',
                            'amount' => -1 * (float) $payment->amount,
                            'note' => "Devolución {$ticket->ticket_number}",
                        ]);
                    }

                    if ($payment->gateway_provider === 'card_terminal' && empty($validated['refund_reference'])) {
                        throw new DomainException('Registra la referencia del reembolso emitido por la terminal.');
                    }

                    $payment->update([
                        'status' => 'refunded',
                        'refund_reference' => $validated['refund_reference'] ?? null,
                        'refunded_at' => now(),
                    ]);
                }
            }

            $ticket->update([
                'status' => $validated['status'],
                'cancellation_reason' => $validated['status'] === 'cancelled'
                    ? trim($validated['cancellation_reason'])
                    : $ticket->cancellation_reason,
            ]);
            if ($validated['status'] === 'pending') {
                $ticket->items()->update([
                    'kds_status' => 'pending',
                    'kds_started_at' => null,
                    'kds_completed_at' => null,
                ]);
            } elseif ($validated['status'] === 'preparing') {
                $ticket->items()->update([
                    'kds_status' => 'preparing',
                    'kds_started_at' => now(),
                    'kds_completed_at' => null,
                ]);
            } elseif ($validated['status'] === 'ready') {
                $ticket->items()->whereNull('kds_started_at')->update(['kds_started_at' => now()]);
                $ticket->items()->update([
                    'kds_status' => 'ready',
                    'kds_completed_at' => now(),
                ]);
            }
            $ticket->activities()->create([
                'action' => 'Estado actualizado a ' . strtoupper($validated['status'])
                    . ($validated['status'] === 'cancelled' ? ': '.trim($validated['cancellation_reason']) : ''),
                'author' => $request->user()?->name ?? 'Barista',
                'user_id' => $request->user()?->id,
            ]);

            return $ticket->fresh();
            });
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Estado del pedido actualizado a: ' . $ticket->status,
            'ticket' => $ticket
        ]);
    }
}
