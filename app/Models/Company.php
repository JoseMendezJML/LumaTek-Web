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

    public function owners(): HasMany
    {
        return $this->users()->where('is_company_owner', true)
            ->whereHas('role', fn ($query) => $query->where('name', 'company_admin'));
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
     * SuscripciÃ³n activa actual de la empresa.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->ofMany(['id' => 'max'], fn ($query) => $query->current());
    }

    public function effectivePlan(): ?Plan
    {
        app(\App\Services\SubscriptionContractIntegrity::class)->assertSubscriptions($this->id);
        $subscription = $this->activeSubscription()->with('contract')->first();
        if ($subscription) {
            // Never fall back to editable catalog limits for a contracted period.
            return $subscription->contract->effectivePlan();
        }

        return Plan::where('slug', 'free')->where('is_active', true)->first();
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
    | Determinar cuÃ¡les exceden el plan actual
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

    public function syncZonePlanRestrictions(): void
{
    $plan = $this->effectivePlan();

    if (!$plan) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Sincronizar primero los invernaderos
    |--------------------------------------------------------------------------
    */

    $this->syncGreenhousePlanRestrictions();

    /*
    |--------------------------------------------------------------------------
    | Quitar restricciones anteriores de las zonas
    |--------------------------------------------------------------------------
    |
    | Esto permite recuperar las zonas cuando la empresa renueva Pro.
    |
    */

    Zone::query()
        ->whereHas(
            'greenhouse',
            function ($query) {
                $query->where(
                    'company_id',
                    $this->id
                );
            }
        )
        ->update([
            'plan_restricted' => false,
        ]);

    /*
    |--------------------------------------------------------------------------
    | Obtener invernaderos activos
    |--------------------------------------------------------------------------
    */

    $greenhouses = $this
        ->greenhouses()
        ->where('status', 'active')
        ->orderBy('id')
        ->get();

    foreach ($greenhouses as $greenhouse) {

        /*
        |--------------------------------------------------------------------------
        | Invernadero restringido
        |--------------------------------------------------------------------------
        |
        | Si todo el invernadero estÃ¡ restringido, sus zonas activas tambiÃ©n.
        |
        */

        if ($greenhouse->plan_restricted) {

            $greenhouse
                ->zones()
                ->where('status', 'active')
                ->update([
                    'plan_restricted' => true,
                ]);

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Zonas activas del invernadero disponible
        |--------------------------------------------------------------------------
        */

        $activeZones = $greenhouse
            ->zones()
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Zonas que exceden el lÃ­mite del plan
        |--------------------------------------------------------------------------
        */

        $restrictedIds = $activeZones
            ->skip($plan->max_zones_per_greenhouse)
            ->pluck('id');

        if ($restrictedIds->isEmpty()) {
            continue;
        }

        $greenhouse
            ->zones()
            ->whereIn('id', $restrictedIds)
            ->update([
                'plan_restricted' => true,
            ]);
    }
}

    public function syncSensorPlanRestrictions(): void
{
    $plan = $this->effectivePlan();

    if (!$plan) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Sincronizar primero invernaderos y zonas
    |--------------------------------------------------------------------------
    */

    $this->syncZonePlanRestrictions();

    /*
    |--------------------------------------------------------------------------
    | Quitar restricciones anteriores de sensores
    |--------------------------------------------------------------------------
    |
    | Esto permite recuperar los sensores si la empresa renueva Pro.
    |
    */

    Sensor::query()
        ->whereHas(
            'device.zone.greenhouse',
            function ($query) {
                $query->where(
                    'company_id',
                    $this->id
                );
            }
        )
        ->update([
            'plan_restricted' => false,
        ]);

    /*
    |--------------------------------------------------------------------------
    | Obtener zonas activas de la empresa
    |--------------------------------------------------------------------------
    */

    $zones = Zone::query()
        ->where('status', 'active')
        ->whereHas(
            'greenhouse',
            function ($query) {
                $query->where(
                    'company_id',
                    $this->id
                );
            }
        )
        ->with('greenhouse')
        ->orderBy('id')
        ->get();

    foreach ($zones as $zone) {

        /*
        |--------------------------------------------------------------------------
        | Zona o invernadero restringido
        |--------------------------------------------------------------------------
        */

        if (
            $zone->plan_restricted
            ||
            $zone->greenhouse->plan_restricted
        ) {

            Sensor::query()
                ->where('status', 'active')
                ->whereHas(
                    'device',
                    function ($query) use ($zone) {
                        $query->where(
                            'zone_id',
                            $zone->id
                        );
                    }
                )
                ->update([
                    'plan_restricted' => true,
                ]);

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Sensores activos de la zona
        |--------------------------------------------------------------------------
        */

        $activeSensors = Sensor::query()
            ->where('status', 'active')
            ->whereHas(
                'device',
                function ($query) use ($zone) {
                    $query->where(
                        'zone_id',
                        $zone->id
                    );
                }
            )
            ->orderBy('id')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Sensores que exceden el lÃ­mite del plan
        |--------------------------------------------------------------------------
        */

        $restrictedIds = $activeSensors
            ->skip($plan->max_sensors_per_zone)
            ->pluck('id');

        if ($restrictedIds->isEmpty()) {
            continue;
        }

        Sensor::query()
            ->whereIn('id', $restrictedIds)
            ->update([
                'plan_restricted' => true,
            ]);
    }
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
