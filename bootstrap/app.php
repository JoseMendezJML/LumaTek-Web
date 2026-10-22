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
        //
    })
    ->create();
