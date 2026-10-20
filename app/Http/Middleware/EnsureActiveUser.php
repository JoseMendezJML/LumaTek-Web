<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        if ($user->status !== 'active') {
            return $this->reject($request, 'La cuenta se encuentra inactiva.', 403);
        }

        if (!$this->credentialsAreCurrent($request, $user)) {
            return $this->reject($request, 'Las credenciales han caducado. Inicia sesión nuevamente.', 401);
        }

        return $next($request);
    }

    private function credentialsAreCurrent(Request $request, User $user): bool
    {
        if ($request->hasSession()) {
            $credentials = $request->session()->get(User::AUTH_VERSION_SESSION_KEY);

            if ($credentials === null) {
                // Never upgrade an old session to the user's current version.
                return $user->auth_version === null;
            }

            if (!is_array($credentials)
                || (string) ($credentials['user_id'] ?? '') !== (string) $user->getAuthIdentifier()) {
                return false;
            }

            $version = $credentials['version'] ?? null;
        } else {
            $version = Auth::guard('api')->payload()->get('auth_version') ?? '0';
        }

        return is_string($version) && hash_equals($user->authenticationVersion(), $version);
    }

    private function reject(Request $request, string $message, int $status): Response
    {
        if ($request->hasSession()) {
            // End this browser session without changing the user's credentials.
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if (!$request->hasSession() || $request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return redirect()->route('login')->with('error', $message);
    }
}
