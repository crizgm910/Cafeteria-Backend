<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\MenuController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Rutas de la API de Cafeteria
Route::get('/menu', [MenuController::class, 'index']);
Route::post('/checkout', [CheckoutController::class, 'store']);
Route::post('/reservations', [\App\Http\Controllers\Api\ReservationController::class, 'store']);
// Update order/reservation status (for admin simulation)
Route::patch('/reservations/{id}/status', [\App\Http\Controllers\Api\ReservationController::class, 'updateStatus']);
Route::patch('/tickets/{id}/status', [\App\Http\Controllers\Api\CheckoutController::class, 'updateStatus']);
