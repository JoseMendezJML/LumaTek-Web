<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanAccess
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {

        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Sin usuario autenticado
        |--------------------------------------------------------------------------
        |
        | El middleware auth se encargará de este caso.
        |
        */

        if (!($user instanceof User)) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Actualizar restricciones del plan
        |--------------------------------------------------------------------------
        */

        $user->loadMissing(
            'company'
        );

        $company = $user->company;

        // This middleware guards company modules, never platform routes.
        abort_unless($company, 403, 'Esta cuenta no tiene acceso a los modulos empresariales.');

        $isRestricted =
            $company->isUserRestrictedByPlan(
                $user
            );

        if (!$isRestricted) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Peticiones API
        |--------------------------------------------------------------------------
        */

        if ($request->expectsJson()) {
            return response()->json([
                'message' =>
                'Tu cuenta está restringida por el plan actual de la empresa. '
                    . 'Contacta al propietario o renueva el plan Pro.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Sesión web
        |--------------------------------------------------------------------------
        */

        Auth::guard('web')->logout();

        $request
            ->session()
            ->invalidate();

        $request
            ->session()
            ->regenerateToken();

        return redirect()
            ->route('login')
            ->with(
                'error',
                'Tu cuenta está restringida por el plan actual de la empresa.'
            );
    }
}
