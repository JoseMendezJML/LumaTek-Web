<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\GreenhouseController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register'])
    ->name('api.register');

Route::post('/login', [AuthController::class, 'login'])
    ->name('api.login');

/*
|--------------------------------------------------------------------------
| Recuperación de contraseña
|--------------------------------------------------------------------------
*/

Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->name('password.email');

Route::post('/password/reset', [AuthController::class, 'resetPassword'])
    ->name('password.update');

/*
|--------------------------------------------------------------------------
| Verificación de correo
|--------------------------------------------------------------------------
*/

Route::get('/email/verify/{id}/{hash}', function (
    Request $request,
    string $id,
    string $hash
) {
    $user = User::findOrFail($id);

    abort_unless(
        hash_equals(
            (string) $hash,
            sha1($user->getEmailForVerification())
        ),
        403
    );

    if ($user->hasVerifiedEmail()) {
        return response()->json([
            'message' => 'El correo electrónico ya estaba verificado.',
        ]);
    }

    $user->markEmailAsVerified();

    return response()->json([
        'message' => 'Correo electrónico verificado correctamente.',
    ]);
})
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

/*
|--------------------------------------------------------------------------
| Rutas protegidas
|--------------------------------------------------------------------------
|
| Estas rutas requieren un JWT válido.
|
*/

Route::middleware('auth:api')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Invernaderos
    |--------------------------------------------------------------------------
    */

    Route::get('/greenhouses', [GreenhouseController::class, 'index'])
        ->name('greenhouses.index');

    Route::post('/greenhouses', [GreenhouseController::class, 'store'])
        ->name('greenhouses.store');

    Route::get('/greenhouses/{greenhouse}', [GreenhouseController::class, 'show'])
        ->name('greenhouses.show');

    Route::put('/greenhouses/{greenhouse}', [GreenhouseController::class, 'update'])
        ->name('greenhouses.update');

    Route::patch('/greenhouses/{greenhouse}', [GreenhouseController::class, 'update']);
});