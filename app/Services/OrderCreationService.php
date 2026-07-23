<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketItem;
use DomainException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class OrderCreationService
{
    public function __construct(private readonly AddOnConfigurationResolver $addOns) {}

    public function create(array $data, array $context): Ticket
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TGR-'.strtoupper(Str::random(6)),
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'status' => $context['ticket_status'],
            'source' => $context['source'],
            'order_type' => $data['order_type'],
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'idempotency_key' => $context['idempotency_key'],
            'request_fingerprint' => $context['request_fingerprint'],
            'tracking_token' => $context['tracking_token'] ?? null,
        ]);

        $ticket->activities()->create([
            'action' => 'Pedido recibido en sistema',
            'author' => $context['author'],
            'user_id' => $context['actor_id'] ?? null,
        ]);

        $totalAmount = 0;
        foreach ($data['items'] as $item) {
            $product = Product::where('active', true)->find($item['product_id']);
            if (! $product) {
                throw new DomainException('El producto solicitado no existe o está inactivo.');
            }
            if ($product->ingredients->isEmpty()) {
                throw new DomainException('El producto no tiene una receta activa y no está disponible para venta.');
            }

            $quantity = (int) $item['quantity'];
            $subtotal = (float) $product->price * $quantity;
            $ticketItem = TicketItem::create([
                'ticket_id' => $ticket->id,
                'product_id' => $product->id,
                'kitchen_station_id' => $product->kitchen_station_id,
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'subtotal' => $subtotal,
                'notes' => $item['notes'] ?? null,
                'kds_status' => 'pending',
            ]);

            foreach ($product->ingredients as $recipeIngredient) {
                $this->deductIngredient(
                    $recipeIngredient->id,
                    (float) $recipeIngredient->pivot->quantity_required * $quantity,
                    $ticket->id,
                    "Inventario insuficiente para el producto: {$product->name}"
                );
            }

            if (! empty($item['add_ons'])) {
                $allowed = $this->addOns->allowed($product);
                foreach ($item['add_ons'] as $addOnId) {
                    $configuration = $allowed->get((int) $addOnId);
                    if (! $configuration) {
                        throw new DomainException('El complemento solicitado no pertenece a este producto.');
                    }

                    $subtotal += (float) $configuration['effective_price'] * $quantity;
                    $ticketItem->addOns()->attach($configuration['id'], [
                        'name_snapshot' => $configuration['name'],
                        'price_charged' => $configuration['effective_price'],
                    ]);

                    foreach ($configuration['recipe'] as $recipe) {
                        $consumed = (float) $recipe->quantity_required * $quantity;
                        $this->deductIngredient(
                            (int) $recipe->ingredient_id,
                            $consumed,
                            $ticket->id,
                            "Inventario insuficiente para el complemento: {$configuration['name']}"
                        );
                        DB::table('ticket_item_add_on_consumptions')->insert([
                            'ticket_item_id' => $ticketItem->id,
                            'add_on_id' => $configuration['id'],
                            'ingredient_id' => $recipe->ingredient_id,
                            'quantity_consumed' => $consumed,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
                $ticketItem->update(['subtotal' => $subtotal]);
            }

            $totalAmount += $subtotal;
        }

        $ticket->update([
            'subtotal' => $totalAmount,
            'total' => $totalAmount,
        ]);

        $amountReceived = $context['amount_received'] ?? null;
        if ($context['payment_provider'] === 'cash' && $context['payment_status'] === 'approved') {
            if ($amountReceived === null || (float) $amountReceived < $totalAmount) {
                throw new DomainException('El efectivo recibido es menor al total de la venta.');
            }
        }

        $ticket->payments()->create([
            'amount' => $totalAmount,
            'amount_received' => $amountReceived,
            'change_amount' => $amountReceived === null ? null : max(0, (float) $amountReceived - $totalAmount),
            'gateway_provider' => $context['payment_provider'],
            'transaction_reference' => $context['transaction_reference'] ?? null,
            'status' => $context['payment_status'],
            'evidence_type' => $context['evidence_type'],
            'paid_at' => $context['payment_status'] === 'approved' ? now() : null,
            'confirmed_by' => $context['confirmed_by'] ?? null,
        ]);

        return $ticket->fresh(['payments']);
    }

    private function deductIngredient(int $ingredientId, float $quantity, string $ticketId, string $message): void
    {
        $ingredient = Ingredient::whereKey($ingredientId)->lockForUpdate()->firstOrFail();
        if ((float) $ingredient->current_stock < $quantity) {
            throw new DomainException($message);
        }

        $ingredient->decrement('current_stock', $quantity);
        $ingredient->refresh();
        $ingredient->inventoryTransactions()->create([
            'transaction_type' => 'sale',
            'quantity' => -$quantity,
            'reference_id' => $ticketId,
            'stock_after_transaction' => $ingredient->current_stock,
        ]);
    }
}
