<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AmbientHumidityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\GreenhouseController;
use App\Http\Controllers\IrrigationController;
use App\Http\Controllers\SensorController;
use App\Http\Controllers\SoilMoistureController;
use App\Http\Controllers\TemperatureController;
use App\Http\Controllers\ZoneController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/

Route::post(
    '/register',
    [AuthController::class, 'register']
)->name('api.register');

Route::post(
    '/login',
    [AuthController::class, 'login']
)->name('api.login');


/*
|--------------------------------------------------------------------------
| Recuperación de contraseña
|--------------------------------------------------------------------------
*/

Route::post(
    '/forgot-password',
    [AuthController::class, 'forgotPassword']
)->name('password.email');

Route::post(
    '/password/reset',
    [AuthController::class, 'resetPassword']
)->name('password.update');


/*
|--------------------------------------------------------------------------
| Verificación de correo
|--------------------------------------------------------------------------
*/

Route::get(
    '/email/verify/{id}/{hash}',
    function (
        Request $request,
        string $id,
        string $hash
    ) {

        $user = User::findOrFail($id);

        abort_unless(
            hash_equals(
                (string) $hash,
                sha1(
                    $user->getEmailForVerification()
                )
            ),
            403
        );

        if ($user->hasVerifiedEmail()) {

            return response()->json([
                'message' =>
                    'El correo electrónico ya estaba verificado.',
            ]);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'message' =>
                'Correo electrónico verificado correctamente.',
        ]);
    }
)
    ->middleware([
        'signed',
        'throttle:6,1',
    ])
    ->name('verification.verify');


/*
|--------------------------------------------------------------------------
| Rutas protegidas con JWT
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {


    /*
    |--------------------------------------------------------------------------
    | Invernaderos
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/greenhouses',
        [GreenhouseController::class, 'index']
    )->name('greenhouses.index');


    Route::post(
        '/greenhouses',
        [GreenhouseController::class, 'store']
    )->name('greenhouses.store');


    Route::get(
        '/greenhouses/{greenhouse}',
        [GreenhouseController::class, 'show']
    )->name('greenhouses.show');


    Route::put(
        '/greenhouses/{greenhouse}',
        [GreenhouseController::class, 'update']
    )->name('greenhouses.update');


    Route::patch(
        '/greenhouses/{greenhouse}',
        [GreenhouseController::class, 'update']
    );


    /*
    |--------------------------------------------------------------------------
    | Temperatura
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/sensors/{sensor}/temperature',
        [TemperatureController::class, 'store']
    )->name('temperature.store');


    Route::get(
        '/sensors/{sensor}/temperature/current',
        [TemperatureController::class, 'current']
    )->name('temperature.current');


    Route::get(
        '/greenhouses/{greenhouse}/temperature/current',
        [TemperatureController::class, 'currentByGreenhouse']
    )->name('greenhouses.temperature.current');


    /*
    |--------------------------------------------------------------------------
    | Humedad del suelo
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/sensors/{sensor}/soil-moisture',
        [SoilMoistureController::class, 'store']
    )->name('soil-moisture.store');


    Route::get(
        '/sensors/{sensor}/soil-moisture/current',
        [SoilMoistureController::class, 'current']
    )->name('soil-moisture.current');


    Route::get(
        '/sensors/{sensor}/soil-moisture/history',
        [SoilMoistureController::class, 'history']
    )->name('soil-moisture.history');


    Route::get(
        '/greenhouses/{greenhouse}/soil-moisture/current',
        [SoilMoistureController::class, 'currentByGreenhouse']
    )->name('greenhouses.soil-moisture.current');


    Route::get(
        '/greenhouses/{greenhouse}/soil-moisture/history',
        [SoilMoistureController::class, 'historyByGreenhouse']
    )->name('greenhouses.soil-moisture.history');


    /*
    |--------------------------------------------------------------------------
    | Humedad ambiental
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/sensors/{sensor}/ambient-humidity',
        [AmbientHumidityController::class, 'store']
    )->name('ambient-humidity.store');


    Route::get(
        '/sensors/{sensor}/ambient-humidity/current',
        [AmbientHumidityController::class, 'current']
    )->name('ambient-humidity.current');


    Route::get(
        '/greenhouses/{greenhouse}/ambient-humidity/current',
        [AmbientHumidityController::class, 'currentByGreenhouse']
    )->name('greenhouses.ambient-humidity.current');


    /*
    |--------------------------------------------------------------------------
    | Zonas
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/greenhouses/{greenhouse}/zones',
        [ZoneController::class, 'index']
    )->name('greenhouses.zones.index');


    Route::post(
        '/greenhouses/{greenhouse}/zones',
        [ZoneController::class, 'store']
    )->name('greenhouses.zones.store');


    Route::put(
        '/zones/{zone}',
        [ZoneController::class, 'update']
    )->name('zones.update');


    Route::patch(
        '/zones/{zone}/status',
        [ZoneController::class, 'changeStatus']
    )->name('zones.status');


    /*
    |--------------------------------------------------------------------------
    | Dispositivos
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/zones/{zone}/devices',
        [DeviceController::class, 'index']
    )->name('zones.devices.index');


    Route::post(
        '/zones/{zone}/devices',
        [DeviceController::class, 'store']
    )->name('zones.devices.store');


    Route::put(
        '/devices/{device}',
        [DeviceController::class, 'update']
    )->name('devices.update');


    Route::patch(
        '/devices/{device}/status',
        [DeviceController::class, 'changeStatus']
    )->name('devices.status');


    /*
    |--------------------------------------------------------------------------
    | Sensores
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/devices/{device}/sensors',
        [SensorController::class, 'index']
    )->name('devices.sensors.index');


    Route::post(
        '/devices/{device}/sensors',
        [SensorController::class, 'store']
    )->name('devices.sensors.store');


    Route::put(
        '/sensors/{sensor}',
        [SensorController::class, 'update']
    )->name('sensors.update');


    Route::patch(
        '/sensors/{sensor}/status',
        [SensorController::class, 'changeStatus']
    )->name('sensors.status');


    /*
    |--------------------------------------------------------------------------
    | Alertas
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/alerts',
        [AlertController::class, 'index']
    )->name('api.alerts.index');


    Route::patch(
        '/alerts/{alert}/acknowledge',
        [AlertController::class, 'acknowledge']
    )->name('alerts.acknowledge');


    Route::patch(
        '/alerts/{alert}/resolve',
        [AlertController::class, 'resolve']
    )->name('alerts.resolve');


    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/dashboard',
        [DashboardController::class, 'index']
    )->name('dashboard.index');


    /*
    |--------------------------------------------------------------------------
    | Riego
    |--------------------------------------------------------------------------
    */

    Route::get(
    '/irrigation-events',
    [IrrigationController::class, 'index']
)->name('api.irrigation.index');

Route::get(
    '/irrigation-events/{irrigationEvent}',
    [IrrigationController::class, 'show']
)->name('api.irrigation.show');

Route::post(
    '/zones/{zone}/irrigation/start',
    [IrrigationController::class, 'startManual']
)->name('api.irrigation.start');

Route::patch(
    '/irrigation-events/{irrigationEvent}/complete',
    [IrrigationController::class, 'complete']
)->name('api.irrigation.complete');

Route::patch(
    '/irrigation-events/{irrigationEvent}/cancel',
    [IrrigationController::class, 'cancel']
)->name('api.irrigation.cancel');

});