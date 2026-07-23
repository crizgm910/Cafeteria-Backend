<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use App\Models\Ticket;
use App\Services\OrderCreationService;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PosSaleController extends Controller
{
    public function store(Request $request, OrderCreationService $orders)
    {
        $request->merge([
            'idempotency_key' => trim((string) $request->header('Idempotency-Key')),
        ]);

        $validated = $request->validate([
            'idempotency_key' => 'required|string|min:16|max:100',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'items.*.notes' => 'nullable|string|max:500',
            'items.*.add_ons' => 'nullable|array',
            'items.*.add_ons.*' => 'integer|distinct|exists:add_ons,id',
            'payment_method' => 'required|in:cash,card_terminal',
            'amount_received' => 'required_if:payment_method,cash|nullable|numeric|min:0',
            'transaction_reference' => 'required_if:payment_method,card_terminal|nullable|string|max:255',
            'customer_name' => 'nullable|string|max:100',
            'customer_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{7,30}$/'],
            'customer_email' => 'nullable|email:rfc|max:255',
            'order_type' => 'required|in:takeout,dine_in',
        ]);

        $fingerprint = hash('sha256', json_encode($validated, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
        $key = $validated['idempotency_key'];

        if ($existing = Ticket::with('payments')->where('idempotency_key', $key)->first()) {
            return $this->response($existing, $fingerprint, true);
        }

        try {
            $ticket = DB::transaction(function () use ($request, $validated, $fingerprint, $key, $orders) {
                $session = CashRegisterSession::where('open_user_id', $request->user()->id)
                    ->lockForUpdate()
                    ->first();

                if (! $session) {
                    throw new DomainException('Debes abrir un turno de caja antes de vender.');
                }

                $ticket = $orders->create($validated, [
                    'ticket_status' => 'paid',
                    'source' => 'admin_panel',
                    'idempotency_key' => $key,
                    'request_fingerprint' => $fingerprint,
                    'author' => $request->user()->name,
                    'actor_id' => $request->user()->id,
                    'payment_provider' => $validated['payment_method'],
                    'payment_status' => 'approved',
                    'evidence_type' => $validated['payment_method'] === 'cash'
                        ? 'cashier_confirmation'
                        : 'external_terminal_manual',
                    'amount_received' => $validated['amount_received'] ?? null,
                    'transaction_reference' => $validated['transaction_reference'] ?? null,
                    'confirmed_by' => $request->user()->id,
                ]);

                if ($validated['payment_method'] === 'cash') {
                    $session->movements()->create([
                        'user_id' => $request->user()->id,
                        'ticket_id' => $ticket->id,
                        'type' => 'sale',
                        'amount' => $ticket->total,
                        'note' => "Venta {$ticket->ticket_number}",
                    ]);
                }

                return $ticket;
            });

            return $this->response($ticket, $fingerprint, false);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Ticket::with('payments')->where('idempotency_key', $key)->first();
            if ($existing) {
                return $this->response($existing, $fingerprint, true);
            }
            report($exception);
            return response()->json(['message' => 'No fue posible completar la venta.'], 500);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'POS_SALE_REJECTED',
            ], 422);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'No fue posible completar la venta.'], 500);
        }
    }

    private function response(Ticket $ticket, string $fingerprint, bool $replayed)
    {
        if (! hash_equals((string) $ticket->request_fingerprint, $fingerprint)) {
            return response()->json([
                'message' => 'La clave de idempotencia ya fue utilizada con otra venta.',
                'code' => 'IDEMPOTENCY_CONFLICT',
            ], 409);
        }

        $ticket->loadMissing('payments');
        $payment = $ticket->payments->first();

        return response()->json([
            'message' => $replayed ? 'Venta recuperada sin duplicarla.' : 'Venta registrada correctamente.',
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'total' => (float) $ticket->total,
            'payment' => $payment,
            'idempotent_replay' => $replayed,
        ], $replayed ? 200 : 201);
    }
}
