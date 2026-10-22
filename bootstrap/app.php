<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureCompanyAdmin;
use App\Http\Middleware\EnsureCompanyOwner;
use App\Http\Middleware\EnsurePlanAccess;
use App\Http\Middleware\EnsureSuperadmin;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([
            'company.owner' => EnsureCompanyOwner::class,
            'platform.admin' => EnsureSuperadmin::class,

            'active.user' => EnsureActiveUser::class,

            'company.admin' => EnsureCompanyAdmin::class,

            'plan.access' => EnsurePlanAccess::class,
        ]);

        $middleware->appendToPriorityList(
            AuthenticatesRequests::class,
            EnsureActiveUser::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // El asistente responde el throttle con el mismo formato que su límite diario.
        $exceptions->render(function (
            \Illuminate\Http\Exceptions\ThrottleRequestsException $error,
            \Illuminate\Http\Request $request
        ) {
            if (!$request->routeIs('api.chat.send')) {
                return null;
            }

            return response()->json([
                'code' => 'chat_rate_limited',
                'message' => 'Estás enviando mensajes muy rápido. Espera un minuto e intenta de nuevo.',
            ], 429, $error->getHeaders());
        });
    })
    ->create();
