<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use App\Models\Payment;
use App\Models\Ticket;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentCollectionController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        $request->merge(['idempotency_key' => trim((string) $request->header('Idempotency-Key'))]);
        $validated = $request->validate([
            'idempotency_key' => 'required|string|min:16|max:100',
            'payment_method' => 'required|in:cash,card_terminal',
            'amount_received' => 'required_if:payment_method,cash|nullable|numeric|min:0',
            'transaction_reference' => 'required_if:payment_method,card_terminal|nullable|string|max:255',
        ]);
        $fingerprint = hash('sha256', json_encode([
            'payment_method' => $validated['payment_method'],
            'amount_received' => $validated['amount_received'] ?? null,
            'transaction_reference' => $validated['transaction_reference'] ?? null,
        ], JSON_PRESERVE_ZERO_FRACTION));

        try {
            $payment = DB::transaction(function () use ($request, $ticket, $validated, $fingerprint) {
                $lockedTicket = Ticket::lockForUpdate()->findOrFail($ticket->id);
                $payment = $lockedTicket->payments()->lockForUpdate()->firstOrFail();

                if ($payment->status === 'approved') {
                    if ($payment->collection_idempotency_key === $validated['idempotency_key']
                        && hash_equals((string) $payment->collection_request_fingerprint, $fingerprint)) {
                        return $payment;
                    }
                    throw new DomainException('El pedido ya tiene un cobro confirmado.');
                }
                if ($payment->status !== 'pending' || $lockedTicket->status === 'cancelled') {
                    throw new DomainException('El pago ya no puede cobrarse en su estado actual.');
                }

                $session = CashRegisterSession::where('open_user_id', $request->user()->id)
                    ->lockForUpdate()->first();
                if (! $session) throw new DomainException('Debes abrir un turno de caja antes de cobrar.');

                $total = (float) $payment->amount;
                $received = $validated['amount_received'] ?? null;
                if ($validated['payment_method'] === 'cash' && (float) $received < $total) {
                    throw new DomainException('El efectivo recibido es menor al total del pedido.');
                }

                $payment->update([
                    'gateway_provider' => $validated['payment_method'],
                    'status' => 'approved',
                    'evidence_type' => $validated['payment_method'] === 'cash'
                        ? 'cashier_confirmation'
                        : 'external_terminal_manual',
                    'amount_received' => $received,
                    'change_amount' => $received === null ? null : round((float) $received - $total, 2),
                    'transaction_reference' => $validated['transaction_reference'] ?? null,
                    'paid_at' => now(),
                    'confirmed_by' => $request->user()->id,
                    'collection_idempotency_key' => $validated['idempotency_key'],
                    'collection_request_fingerprint' => $fingerprint,
                ]);

                if ($validated['payment_method'] === 'cash') {
                    $session->movements()->create([
                        'user_id' => $request->user()->id,
                        'ticket_id' => $lockedTicket->id,
                        'type' => 'sale',
                        'amount' => $total,
                        'note' => "Cobro {$lockedTicket->ticket_number}",
                    ]);
                }

                if ($lockedTicket->status === 'pending') $lockedTicket->update(['status' => 'paid']);
                $lockedTicket->activities()->create([
                    'action' => 'Pago registrado: '.strtoupper($validated['payment_method']),
                    'author' => $request->user()->name,
                    'user_id' => $request->user()->id,
                ]);

                return $payment->fresh();
            });
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => 'PAYMENT_COLLECTION_REJECTED'], 422);
        }

        return response()->json([
            'message' => 'Cobro registrado correctamente.',
            'ticket_id' => $ticket->id,
            'payment' => $payment,
        ]);
    }
}
