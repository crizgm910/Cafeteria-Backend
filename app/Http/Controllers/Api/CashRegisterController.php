<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashRegisterController extends Controller
{
    public function current(Request $request)
    {
        $session = CashRegisterSession::with(['movements.user:id,name', 'opener:id,name'])
            ->where('open_user_id', $request->user()->id)
            ->first();

        if (! $session) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $this->summary($session)]);
    }

    public function open(Request $request)
    {
        $validated = $request->validate([
            'opening_amount' => 'required|numeric|min:0|max:9999999999.99',
        ]);

        $session = DB::transaction(function () use ($request, $validated) {
            DB::table('users')
                ->where('id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (CashRegisterSession::where('open_user_id', $request->user()->id)->exists()) {
                return null;
            }

            return CashRegisterSession::create([
                'opened_by' => $request->user()->id,
                'open_user_id' => $request->user()->id,
                'opening_amount' => $validated['opening_amount'],
                'status' => 'open',
                'opened_at' => now(),
            ]);
        });

        if (! $session) {
            return response()->json([
                'message' => 'Ya existe un turno de caja abierto para este usuario.',
                'code' => 'CASH_SESSION_ALREADY_OPEN',
            ], 409);
        }

        return response()->json(['data' => $this->summary($session)], 201);
    }

    public function movement(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:deposit,withdrawal',
            'amount' => 'required|numeric|gt:0|max:9999999999.99',
            'note' => 'required|string|max:500',
        ]);

        $session = CashRegisterSession::where('open_user_id', $request->user()->id)->first();
        if (! $session) {
            return response()->json([
                'message' => 'Debes abrir un turno de caja antes de registrar movimientos.',
                'code' => 'CASH_SESSION_REQUIRED',
            ], 409);
        }

        $amount = (float) $validated['amount'];
        if ($validated['type'] === 'withdrawal') {
            $amount *= -1;
        }

        $movement = $session->movements()->create([
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'amount' => $amount,
            'note' => $validated['note'],
        ]);

        return response()->json([
            'movement' => $movement,
            'data' => $this->summary($session->fresh()),
        ], 201);
    }

    public function close(Request $request)
    {
        $validated = $request->validate([
            'counted_cash' => 'required|numeric|min:0|max:9999999999.99',
            'closing_note' => 'nullable|string|max:500',
        ]);

        $session = DB::transaction(function () use ($request, $validated) {
            $session = CashRegisterSession::where('open_user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (! $session) {
                return null;
            }

            $expected = $session->calculatedExpectedCash();
            $counted = round((float) $validated['counted_cash'], 2);
            $session->update([
                'open_user_id' => null,
                'closed_by' => $request->user()->id,
                'expected_cash' => $expected,
                'counted_cash' => $counted,
                'difference' => round($counted - $expected, 2),
                'status' => 'closed',
                'closed_at' => now(),
                'closing_note' => $validated['closing_note'] ?? null,
            ]);

            return $session->fresh();
        });

        if (! $session) {
            return response()->json([
                'message' => 'No existe un turno abierto para cerrar.',
                'code' => 'CASH_SESSION_REQUIRED',
            ], 409);
        }

        return response()->json(['data' => $this->summary($session)]);
    }

    private function summary(CashRegisterSession $session): array
    {
        $session->loadMissing(['movements.user:id,name', 'opener:id,name', 'closer:id,name']);

        return array_merge($session->toArray(), [
            'calculated_expected_cash' => $session->status === 'open'
                ? $session->calculatedExpectedCash()
                : (float) $session->expected_cash,
        ]);
    }
}
