<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FollowupController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LeadFollowupController;
use App\Http\Controllers\Api\LeadTransferController;
use App\Http\Controllers\Api\MetaController;
use Illuminate\Support\Facades\Route;

/*
| Base URL (XAMPP): http://localhost/followup-api-kotlin/public/api
| Swagger UI:       http://localhost/followup-api-kotlin/public/api/documentation
*/

Route::get('options', MetaController::class);

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('dashboard', DashboardController::class);

    // Import / export (declared before the {lead} routes)
    Route::get('leads/export', [LeadTransferController::class, 'export']);
    Route::post('leads/import', [LeadTransferController::class, 'import']);

    Route::apiResource('leads', LeadController::class);
    Route::get('leads/{lead}/followups', [LeadFollowupController::class, 'index']);
    Route::post('leads/{lead}/followups', [LeadFollowupController::class, 'store']);

    Route::get('followups/reminders', [FollowupController::class, 'reminders']);
    Route::get('followups/daily-summary', [FollowupController::class, 'dailySummary']);
    Route::get('followups', [FollowupController::class, 'index']);
    Route::get('followups/{followup}', [FollowupController::class, 'show']);
    Route::put('followups/{followup}', [FollowupController::class, 'update']);
    Route::post('followups/{followup}/complete', [FollowupController::class, 'complete']);
    Route::delete('followups/{followup}', [FollowupController::class, 'destroy']);
});
