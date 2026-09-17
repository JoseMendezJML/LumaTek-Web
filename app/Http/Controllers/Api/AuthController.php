<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900; // 15 minutos

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $role = Role::where('name', 'company_admin')
                ->firstOrFail();

            $company = Company::create([
                'name' => $request->company_name,
                'email' => $request->email,
                'status' => 'active',
            ]);

            return User::create([
                'company_id' => $company->id,
                'role_id' => $role->id,
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'status' => 'active',
            ]);
        });

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Registro realizado correctamente. Revisa tu correo para verificar tu cuenta.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $key = $this->loginThrottleKey(
            $request->email,
            $request->ip()
        );

        if (
            RateLimiter::tooManyAttempts(
                $key,
                self::MAX_LOGIN_ATTEMPTS
            )
        ) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Demasiados intentos fallidos. Intenta nuevamente más tarde.',
                'retry_after_seconds' => $seconds,
            ], 429);
        }

        $user = User::where('email', $request->email)->first();

        if (
            !$user ||
            !Hash::check($request->password, $user->password)
        ) {
            RateLimiter::hit(
                $key,
                self::LOCKOUT_SECONDS
            );

            $remaining = RateLimiter::remaining(
                $key,
                self::MAX_LOGIN_ATTEMPTS
            );

            return response()->json([
                'message' => 'Correo o contraseña incorrectos.',
                'attempts_remaining' => $remaining,
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'La cuenta se encuentra inactiva.',
            ], 403);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Debes verificar tu correo electrónico antes de iniciar sesión.',
            ], 403);
        }

        RateLimiter::clear($key);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Inicio de sesión correcto.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company_id' => $user->company_id,
                'role' => $user->role?->name,
            ],
        ]);
    }

    public function forgotPassword(
    ForgotPasswordRequest $request
): JsonResponse {

    $email = strtolower(trim($request->email));

    $status = Password::sendResetLink([
        'email' => $email,
    ]);

    if ($status === Password::RESET_THROTTLED) {
        return response()->json([
            'message' => 'Espera antes de solicitar otro enlace de recuperación.',
        ], 429);
    }

    return response()->json([
        'message' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.',
    ]);
}

    public function resetPassword(
        ResetPasswordRequest $request
    ): JsonResponse {
        $status = Password::reset(
            [
                'email' => $request->email,
                'password' => $request->password,
                'password_confirmation' => $request->password_confirmation,
                'token' => $request->token,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ]);

                $user->setRememberToken(
                    Str::random(60)
                );

                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'La contraseña fue actualizada correctamente.',
            ]);
        }

        return response()->json([
            'message' => 'El enlace o token de recuperación no es válido o ha expirado.',
        ], 422);
    }

    private function loginThrottleKey(
        string $email,
        ?string $ip
    ): string {
        return 'login:'
            . strtolower($email)
            . '|'
            . ($ip ?? 'unknown');
    }
}