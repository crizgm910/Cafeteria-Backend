<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;

// User route for Sanctum
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Login
Route::post('/login', [AuthController::class, 'login']);

// Rutas pÃºblicas (E-commerce Cliente)
Route::get('/menu', [MenuController::class, 'index']);
Route::post('/checkout', [CheckoutController::class, 'store']);
Route::post('/reservations', [ReservationController::class, 'store']);

// Rutas protegidas (Staff Portal KDS)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/tickets', [CheckoutController::class, 'index']);
    Route::patch('/tickets/{id}/status', [CheckoutController::class, 'updateStatus']);
    
    Route::get('/reservations', [ReservationController::class, 'index']);
    Route::patch('/reservations/{id}/status', [ReservationController::class, 'updateStatus']);

    Route::apiResource('products', ProductController::class);
});





