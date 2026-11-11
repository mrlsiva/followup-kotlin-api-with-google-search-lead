<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessFinder\BusinessFinderController;
use App\Http\Controllers\Api\BusinessFinder\BusinessLeadController;
use App\Http\Controllers\Api\BusinessFinder\BusinessLeadExportController;
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

    // Business Lead Finder (free manual research → existing CRM leads / follow-ups)
    Route::prefix('business-finder')->middleware('throttle:business-finder')->group(function () {
        Route::get('options', [BusinessFinderController::class, 'options']);
        Route::get('categories', [BusinessFinderController::class, 'categories']);
        Route::get('locations', [BusinessFinderController::class, 'locations']);
        Route::get('search-links', [BusinessFinderController::class, 'searchLinks']);
        Route::get('history', [BusinessFinderController::class, 'history']);
        Route::delete('history/{id}', [BusinessFinderController::class, 'destroyHistory'])->whereNumber('id');
        Route::get('suggestions', [BusinessFinderController::class, 'suggestions']);
        Route::get('dashboard', [BusinessFinderController::class, 'dashboard']);
        Route::get('check-duplicate', [BusinessFinderController::class, 'checkDuplicate']);
        Route::post('verify-website', [BusinessFinderController::class, 'verifyWebsite'])->middleware('throttle:website-check');

        Route::get('export/{format}', [BusinessLeadExportController::class, 'export'])
            ->whereIn('format', ['csv', 'excel', 'pdf'])->middleware('throttle:exports');

        Route::get('leads', [BusinessLeadController::class, 'index']);
        Route::post('leads', [BusinessLeadController::class, 'store']);
        Route::get('leads/{businessLead}', [BusinessLeadController::class, 'show']);
        Route::put('leads/{businessLead}', [BusinessLeadController::class, 'update']);
        Route::delete('leads/{businessLead}', [BusinessLeadController::class, 'destroy']);
        Route::post('leads/{businessLead}/convert', [BusinessLeadController::class, 'convert']);
        Route::post('leads/{businessLead}/verify-contact', [BusinessLeadController::class, 'verifyContact']);
        Route::post('leads/{businessLead}/verify-website', [BusinessLeadController::class, 'verifyWebsite'])->middleware('throttle:website-check');
    });
});
