<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

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
    Route::get('/companies', [\App\Http\Controllers\Platform\CompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/{company}', [\App\Http\Controllers\Platform\CompanyController::class, 'show'])->name('companies.show');
    Route::patch('/companies/{company}/status', [\App\Http\Controllers\Platform\CompanyController::class, 'changeStatus'])->name('companies.status');
    Route::get('/plans', [\App\Http\Controllers\Platform\PlanController::class, 'index'])->name('plans.index');
    Route::patch('/plans/{plan}', [\App\Http\Controllers\Platform\PlanController::class, 'update'])->name('plans.update');
    Route::post('/logout', [AuthController::class, 'webLogout'])->name('logout');
});
