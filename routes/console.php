<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Comandos de consola
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {

    $this->comment(
        Inspiring::quote()
    );

})->purpose(
    'Display an inspiring quote'
);


/*
|--------------------------------------------------------------------------
| Procesamiento de riego automático
|--------------------------------------------------------------------------
|
| Cada minuto Laravel comprobará si algún riego automático
| ya cumplió la duración configurada.
|
| withoutOverlapping evita que se ejecute dos veces al mismo tiempo.
|
*/

Schedule::command(
    'irrigation:process-automatic'
)
    ->everyMinute()
    ->withoutOverlapping();