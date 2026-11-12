<?php

use App\Http\Controllers\Web\Admin\CategoryController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BusinessController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FinderController;
use App\Http\Controllers\Web\FollowupController;
use App\Http\Controllers\Web\LeadController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\TransferController;
use Illuminate\Support\Facades\Route;

/*
| Web panel (session login). The REST API for the Android app lives in routes/api.php;
| Swagger stays at /api/documentation.
*/

Route::get('locale/{locale}', [AuthController::class, 'locale'])->name('locale');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    // CRM leads
    Route::get('import-export', [TransferController::class, 'index'])->name('transfer.index');
    Route::get('leads/export', [TransferController::class, 'export'])->name('leads.export');
    Route::post('leads/import', [TransferController::class, 'import'])->name('leads.import');
    Route::resource('leads', LeadController::class);
    Route::post('leads/{lead}/notes', [LeadController::class, 'addNote'])->name('leads.notes.store');
    Route::post('leads/{lead}/reassign', [LeadController::class, 'reassign'])->name('leads.reassign');

    // Follow-ups
    Route::get('followups', [FollowupController::class, 'index'])->name('followups.index');
    Route::post('followups/{followup}/complete', [FollowupController::class, 'complete'])->name('followups.complete');
    Route::put('followups/{followup}', [FollowupController::class, 'reschedule'])->name('followups.reschedule');
    Route::delete('followups/{followup}', [FollowupController::class, 'destroy'])->name('followups.destroy');

    // Business Lead Finder
    Route::prefix('business-finder')->name('finder.')->group(function () {
        Route::get('/', [FinderController::class, 'dashboard'])->name('dashboard');
        Route::get('search', [FinderController::class, 'search'])->name('search');
        Route::get('search/results', [FinderController::class, 'results'])->name('results');
        Route::get('history', [FinderController::class, 'history'])->name('history');
        Route::delete('history/{id}', [FinderController::class, 'destroyHistory'])->whereNumber('id')->name('history.destroy');
        Route::get('check-duplicate', [FinderController::class, 'checkDuplicate'])->name('check-duplicate');
        Route::post('verify-website', [FinderController::class, 'verifyWebsite'])->middleware('throttle:website-check')->name('verify-website');
    });
    Route::prefix('businesses')->name('businesses.')->group(function () {
        Route::get('/', [BusinessController::class, 'index'])->name('index');
        Route::get('create', [BusinessController::class, 'create'])->name('create');
        Route::post('/', [BusinessController::class, 'store'])->name('store');
        Route::get('export/{format}', [BusinessController::class, 'export'])->whereIn('format', ['csv', 'excel', 'pdf'])
            ->middleware('throttle:exports')->name('export');
        Route::get('{businessLead}', [BusinessController::class, 'show'])->name('show');
        Route::get('{businessLead}/edit', [BusinessController::class, 'edit'])->name('edit');
        Route::put('{businessLead}', [BusinessController::class, 'update'])->name('update');
        Route::delete('{businessLead}', [BusinessController::class, 'destroy'])->name('destroy');
        Route::post('{businessLead}/convert', [BusinessController::class, 'convert'])->name('convert');
        Route::post('{businessLead}/verify-contact', [BusinessController::class, 'verifyContact'])->name('verify-contact');
        Route::post('{businessLead}/verify-website', [BusinessController::class, 'verifyWebsite'])->middleware('throttle:website-check')->name('verify-website');
    });

    // Team reports (admins + managers)
    Route::middleware('role:team')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export/{format}', [ReportController::class, 'export'])->whereIn('format', ['excel', 'pdf'])->name('reports.export');
    });

    // User management (admins)
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{managedUser}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{managedUser}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{managedUser}/toggle', [UserController::class, 'toggle'])->name('users.toggle');

        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::post('categories/{category}/toggle', [CategoryController::class, 'toggle'])->name('categories.toggle');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    });
});
