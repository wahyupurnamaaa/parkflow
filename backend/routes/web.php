<?php

use App\Http\Controllers\ParkingController as P;
use App\Http\Middleware\OperatorAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    DB::select('SELECT 1');

    return ['status' => 'ok', 'service' => 'parkflow'];
});
Route::prefix('api')->group(function () {
    Route::get('/auth/csrf', fn () => ['csrf_token' => csrf_token()]);
    Route::post('/auth/login', [P::class, 'login'])->middleware('throttle:20,1');
    Route::get('/tickets/{token}', [P::class, 'ticket'])->middleware('throttle:60,1');
    Route::middleware(['auth', OperatorAccess::class, 'throttle:180,1'])->group(function () {
        Route::get('/auth/me', fn () => ['user' => auth()->user()]);
        Route::post('/auth/logout', [P::class, 'logout']);
        Route::post('/auth/password', [P::class, 'password']);
        Route::get('/dashboard', [P::class, 'dashboard']);
        Route::get('/parking-locations', [P::class, 'locations']);
        Route::post('/parking-locations', [P::class, 'createLocation']);
        Route::get('/parking-areas', [P::class, 'areas']);
        Route::post('/parking-areas', [P::class, 'createArea']);
        Route::get('/parking-slots', [P::class, 'slots']);
        Route::patch('/parking-slots/{id}', [P::class, 'updateSlot']);
        Route::get('/vehicles', [P::class, 'vehicles']);
        Route::get('/rates', [P::class, 'rates']);
        Route::post('/rates', [P::class, 'saveRate']);
        Route::get('/parking/sessions', [P::class, 'sessionList']);
        Route::get('/parking/sessions/{id}', [P::class, 'session']);
        Route::post('/parking/check-in', [P::class, 'checkIn']);
        Route::post('/parking/quote', [P::class, 'quote']);
        Route::post('/parking/check-out', [P::class, 'checkOut']);
        Route::get('/payments', [P::class, 'payments']);
        Route::get('/reports/revenue', [P::class, 'reports']);
        Route::get('/audit-logs', [P::class, 'auditLogs']);
        Route::get('/staff',[P::class, 'staff']);
    });
});
