<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reservation;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $query = Reservation::query();

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('date') && $request->date !== 'all') {
            if ($request->date === 'today') {
                $query->where('date', date('Y-m-d'));
            } elseif ($request->date === 'tomorrow') {
                $query->where('date', date('Y-m-d', strtotime('+1 day')));
            } elseif ($request->date === 'week') {
                $query->whereBetween('date', [date('Y-m-d'), date('Y-m-d', strtotime('+7 days'))]);
            }
        }

        $reservations = $query->orderBy('created_at', 'desc')->get();
        return response()->json($reservations);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'date' => 'required|string',
            'time' => 'required|string',
            'guests' => 'required|integer|min:1|max:6',
        ]);

        $reservation = Reservation::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'date' => $validated['date'],
            'time' => $validated['time'],
            'guests' => $validated['guests'],
            'status' => 'pending', // Default
        ]);

        return response()->json([
            'message' => 'Reserva creada exitosamente',
            'reservation' => $reservation
        ], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,approved,ready,cancelled,completed',
        ]);

        $reservation = Reservation::findOrFail($id);
        $reservation->status = $validated['status'];
        $reservation->save();

        return response()->json([
            'message' => 'Estado de reserva actualizado',
            'reservation' => $reservation
        ]);
    }
}
