<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\User;
use App\Services\IotConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SensorController extends Controller
{
    /**
     * Lista los sensores asociados a un dispositivo.
     */
    public function index(
        Device $device
    ): JsonResponse {

        $user = auth('api')->user();

        if (! ($user instanceof User)) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $user->loadMissing('company');

        $user
            ->company
            ?->syncSensorPlanRestrictions();

        $device->refresh();

        $device->load(
            'zone.greenhouse'
        );

        /*
        |--------------------------------------------------------------------------
        | Aislamiento por empresa
        |--------------------------------------------------------------------------
        */

        if (
            $device
                ->zone
                ->greenhouse
                ->company_id
                !==
                $user->company_id
        ) {

            return response()->json([
                'message' => 'No tienes permiso para consultar este dispositivo.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Obtener sensores
        |--------------------------------------------------------------------------
        */

        $sensors = $device
            ->sensors()
            ->with([
                'latestReading',
                'latestRawReading',
            ])
            ->orderBy('name')
            ->get();

        foreach ($sensors as $sensor) {
            $sensor->setRelation('device', $device);
            $reading = app(\App\Services\MeasurementHistory::class)->latest($sensor);
            $sensor->setAttribute('connection_status', $reading ? $reading->connectionStatus($device->measurement_interval, $sensor) : $device->connection_status);
            $sensor->setAttribute('connection_label', $reading ? $reading->connectionLabel($device->measurement_interval, $sensor) : $device->connection_label);
            if ($reading) $sensor->setAttribute('measurement_details', $reading->connectionDetails($sensor));
            $sensor->unsetRelation('device');
        }

        return response()->json([
            'data' => $sensors,
        ]);
    }

    /**
     * Registra un nuevo sensor.
     */
    public function store(Request $request, Device $device): JsonResponse
    {
        abort_unless($device->zone->greenhouse->company_id === auth('api')->user()->company_id, 403);

        return auth('api')->user()->company->withPlanRestrictionLock(function () use ($request, $device) {
            $device = Device::whereKey($device->id)->lockForUpdate()->firstOrFail();

            return $this->storeLocked($request, $device);
        });
    }

    private function storeLocked(
        Request $request,
        Device $device
    ): JsonResponse {

        $user = auth('api')->user();

        if (! ($user instanceof User)) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $device->load(
            'zone.greenhouse'
        );

        if (
            $device
                ->zone
                ->greenhouse
                ->company_id
                !==
                $user->company_id
        ) {

            return response()->json([
                'message' => 'No tienes permiso para modificar este dispositivo.',
            ], 403);
        }

        $user->loadMissing(
            'company'
        );

        $user
            ->company
            ?->syncSensorPlanRestrictions();

        $device->refresh();

        $plan =
            $user
                ->company
                ?->effectivePlan();

        if (! $plan) {
            return response()->json([
                'message' => 'La empresa no tiene un plan activo configurado.',
            ], 422);
        }

        $device->load(
            'zone.greenhouse'
        );

        $activeSensors =
            Sensor::query()
                ->whereHas(
                    'device',
                    function ($query) use ($device) {
                        $query->where(
                            'zone_id',
                            $device->zone_id
                        );
                    }
                )
                ->where(
                    'status',
                    'active'
                )
                ->count();

        if (
            $activeSensors
            >=
            $plan->max_sensors_per_zone
        ) {
            return response()->json([
                'message' => 'Has alcanzado el límite de sensores activos por zona de tu plan '
                    .$plan->name
                    .'.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Aislamiento por empresa
        |--------------------------------------------------------------------------
        */

        if (
            $device
                ->zone
                ->greenhouse
                ->plan_restricted
        ) {

            return response()->json([
                'message' => 'Este invernadero está restringido por el plan actual. '
                    .'Renueva Pro para administrar sus sensores.',
            ], 403);
        }

        if ($device->zone->plan_restricted) {
            return response()->json([
                'message' => 'Esta zona está restringida por el plan actual. '
                    .'Renueva Pro para agregar sensores.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Validar dispositivo y zona activos
        |--------------------------------------------------------------------------
        */

        if ($device->status !== 'active') {

            return response()->json([
                'message' => 'No puedes agregar sensores a un dispositivo inactivo.',
            ], 422);
        }

        if ($device->zone->status !== 'active') {

            return response()->json([
                'message' => 'No puedes agregar sensores a una zona inactiva.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Validación
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'sensor_code' => [
                'required',
                'string',
                'max:100',
                'unique:sensors,sensor_code',
            ],

            'sensor_type' => [
                'required',
                'in:temperature,soil_humidity,ambient_humidity',
            ],

            'model' => ['required', 'in:DHT22,analog_soil'],
            'pin' => ['required', 'integer'],
            'calibration' => ['nullable', 'array'],

            'position_x' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],

            'position_y' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],
        ], [
            'name.required' => 'El nombre del sensor es obligatorio.',

            'sensor_code.required' => 'El código del sensor es obligatorio.',

            'sensor_code.unique' => 'Ya existe un sensor registrado con ese código.',

            'sensor_type.required' => 'El tipo de sensor es obligatorio.',

            'sensor_type.in' => 'El tipo de sensor seleccionado no es válido.',

            'position_x.between' => 'La posición X debe estar entre 0 y 100.',

            'position_y.between' => 'La posición Y debe estar entre 0 y 100.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Determinar unidad automáticamente
        |--------------------------------------------------------------------------
        */

        $hardware = app(IotConfiguration::class)->validateSensor($device, $validated);
        $unit = $hardware['unit'];

        /*
        |--------------------------------------------------------------------------
        | Crear sensor
        |--------------------------------------------------------------------------
        */

        $sensor = $device
            ->sensors()
            ->create([
                'name' => trim(
                    $validated['name']
                ),

                'sensor_code' => strtoupper(
                    trim(
                        $validated['sensor_code']
                    )
                ),

                'sensor_type' => $validated['sensor_type'],

                'pin' => $hardware['pin'],
                'calibration' => $hardware['calibration'],
                'unit' => $unit,

                'model' => isset($validated['model'])
                    && $validated['model'] !== ''
                    ? trim(
                        $validated['model']
                    )
                    : null,

                'position_x' => $validated['position_x']
                    ?? null,

                'position_y' => $validated['position_y']
                    ?? null,

                'status' => 'active',
            ]);

        return response()->json([
            'message' => 'Sensor registrado correctamente.',

            'data' => $sensor,
        ], 201);
    }

    /**
     * Actualiza un sensor existente.
     */
    public function update(Request $request, Sensor $sensor): JsonResponse
    {
        abort_unless($sensor->device->zone->greenhouse->company_id === auth('api')->user()->company_id, 403);

        return auth('api')->user()->company->withPlanRestrictionLock(function () use ($request, $sensor) {
            Device::whereKey($sensor->device_id)->lockForUpdate()->firstOrFail();

            return $this->updateLocked($request, $sensor->fresh());
        });
    }

    private function updateLocked(
        Request $request,
        Sensor $sensor
    ): JsonResponse {

        $user = auth('api')->user();

        $sensor->load(
            'device.zone.greenhouse'
        );

        /*
        |--------------------------------------------------------------------------
        | Aislamiento por empresa
        |--------------------------------------------------------------------------
        */

        if (
            $sensor
                ->device
                ->zone
                ->greenhouse
                ->company_id
                !==
                $user->company_id
        ) {

            return response()->json([
                'message' => 'No tienes permiso para modificar este sensor.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Validación
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'sensor_code' => [
                'required',
                'string',
                'max:100',
                'unique:sensors,sensor_code,'.$sensor->id,
            ],

            'sensor_type' => [
                'required',
                'in:temperature,soil_humidity,ambient_humidity',
            ],

            'model' => ['required', 'in:DHT22,analog_soil'],
            'pin' => ['required', 'integer'],
            'calibration' => ['nullable', 'array'],

            'position_x' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],

            'position_y' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],

        ], [
            'name.required' => 'El nombre del sensor es obligatorio.',

            'sensor_code.required' => 'El código del sensor es obligatorio.',

            'sensor_code.unique' => 'Ya existe otro sensor registrado con ese código.',

            'sensor_type.required' => 'El tipo de sensor es obligatorio.',

            'sensor_type.in' => 'El tipo de sensor seleccionado no es válido.',

            'position_x.between' => 'La posición X debe estar entre 0 y 100.',

            'position_y.between' => 'La posición Y debe estar entre 0 y 100.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Unidad automática
        |--------------------------------------------------------------------------
        */

        $hardware = app(IotConfiguration::class)->validateSensor($sensor->device, $validated, $sensor);
        $unit = $hardware['unit'];

        /*
        |--------------------------------------------------------------------------
        | Actualizar
        |--------------------------------------------------------------------------
        */

        $sensor->update([
            'name' => trim(
                $validated['name']
            ),

            'sensor_code' => strtoupper(
                trim(
                    $validated['sensor_code']
                )
            ),

            'sensor_type' => $validated['sensor_type'],

            'pin' => $hardware['pin'],
            'calibration' => $hardware['calibration'],
            'unit' => $unit,

            'model' => isset($validated['model'])
                && $validated['model'] !== ''
                ? trim(
                    $validated['model']
                )
                : null,

            'position_x' => $validated['position_x']
                ?? null,

            'position_y' => $validated['position_y']
                ?? null,

        ]);

        return response()->json([
            'message' => 'Sensor actualizado correctamente.',

            'data' => $sensor->fresh(),
        ]);
    }

    /**
     * Activa o desactiva un sensor.
     *
     * No eliminamos físicamente el sensor para conservar
     * lecturas y alertas históricas.
     */
    public function changeStatus(
        Request $request,
        Sensor $sensor
    ): JsonResponse {

        $user = auth('api')->user();

        if (! ($user instanceof User)) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $sensor->load(
            'device.zone.greenhouse'
        );

        /*
    |--------------------------------------------------------------------------
    | Aislamiento por empresa
    |--------------------------------------------------------------------------
    */

        if (
            $sensor
                ->device
                ->zone
                ->greenhouse
                ->company_id
                !==
                $user->company_id
        ) {
            return response()->json([
                'message' => 'No tienes permiso para modificar este sensor.',
            ], 403);
        }

        $user->loadMissing('company');

        /*
    |--------------------------------------------------------------------------
    | Sincronizar restricciones actuales del plan
    |--------------------------------------------------------------------------
    */

        $user
            ->company
            ?->syncSensorPlanRestrictions();

        $sensor->refresh();

        $sensor->load(
            'device.zone.greenhouse'
        );

        $validated = $request->validate([
            'status' => [
                'required',
                'in:active,inactive',
            ],
        ]);

        /*
    |--------------------------------------------------------------------------
    | No activar dentro de un invernadero restringido
    |--------------------------------------------------------------------------
    */

        if (
            $validated['status'] === 'active'
            &&
            $sensor
                ->device
                ->zone
                ->greenhouse
                ->plan_restricted
        ) {
            return response()->json([
                'message' => 'Este invernadero está restringido por el plan actual. '
                    .'Renueva Pro para activar sus sensores.',
            ], 403);
        }

        /*
    |--------------------------------------------------------------------------
    | No activar dentro de una zona restringida
    |--------------------------------------------------------------------------
    */

        if (
            $validated['status'] === 'active'
            &&
            $sensor->device->zone->plan_restricted
        ) {
            return response()->json([
                'message' => 'Esta zona está restringida por el plan actual. '
                    .'Renueva Pro para activar sus sensores.',
            ], 403);
        }

        /*
    |--------------------------------------------------------------------------
    | No activar un sensor restringido por el plan
    |--------------------------------------------------------------------------
    */

        if (
            $validated['status'] === 'active'
            &&
            $sensor->plan_restricted
        ) {
            return response()->json([
                'message' => 'Este sensor está restringido por el plan actual. '
                    .'Renueva Pro para volver a utilizarlo.',
            ], 403);
        }

        /*
    |--------------------------------------------------------------------------
    | Validar límite antes de activar
    |--------------------------------------------------------------------------
    */

        if (
            $validated['status'] === 'active'
            &&
            $sensor->status !== 'active'
        ) {

            $plan = $user
                ->company
                ?->effectivePlan();

            if (! $plan) {
                return response()->json([
                    'message' => 'La empresa no tiene un plan activo configurado.',
                ], 422);
            }

            $activeSensors = Sensor::query()
                ->whereHas(
                    'device',
                    function ($query) use ($sensor) {
                        $query->where(
                            'zone_id',
                            $sensor
                                ->device
                                ->zone_id
                        );
                    }
                )
                ->where(
                    'status',
                    'active'
                )
                ->count();

            if (
                $activeSensors
                >=
                $plan->max_sensors_per_zone
            ) {
                return response()->json([
                    'message' => 'Has alcanzado el límite de sensores activos por zona de tu plan '
                        .$plan->name
                        .'.',
                ], 422);
            }
        }

        /*
    |--------------------------------------------------------------------------
    | No activar sensor si el dispositivo está inactivo
    |--------------------------------------------------------------------------
    */

        if (
            $validated['status'] === 'active'
            &&
            $sensor->device->status !== 'active'
        ) {
            return response()->json([
                'message' => 'No puedes activar el sensor mientras su dispositivo esté inactivo.',
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | No activar sensor si la zona está inactiva
    |--------------------------------------------------------------------------
    */

        if (
            $validated['status'] === 'active'
            &&
            $sensor->device->zone->status !== 'active'
        ) {
            return response()->json([
                'message' => 'No puedes activar el sensor mientras su zona esté inactiva.',
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | Cambiar estado
    |--------------------------------------------------------------------------
    */

        $sensor->update([
            'status' => $validated['status'],
        ]);

        /*
    |--------------------------------------------------------------------------
    | Recalcular restricciones después del cambio
    |--------------------------------------------------------------------------
    */

        $user
            ->company
            ?->syncSensorPlanRestrictions();

        $sensor->refresh();

        return response()->json([
            'message' => $sensor->status === 'active'
                ? 'Sensor activado correctamente.'
                : 'Sensor desactivado correctamente.',

            'data' => $sensor,
        ]);
    }

    /**
     * Obtiene la unidad correspondiente al tipo de sensor.
     */
    private function unitForType(
        string $sensorType
    ): string {

        return match ($sensorType) {

            'temperature' => '°C',

            'soil_humidity' => '%',

            'ambient_humidity' => '%',

            default => '',
        };
    }
}
