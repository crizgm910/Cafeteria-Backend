<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\IngredientController;
use App\Http\Controllers\Api\InventoryTransactionController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CategoryAddOnController;
use App\Http\Controllers\Api\AddOnController;
use App\Http\Controllers\Api\ProductConfigurationController;
use App\Http\Controllers\Api\CashRegisterController;
use App\Http\Controllers\Api\PosSaleController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PaymentCollectionController;
use App\Http\Controllers\Api\CatalogBootstrapController;
use App\Http\Controllers\Api\ServiceAreaController;
use App\Http\Controllers\Api\DiningTableController;
use App\Http\Controllers\Api\ReservationScheduleController;
use App\Http\Controllers\Api\ReservationBlockController;
use App\Http\Controllers\Api\ReservationAvailabilityController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

Route::get('/health', function () {
    try {
        DB::select('select 1');
        return response()->json(['status' => 'ok', 'database' => 'available']);
    } catch (\Throwable) {
        return response()->json(['status' => 'degraded', 'database' => 'unavailable'], 503);
    }
})->middleware('throttle:public-read');

// User route for Sanctum
Route::get('/user', function (Request $request) {
    $user = $request->user();

    return response()->json(array_merge($user->toArray(), $user->authorizationData()));
})->middleware('auth:sanctum');

// Login
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Rutas pÃºblicas (E-commerce Cliente)
Route::get('/menu', [MenuController::class, 'index'])->middleware('throttle:public-read');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout');
Route::get('/orders/{ticketNumber}/status', [CheckoutController::class, 'publicStatus'])->middleware('throttle:public-read');
Route::post('/reservations', [ReservationController::class, 'store'])->middleware('throttle:public-write');
Route::get('/reservation-availability', ReservationAvailabilityController::class)->middleware('throttle:public-read');

// Rutas protegidas (Staff Portal KDS)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/tickets', [CheckoutController::class, 'index'])->middleware('permission:tickets.view');
    Route::patch('/tickets/{id}/status', [CheckoutController::class, 'updateStatus'])->middleware('permission:tickets.update,tickets.cancel');
    
    Route::get('/reservations', [ReservationController::class, 'index'])->middleware('permission:reservations.manage');
    Route::patch('/reservations/{reservation}/status', [ReservationController::class, 'updateStatus'])->middleware('permission:reservations.manage');
    Route::patch('/reservations/{reservation}/assignment', [ReservationController::class, 'assign'])->middleware('permission:reservations.manage');
    Route::apiResource('service-areas', ServiceAreaController::class)->middleware('permission:reservation_areas.manage');
    Route::apiResource('dining-tables', DiningTableController::class)->middleware('permission:reservation_tables.manage');
    Route::apiResource('reservation-schedules', ReservationScheduleController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('permission:reservation_blocks.manage');
    Route::apiResource('reservation-blocks', ReservationBlockController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('permission:reservation_blocks.manage');

    Route::get('/catalog/bootstrap', CatalogBootstrapController::class)
        ->middleware('permission:catalog.manage,inventory.view');

    Route::apiResource('products', ProductController::class)->middleware('permission:catalog.manage');
    Route::apiResource('categories', CategoryController::class)->middleware('permission:catalog.manage');
    Route::get('/categories/{category}/add-ons', [CategoryAddOnController::class, 'index'])->middleware('permission:catalog.manage');
    Route::put('/categories/{category}/add-ons', [CategoryAddOnController::class, 'update'])->middleware('permission:catalog.manage');
    Route::apiResource('add-ons', AddOnController::class)->middleware('permission:catalog.manage');
    Route::get('/products/{product}/configuration', [ProductConfigurationController::class, 'show'])->middleware('permission:catalog.manage');
    Route::put('/products/{product}/recipe', [ProductConfigurationController::class, 'updateRecipe'])->middleware('permission:catalog.manage');
    Route::put('/products/{product}/add-ons', [ProductConfigurationController::class, 'updateAddOns'])->middleware('permission:catalog.manage');
    
    // Inventario
    Route::get('/ingredients', [IngredientController::class, 'index'])->middleware('permission:inventory.view');
    Route::get('/ingredients/{ingredient}', [IngredientController::class, 'show'])->middleware('permission:inventory.view');
    Route::post('/ingredients', [IngredientController::class, 'store'])->middleware('permission:inventory.manage');
    Route::match(['put', 'patch'], '/ingredients/{ingredient}', [IngredientController::class, 'update'])->middleware('permission:inventory.manage');
    Route::delete('/ingredients/{ingredient}', [IngredientController::class, 'destroy'])->middleware('permission:inventory.manage');
    Route::get('/inventory/transactions', [InventoryTransactionController::class, 'index'])->middleware('permission:inventory.view');
    Route::post('/inventory/transactions', [InventoryTransactionController::class, 'store'])->middleware('permission:inventory.adjust');

    Route::get('/cash-register/current', [CashRegisterController::class, 'current'])->middleware('permission:cash.manage');
    Route::post('/cash-register/open', [CashRegisterController::class, 'open'])->middleware('permission:cash.manage');
    Route::post('/cash-register/movements', [CashRegisterController::class, 'movement'])->middleware('permission:cash.manage');
    Route::post('/cash-register/close', [CashRegisterController::class, 'close'])->middleware('permission:cash.manage');
    Route::post('/pos/sales', [PosSaleController::class, 'store'])
        ->middleware(['permission:pos.operate', 'permission:cash.manage']);
    Route::post('/tickets/{ticket}/collect-payment', [PaymentCollectionController::class, 'store'])
        ->middleware(['permission:pos.operate', 'permission:cash.manage']);
    Route::get('/reports/daily', [ReportController::class, 'daily'])->middleware('permission:reports.view');
    Route::get('/audit-events', [AuditController::class, 'index'])->middleware('permission:audit.view');
    Route::get('/roles', [UserController::class, 'roles'])->middleware('permission:users.manage');
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.manage');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.manage');
    Route::match(['put', 'patch'], '/users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage');
});





