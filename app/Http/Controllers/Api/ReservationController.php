<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reservation;

class ReservationController extends Controller
{
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
            'status' => 'required|in:pending,approved,ready,cancelled',
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
