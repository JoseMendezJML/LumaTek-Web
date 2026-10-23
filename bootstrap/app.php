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
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Symfony\Component\HttpFoundation\Response;

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

        // Proxy IPs are configured in AppServiceProvider after config/env boot.
        // Infer the original URL per request; never force HTTPS globally.
        $middleware->trustProxies(headers:
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_PREFIX
        );

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

        // Conserva estados, claves y cabeceras; localiza solo mensajes públicos
        // del framework. Los detalles técnicos siguen disponibles en los logs.
        $exceptions->respond(function (Response $response): Response {
            if ($response instanceof JsonResponse) {
                $data = $response->getData(true);
                $message = $data['message'] ?? null;
                $status = $response->getStatusCode();

                if ($status === 404) {
                    $data['message'] = __('Not Found');
                } elseif ($status === 405) {
                    $data['message'] = __('Method Not Allowed');
                } elseif ($message === '' && isset(Response::$statusTexts[$status])) {
                    $data['message'] = Lang::hasForLocale('http.'.$status)
                        ? __('http.'.$status)
                        : __('http.client_error');
                } elseif (is_string($message) && Lang::hasForLocale($message)) {
                    $data['message'] = __($message);
                }

                $response->setData($data);
            }

            return $response;
        });

        // El asistente responde el throttle con el mismo formato que su límite diario.
        $exceptions->render(function (
            ThrottleRequestsException $error,
            Request $request
        ) {
            if (! $request->routeIs('api.chat.send')) {
                return null;
            }

            return response()->json([
                'code' => 'chat_rate_limited',
                'message' => 'Estás enviando mensajes muy rápido. Espera un minuto e intenta de nuevo.',
            ], 429, $error->getHeaders());
        });
    })
    ->create();
