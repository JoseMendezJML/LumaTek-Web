<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->is_company_owner && $user->role?->name === 'company_admin'
            && $user->company?->status === 'active', 403);

        return $next($request);
    }
}
