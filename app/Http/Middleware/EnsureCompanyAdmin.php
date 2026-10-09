<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAdmin
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {

        $user = $request->user();

        if (
            !$user
            ||
            $user->role?->name !== 'company_admin'
        ) {
            abort(
                403,
                'No tienes permisos para acceder a esta sección.'
            );
        }

        return $next($request);
    }
}
