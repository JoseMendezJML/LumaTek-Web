<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Platform\CompanyController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')
    ->name('landing');

Route::view('/login', 'auth.login')
    ->name('login');

Route::post(
    '/login',
    [AuthController::class, 'webLogin']
)->name('web.login');

Route::view('/register', 'auth.register')
    ->name('register');

Route::view('/forgot-password', 'auth.forgot-password')
    ->name('password.request');

Route::get('/reset-password/{token}', function (
    Request $request,
    string $token
) {
    return view('auth.reset-password', [
        'token' => $token,
        'email' => $request->query('email'),
    ]);
})
    ->name('password.reset');

// Subscription management uses web sessions, independently of module plan access.
Route::middleware(['auth', 'active.user', 'company.owner'])->group(function () {
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/subscription/quotes', [SubscriptionController::class, 'store'])->name('subscription.quotes.store');
});

/*
|--------------------------------------------------------------------------
| Panel LumaTek
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'active.user',
    'plan.access',
])->group(function () {
    Route::view('/greenhouses', 'greenhouses.index')
        ->name('greenhouses.web.index');

    Route::view('/zones', 'zones.index')
        ->name('zones.index');

    Route::view('/alerts', 'alerts.index')
        ->name('alerts.index');

    Route::view('/dashboard', 'dashboard.index')
        ->name('dashboard');

    Route::view('/irrigation', 'irrigation.index')
        ->name('irrigation.index');

    Route::view('/history', 'history.index')
        ->name('history.index');

    Route::view('/reports', 'reports.index')
        ->name('reports.index');

    Route::middleware('company.admin')->group(function () {

        Route::view('/users', 'users.index')
            ->name('users.index');

        Route::view('/settings', 'settings.index')
            ->name('settings.index');
    });

    Route::post(
        '/logout',
        [AuthController::class, 'webLogout']
    )->name('web.logout');
});

// Platform routes deliberately do not depend on a company's subscription.
Route::prefix('platform')->name('platform.')->middleware([
    'auth', 'active.user', 'platform.admin',
])->group(function () {
    Route::view('/', 'platform.index')->name('dashboard');
    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
    Route::patch('/companies/{company}/status', [CompanyController::class, 'changeStatus'])->name('companies.status');
    Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
    Route::get('/subscriptions', [App\Http\Controllers\Platform\SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::get('/subscriptions/{quote}', [App\Http\Controllers\Platform\SubscriptionController::class, 'show'])->name('subscriptions.show');
    Route::post('/subscriptions/{quote}/confirm', [App\Http\Controllers\Platform\SubscriptionController::class, 'confirm'])->name('subscriptions.confirm');
    Route::patch('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::post('/logout', [AuthController::class, 'webLogout'])->name('logout');
});
