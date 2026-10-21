<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([
            'platform.admin' => \App\Http\Middleware\EnsureSuperadmin::class,

            'active.user' =>
            \App\Http\Middleware\EnsureActiveUser::class,

            'company.admin' =>
            \App\Http\Middleware\EnsureCompanyAdmin::class,

            'plan.access' =>
            \App\Http\Middleware\EnsurePlanAccess::class,
        ]);

        $middleware->appendToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Http\Middleware\EnsureActiveUser::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
