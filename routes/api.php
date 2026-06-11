<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\EscrowController;
use App\Http\Controllers\RevisionController;

// ─────────────────────────────────────────────────────────────
// PUBLIC
// ─────────────────────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Midtrans webhook / notification — dipanggil server Midtrans, bukan Flutter
Route::post('/payment/notification', [PaymentController::class, 'handleNotification']);

// Payment flow Flutter — public karena Flutter kamu tidak pakai Sanctum
Route::post('/payment/initiate', [PaymentController::class, 'initiatePayment']);
Route::post('/payment/complete', [PaymentController::class, 'completeOrder']);
Route::get('/orders/{id}/status', [PaymentController::class, 'getStatus']);

// ─────────────────────────────────────────────────────────────
// PROTECTED — Sanctum (untuk fitur admin/panel)
// ─────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/deals', [DealController::class, 'create']);
    Route::post('/deals/{id}/accept', [DealController::class, 'accept']);

    Route::post('/orders/from-deal/{dealId}', [OrderController::class, 'createFromDeal']);

    Route::post('/escrow/{paymentId}/release', [EscrowController::class, 'release']);
    Route::post('/revisions', [RevisionController::class, 'request']);
});