<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reglas compartidas del inicio de sesión con Google (web y móvil).
 *
 * Aplica las mismas validaciones que AuthController para que una
 * cuenta de Google no pueda saltarse el estado de la cuenta, de la
 * empresa ni las restricciones del plan.
 */
class GoogleAccountService
{
    public const LINKED_TO_OTHER_GOOGLE_ACCOUNT =
        'Este correo ya está vinculado a otra cuenta de Google.';

    public const EMAIL_MISMATCH =
        'El correo actual de tu cuenta no coincide con el correo verificado por Google. Inicia sesión con tu correo y contraseña.';

    /**
     * Busca primero por cuenta de Google y después por correo.
     */
    public function findUser(
        string $googleId,
        string $email
    ): ?User {

        return User::where('google_id', $googleId)->first()
            ?? User::where('email', $email)->first();
    }

    /**
     * Devuelve [mensaje, estado HTTP] si la cuenta no puede iniciar
     * sesión, o null si puede continuar.
     */
    public function loginBlockReason(
        User $user,
        string $googleId,
        string $verifiedEmail
    ): ?array {

        if (strcasecmp(trim($user->email), trim($verifiedEmail)) !== 0) {
            return [self::EMAIL_MISMATCH, 409];
        }

        if ($user->google_id && $user->google_id !== $googleId) {
            return [self::LINKED_TO_OTHER_GOOGLE_ACCOUNT, 409];
        }

        if ($user->status !== 'active') {
            return ['La cuenta se encuentra inactiva.', 403];
        }

        $user->loadMissing('role', 'company');

        if ($user->role?->name !== 'superadmin') {

            if ($user->company?->status !== 'active') {
                return ['La empresa se encuentra suspendida.', 403];
            }

            $user->company->syncUserPlanRestrictions();

            $user->refresh();

            if ($user->plan_restricted) {
                return [
                    'Tu cuenta está restringida por el plan actual de la empresa. '
                        .'Contacta al propietario o renueva el plan Pro.',
                    403,
                ];
            }
        }

        return null;
    }

    /**
     * Revalida la identidad bajo bloqueo antes de vincular o verificar.
     */
    public function link(
        User $user,
        string $googleId,
        string $verifiedEmail
    ): User {
        return DB::transaction(function () use ($user, $googleId, $verifiedEmail) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (strcasecmp(trim($user->email), trim($verifiedEmail)) !== 0) {
                throw ValidationException::withMessages(['email' => self::EMAIL_MISMATCH]);
            }

            if ($user->google_id && $user->google_id !== $googleId) {
                throw ValidationException::withMessages(['email' => self::LINKED_TO_OTHER_GOOGLE_ACCOUNT]);
            }

            $user->google_id = $googleId;
            if (! $user->hasVerifiedEmail()) {
                $user->email_verified_at = now();
            }
            $user->save();

            return $user;
        });
    }

    /**
     * Crea la empresa, su administrador propietario y la suscripción
     * Gratis, igual que el registro con correo y contraseña.
     */
    public function registerCompanyOwner(
        string $googleId,
        string $name,
        string $email,
        string $companyName
    ): User {

        return DB::transaction(function () use ($googleId, $name, $email, $companyName) {

            $role =
                Role::where('name', 'company_admin')
                    ->firstOrFail();

            $company = Company::create([
                'name' => $companyName,
                'email' => $email,
                'status' => 'active',
            ]);

            $user = User::create([
                'company_id' => $company->id,
                'role_id' => $role->id,
                'is_company_owner' => true,
                'name' => $name,
                'email' => $email,
                'google_id' => $googleId,

                /*
                 * El usuario entra con Google. Si después quiere
                 * usar contraseña, puede crearla con
                 * "¿Olvidaste tu contraseña?".
                 */
                'password' => Str::random(40),

                'status' => 'active',
            ]);

            $user->email_verified_at = now();
            $user->save();

            $freePlan =
                Plan::where('slug', 'free')
                    ->firstOrFail();

            Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $freePlan->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => null,
                'auto_renew' => false,
            ]);

            return $user;
        });
    }

    /**
     * Pantalla inicial según el rol.
     */
    public function homePath(
        User $user
    ): string {

        $user->loadMissing('role');

        return $user->role?->name === 'superadmin'
            ? '/platform'
            : '/dashboard';
    }
}
