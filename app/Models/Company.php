<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'email',
        'phone',
        'status',
    ];

    /**
     * Usuarios que pertenecen a la empresa.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Invernaderos que pertenecen a la empresa.
     */
    public function greenhouses(): HasMany
    {
        return $this->hasMany(Greenhouse::class);
    }

    /**
     * Historial de suscripciones de la empresa.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(
            Subscription::class
        );
    }


    /**
     * Suscripción activa actual de la empresa.
     */
    public function activeSubscription(): HasOne
    {
        return $this
            ->hasOne(Subscription::class)
            ->where('status', 'active')
            ->latestOfMany();
    }

    public function effectivePlan(): ?Plan
    {
        $subscription =
            $this
            ->activeSubscription()
            ->with('plan')
            ->first();

        /*
    |--------------------------------------------------------------------------
    | Sin suscripción activa o con suscripción vencida
    |--------------------------------------------------------------------------
    |
    | La empresa pasa a utilizar las reglas del plan Gratis.
    |
    */

        if (
            !$subscription
            ||
            $subscription->isExpired()
        ) {

            return Plan::query()
                ->where(
                    'slug',
                    'free'
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();
        }

        return $subscription->plan;
    }

    public function syncGreenhousePlanRestrictions(): void
    {
        $plan = $this->effectivePlan();

        if (!$plan) {
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | Obtener invernaderos activos
    |--------------------------------------------------------------------------
    */

        $activeGreenhouses = $this
            ->greenhouses()
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        /*
    |--------------------------------------------------------------------------
    | Primero quitar restricciones anteriores
    |--------------------------------------------------------------------------
    |
    | Esto permite que, si la empresa renueva Pro,
    | sus recursos vuelvan a estar disponibles.
    |
    */

        $this
            ->greenhouses()
            ->update([
                'plan_restricted' => false,
            ]);

        /*
    |--------------------------------------------------------------------------
    | Determinar cuáles exceden el plan actual
    |--------------------------------------------------------------------------
    */

        $restrictedIds = $activeGreenhouses
            ->skip($plan->max_greenhouses)
            ->pluck('id');

        if ($restrictedIds->isEmpty()) {
            return;
        }

        $this
            ->greenhouses()
            ->whereIn('id', $restrictedIds)
            ->update([
                'plan_restricted' => true,
            ]);
    }

    public function syncUserPlanRestrictions(): void
    {
        $plan = $this->effectivePlan();

        if (!$plan) {
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | Limpiar restricciones anteriores
    |--------------------------------------------------------------------------
    */

        $this
            ->users()
            ->update([
                'plan_restricted' => false,
            ]);

        /*
    |--------------------------------------------------------------------------
    | Propietario
    |--------------------------------------------------------------------------
    |
    | El propietario siempre conserva acceso.
    |
    */

        $owner = $this
            ->users()
            ->where(
                'status',
                'active'
            )
            ->where(
                'is_company_owner',
                true
            )
            ->first();

        $availableUsers =
            $owner
            ? 1
            : 0;

        /*
    |--------------------------------------------------------------------------
    | Administradores adicionales
    |--------------------------------------------------------------------------
    */

        $additionalAdmins = $this
            ->users()
            ->where(
                'status',
                'active'
            )
            ->where(
                'is_company_owner',
                false
            )
            ->whereHas(
                'role',
                function ($query) {
                    $query->where(
                        'name',
                        'company_admin'
                    );
                }
            )
            ->orderBy('id')
            ->get();

        $allowedAdmins =
            $additionalAdmins
            ->take(
                $plan->max_additional_admins
            );

        $restrictedAdminIds =
            $additionalAdmins
            ->skip(
                $plan->max_additional_admins
            )
            ->pluck('id');

        if ($restrictedAdminIds->isNotEmpty()) {

            $this
                ->users()
                ->whereIn(
                    'id',
                    $restrictedAdminIds
                )
                ->update([
                    'plan_restricted' => true,
                ]);
        }

        $availableUsers +=
            $allowedAdmins->count();

        /*
    |--------------------------------------------------------------------------
    | Empleados
    |--------------------------------------------------------------------------
    */

        $remainingSlots =
            max(
                0,
                $plan->max_users
                    - $availableUsers
            );

        $employees = $this
            ->users()
            ->where(
                'status',
                'active'
            )
            ->where(
                'is_company_owner',
                false
            )
            ->whereHas(
                'role',
                function ($query) {
                    $query->where(
                        'name',
                        'employee'
                    );
                }
            )
            ->orderBy('id')
            ->get();

        $restrictedEmployeeIds =
            $employees
            ->skip(
                $remainingSlots
            )
            ->pluck('id');

        if ($restrictedEmployeeIds->isNotEmpty()) {

            $this
                ->users()
                ->whereIn(
                    'id',
                    $restrictedEmployeeIds
                )
                ->update([
                    'plan_restricted' => true,
                ]);
        }
    }

    public function isUserRestrictedByPlan(User $user): bool
{
    if ($user->company_id !== $this->id) {
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | El propietario siempre conserva acceso
    |--------------------------------------------------------------------------
    */

    if ($user->is_company_owner) {
        return false;
    }

    $plan = $this->effectivePlan();

    if (!$plan) {
        return true;
    }

    $user->loadMissing('role');

    /*
    |--------------------------------------------------------------------------
    | Administrador adicional
    |--------------------------------------------------------------------------
    */

    if ($user->role?->name === 'company_admin') {

        $adminPosition = $this
            ->users()
            ->where('status', 'active')
            ->where('is_company_owner', false)
            ->whereHas(
                'role',
                function ($query) {
                    $query->where(
                        'name',
                        'company_admin'
                    );
                }
            )
            ->where(
                'id',
                '<=',
                $user->id
            )
            ->count();

        return $adminPosition
            > $plan->max_additional_admins;
    }

    /*
    |--------------------------------------------------------------------------
    | Empleados
    |--------------------------------------------------------------------------
    */

    if ($user->role?->name === 'employee') {

        $ownerCount = $this
            ->users()
            ->where('status', 'active')
            ->where('is_company_owner', true)
            ->count();

        $activeAdditionalAdmins = $this
            ->users()
            ->where('status', 'active')
            ->where('is_company_owner', false)
            ->whereHas(
                'role',
                function ($query) {
                    $query->where(
                        'name',
                        'company_admin'
                    );
                }
            )
            ->count();

        $allowedAdmins = min(
            $activeAdditionalAdmins,
            $plan->max_additional_admins
        );

        $employeeSlots = max(
            0,
            $plan->max_users
            - $ownerCount
            - $allowedAdmins
        );

        $employeePosition = $this
            ->users()
            ->where('status', 'active')
            ->where('is_company_owner', false)
            ->whereHas(
                'role',
                function ($query) {
                    $query->where(
                        'name',
                        'employee'
                    );
                }
            )
            ->where(
                'id',
                '<=',
                $user->id
            )
            ->count();

        return $employeePosition
            > $employeeSlots;
    }

    return false;
}
}
