<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeaveBalanceController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\LeaveRequestController as AdminLeaveRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);

    // User - Leave Balance
    Route::get('/leave-balances', [LeaveBalanceController::class, 'index']);

    // User - Leave Request
    Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
    Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
    Route::patch('/leave-requests/{id}/cancel', [LeaveRequestController::class, 'cancel']);
    Route::delete('/leave-requests/{id}', [LeaveRequestController::class, 'destroy']);

    // Admin routes
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Admin - User Management
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::put('/users/{user}', [AdminUserController::class, 'update']);

        // Admin - Leave Request Management
        Route::get('/leave-requests', [AdminLeaveRequestController::class, 'index']);
        Route::patch('/leave-requests/{id}/approve', [AdminLeaveRequestController::class, 'approve']);
        Route::patch('/leave-requests/{id}/reject', [AdminLeaveRequestController::class, 'reject']);
        Route::delete('/leave-requests/{id}', [AdminLeaveRequestController::class, 'destroy']);
    });
});
