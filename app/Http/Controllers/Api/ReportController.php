<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use App\Models\CashMovement;
use App\Models\Payment;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function daily(Request $request)
    {
        $validated = $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        $date = CarbonImmutable::parse($validated['date'] ?? now()->toDateString());
        $start = $date->startOfDay();
        $end = $date->endOfDay();

        $tickets = Ticket::whereBetween('created_at', [$start, $end]);
        $capturedPayments = Payment::whereIn('status', ['approved', 'refunded'])
            ->where('evidence_type', '!=', 'legacy_unverified')
            ->whereBetween('paid_at', [$start, $end]);
        $refundedPayments = Payment::where('status', 'refunded')
            ->where('evidence_type', '!=', 'legacy_unverified')
            ->whereBetween('refunded_at', [$start, $end]);

        $paymentMethods = (clone $capturedPayments)
            ->selectRaw('gateway_provider, COUNT(*) as transactions, SUM(amount) as total')
            ->groupBy('gateway_provider')
            ->orderBy('gateway_provider')
            ->get();

        $cashSessions = CashRegisterSession::whereBetween('opened_at', [$start, $end]);
        $cashMovements = CashMovement::whereBetween('created_at', [$start, $end]);
        $grossCollected = (float) (clone $capturedPayments)->sum('amount');
        $refundedTotal = (float) (clone $refundedPayments)->sum('amount');

        return response()->json([
            'date' => $date->toDateString(),
            'orders' => [
                'total' => (clone $tickets)->count(),
                'delivered' => (clone $tickets)->where('status', 'delivered')->count(),
                'cancelled' => (clone $tickets)->where('status', 'cancelled')->count(),
                'gross_non_cancelled' => (float) (clone $tickets)->where('status', '!=', 'cancelled')->sum('total'),
            ],
            'payments' => [
                'captured_transactions' => (clone $capturedPayments)->count(),
                'gross_collected' => $grossCollected,
                'refunded_total' => $refundedTotal,
                'net_collected' => round($grossCollected - $refundedTotal, 2),
                'methods' => $paymentMethods,
            ],
            'cash' => [
                'sessions' => (clone $cashSessions)->count(),
                'closed_sessions' => (clone $cashSessions)->where('status', 'closed')->count(),
                'difference_total' => (float) (clone $cashSessions)->where('status', 'closed')->sum('difference'),
                'sales' => (float) (clone $cashMovements)->where('type', 'sale')->sum('amount'),
                'refunds' => abs((float) (clone $cashMovements)->where('type', 'refund')->sum('amount')),
                'net_movements' => (float) (clone $cashMovements)->sum('amount'),
            ],
        ]);
    }
}
