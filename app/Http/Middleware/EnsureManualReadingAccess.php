<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Models\Sensor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnsureManualReadingAccess
{
    public function handle(Request $request, Closure $next)
    {
        $sensor = $request->route('sensor');
        $user = auth('api')->user();
        abort_unless($user && $sensor instanceof Sensor, 401);
        abort_unless($sensor->device->zone->greenhouse->company_id === $user->company_id, 403);

        return DB::transaction(function () use ($user, $sensor, $request, $next) {
            $company = $user->company;
            $company->withPlanRestrictionLock(fn () => $company->syncSensorPlanRestrictions());
            Device::whereKey($sensor->device_id)->lockForUpdate()->firstOrFail();
            $sensor = Sensor::whereKey($sensor->id)->lockForUpdate()->firstOrFail();
            $sensor->load('device.zone.greenhouse');
            $zone = $sensor->device->zone;
            abort_if($sensor->plan_restricted || $zone->plan_restricted || $zone->greenhouse->plan_restricted, 403);
            abort_unless($sensor->status === 'active' && $sensor->device->status === 'active'
                && $zone->status === 'active' && $zone->greenhouse->status === 'active', 422, 'El sensor o su ubicación están inactivos.');
            $request->validate([
                'source' => ['nullable', 'in:simulation'],
                'iot_reading_batch_id' => ['prohibited'],
            ]);
            $request->route()->setParameter('sensor', $sensor);

            return $next($request);
        }, 3);
    }
}
