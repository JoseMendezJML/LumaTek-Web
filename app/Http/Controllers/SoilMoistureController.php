<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Greenhouse;
use App\Models\IrrigationEvent;
use App\Models\Sensor;
use App\Models\ZoneIrrigationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SoilMoistureController extends Controller
{
    /**
     * Registra una nueva lectura de humedad del suelo.
     */
    public function store(
        Request $request,
        Sensor $sensor
    ): JsonResponse {

        $user = auth('api')->user();

        if (!($user instanceof \App\Models\User)) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $user->loadMissing('company');

        $user
            ->company
            ?->syncSensorPlanRestrictions();

        $sensor->refresh();


        /*
        |--------------------------------------------------------------------------
        | Validar tipo de sensor
        |--------------------------------------------------------------------------
        */

        if ($sensor->sensor_type !== 'soil_humidity') {

            return response()->json([
                'message' =>
                'El sensor seleccionado no es de humedad del suelo.',
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | Cargar relaciones
        |--------------------------------------------------------------------------
        */

        $sensor->load([
            'device.zone.greenhouse.thresholds',
        ]);


        $zone =
            $sensor
            ->device
            ->zone;


        $greenhouse =
            $zone
            ->greenhouse;


        /*
        |--------------------------------------------------------------------------
        | Aislamiento por empresa
        |--------------------------------------------------------------------------
        */

        if (
            $greenhouse->company_id
            !== $user->company_id
        ) {

            return response()->json([
                'message' =>
                'No tienes permiso para registrar lecturas en este sensor.',
            ], 403);
        }

        if ($sensor->unit !== '%') {
            return response()->json(['message' => 'Este endpoint acepta porcentajes calibrados; no admite valores RAW.'], 422);
        }

        if ($greenhouse->plan_restricted) {

            return response()->json([
                'message' =>
                'Este invernadero está restringido por el plan actual. '
                    . 'Renueva Pro para continuar registrando lecturas.',
            ], 403);
        }

        if ($zone->plan_restricted) {

            return response()->json([
                'message' =>
                'Esta zona está restringida por el plan actual. '
                    . 'Renueva Pro para continuar registrando lecturas.',
            ], 403);
        }

        if ($sensor->plan_restricted) {

            return response()->json([
                'message' =>
                'Este sensor está restringido por el plan actual. '
                    . 'Renueva Pro para continuar registrando lecturas.',
            ], 403);
        }


        /*
        |--------------------------------------------------------------------------
        | Sensor, dispositivo y zona activos
        |--------------------------------------------------------------------------
        */

        if ($sensor->status !== 'active') {

            return response()->json([
                'message' =>
                'El sensor de humedad está inactivo.',
            ], 422);
        }


        if ($sensor->device->status !== 'active') {

            return response()->json([
                'message' =>
                'El dispositivo asociado al sensor está inactivo.',
            ], 422);
        }


        if ($zone->status !== 'active') {

            return response()->json([
                'message' =>
                'La zona asociada al sensor está inactiva.',
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | Validación
        |--------------------------------------------------------------------------
        */

        $validated =
            $request->validate([
                'value' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:100',
                ],

                'recorded_at' => [
                    'nullable',
                    'date',
                ],

                'source' => [
                    'nullable',
                    'string',
                    'in:simulation,iot',
                ],
            ]);


        /*
        |--------------------------------------------------------------------------
        | Registrar lectura y evaluar reglas
        |--------------------------------------------------------------------------
        */

        $result = DB::transaction(
            function () use (
                $validated,
                $sensor,
                $greenhouse,
                $zone
            ) {

                /*
                |--------------------------------------------------------------------------
                | Crear lectura
                |--------------------------------------------------------------------------
                */

                $reading =
                    $sensor
                    ->readings()
                    ->create([
                        'value' =>
                        $validated['value'],

                        'recorded_at' =>
                        $validated['recorded_at']
                            ?? now(),

                        'source' =>
                        'simulation',
                    ]);


                /*
                |--------------------------------------------------------------------------
                | La captura manual no actualiza la comunicación IoT
                |--------------------------------------------------------------------------
                */




                /*
                |--------------------------------------------------------------------------
                | Buscar umbral de humedad del suelo
                |--------------------------------------------------------------------------
                */

                $threshold =
                    $greenhouse
                    ->thresholds
                    ->firstWhere(
                        'variable',
                        'soil_humidity'
                    );


                $humidity =
                    (float) $reading->value;


                $level =
                    'medium';


                $alert =
                    null;


                $automaticIrrigation =
                    null;


                /*
                |--------------------------------------------------------------------------
                | Evaluar umbrales
                |--------------------------------------------------------------------------
                */

                if ($threshold) {

                    $minimum =
                        $threshold->min_value !== null
                        ? (float) $threshold->min_value
                        : null;


                    $maximum =
                        $threshold->max_value !== null
                        ? (float) $threshold->max_value
                        : null;


                    /*
                    |--------------------------------------------------------------------------
                    | HUMEDAD BAJA
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $minimum !== null
                        &&
                        $humidity < $minimum
                    ) {

                        $level =
                            'low';


                        /*
                        |--------------------------------------------------------------------------
                        | Crear alerta si no existe una activa
                        |--------------------------------------------------------------------------
                        */

                        $alert = app(\App\Services\ReadingAlertEvaluator::class)->evaluate($sensor, $reading);

                        /*
                        |--------------------------------------------------------------------------
                        | Intentar iniciar riego automático
                        |--------------------------------------------------------------------------
                        */

                        $automaticIrrigation =
                            $this->tryStartAutomaticIrrigation(
                                $zone->id,
                                $reading->id,
                                $humidity,
                                $reading->recorded_at
                            );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | HUMEDAD ALTA
                    |--------------------------------------------------------------------------
                    */ elseif (
                        $maximum !== null
                        &&
                        $humidity > $maximum
                    ) {

                        $level =
                            'high';


                        app(\App\Services\ReadingAlertEvaluator::class)->evaluate($sensor, $reading);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | HUMEDAD MEDIA
                    |--------------------------------------------------------------------------
                    */ else {

                        $level =
                            'medium';


                        app(\App\Services\ReadingAlertEvaluator::class)->evaluate($sensor, $reading);
                    }
                }


                return [
                    'reading' =>
                    $reading,

                    'threshold' =>
                    $threshold,

                    'level' =>
                    $level,

                    'alert' =>
                    $alert,

                    'automatic_irrigation' =>
                    $automaticIrrigation,
                ];
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Respuesta
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'message' =>
            'Lectura de humedad del suelo registrada correctamente.',

            'data' => [

                'sensor_id' =>
                $sensor->id,

                'sensor_name' =>
                $sensor->name,


                /*
                |--------------------------------------------------------------------------
                | Humedad
                |--------------------------------------------------------------------------
                */

                'humidity' =>
                round(
                    (float) $result['reading']->value,
                    1
                ),

                'unit' =>
                '%',


                /*
                |--------------------------------------------------------------------------
                | Nivel
                |--------------------------------------------------------------------------
                */

                'level' =>
                $result['level'],

                'level_label' =>
                $this->levelLabel(
                    $result['level']
                ),


                /*
                |--------------------------------------------------------------------------
                | Lectura
                |--------------------------------------------------------------------------
                */

                'recorded_at' =>
                $result['reading']
                    ->recorded_at
                    ->toDateTimeString(),

                'source' =>
                $result['reading']->source,


                /*
                |--------------------------------------------------------------------------
                | Umbral
                |--------------------------------------------------------------------------
                */

                'threshold' =>
                $result['threshold']
                    ? [
                        'min' =>
                        $result['threshold']->min_value !== null
                            ? (float) $result['threshold']->min_value
                            : null,

                        'max' =>
                        $result['threshold']->max_value !== null
                            ? (float) $result['threshold']->max_value
                            : null,

                        'unit' =>
                        $result['threshold']->unit,
                    ]
                    : null,


                /*
                |--------------------------------------------------------------------------
                | Alerta
                |--------------------------------------------------------------------------
                */

                'alert' =>
                $result['alert']
                    ? [
                        'id' =>
                        $result['alert']->id,

                        'type' =>
                        $result['alert']->type,

                        'severity' =>
                        $result['alert']->severity,

                        'message' =>
                        $result['alert']->message,
                    ]
                    : null,


                /*
                |--------------------------------------------------------------------------
                | Riego automático
                |--------------------------------------------------------------------------
                */

                'automatic_irrigation' =>
                $result['automatic_irrigation']
                    ? [
                        'started' =>
                        true,

                        'id' =>
                        $result['automatic_irrigation']->id,

                        'status' =>
                        $result['automatic_irrigation']->status,

                        'mode' =>
                        $result['automatic_irrigation']->mode,

                        'duration_minutes' =>
                        $result['automatic_irrigation']->duration_minutes,

                        'water_liters' =>
                        $result['automatic_irrigation']->water_liters !== null
                            ? (float) $result['automatic_irrigation']->water_liters
                            : null,

                        'soil_humidity_before' =>
                        $result['automatic_irrigation']->soil_humidity_before !== null
                            ? (float) $result['automatic_irrigation']->soil_humidity_before
                            : null,

                        'started_at' =>
                        $result['automatic_irrigation']
                            ->started_at
                            ?->toDateTimeString(),
                    ]
                    : [
                        'started' =>
                        false,
                    ],
            ],
        ], 201);
    }


    /**
     * Intenta iniciar un evento de riego automático.
     *
     * Solo lo crea cuando:
     * - la configuración existe;
     * - el automático está activado;
     * - no existe otro riego en curso;
     * - ya pasó el cooldown configurado.
     */
    private function tryStartAutomaticIrrigation(
        int $zoneId,
        int $readingId,
        float $humidity,
        $recordedAt
    ): ?IrrigationEvent {


        $zone = \App\Models\Zone::query()
            ->with('greenhouse')
            ->find($zoneId);

        if (!$zone) {
            return null;
        }

        if (
            $zone->status !== 'active'
            ||
            $zone->plan_restricted
            ||
            !$zone->greenhouse
            ||
            $zone->greenhouse->status !== 'active'
            ||
            $zone->greenhouse->plan_restricted
        ) {
            return null;
        }
        /*
        |--------------------------------------------------------------------------
        | Configuración
        |--------------------------------------------------------------------------
        */

        $setting =
            ZoneIrrigationSetting::query()
            ->where(
                'zone_id',
                $zoneId
            )
            ->lockForUpdate()
            ->first();


        /*
        |--------------------------------------------------------------------------
        | No configurado o desactivado
        |--------------------------------------------------------------------------
        */

        if (
            !$setting
            ||
            !$setting->automatic_enabled
        ) {

            return null;
        }


        /*
        |--------------------------------------------------------------------------
        | Evitar dos riegos simultáneos
        |--------------------------------------------------------------------------
        |
        | Esto considera tanto un riego manual como automático.
        |
        */

        $activeIrrigation =
            IrrigationEvent::query()
            ->where(
                'zone_id',
                $zoneId
            )
            ->where(
                'status',
                'started'
            )
            ->exists();


        if ($activeIrrigation) {

            return null;
        }


        /*
        |--------------------------------------------------------------------------
        | Cooldown
        |--------------------------------------------------------------------------
        */

        $lastAutomaticIrrigation =
            IrrigationEvent::query()
            ->where(
                'zone_id',
                $zoneId
            )
            ->where(
                'mode',
                'automatic'
            )
            ->orderByDesc(
                'started_at'
            )
            ->first();


        if ($lastAutomaticIrrigation) {

            $nextAllowedAt =
                $lastAutomaticIrrigation
                ->started_at
                ->copy()
                ->addMinutes(
                    $setting->cooldown_minutes
                );


            if (now()->lt($nextAllowedAt)) {

                return null;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Crear riego automático
        |--------------------------------------------------------------------------
        */

        return IrrigationEvent::create([
            'zone_id' =>
            $zoneId,

            /*
            | No fue activado manualmente por un usuario.
            */
            'user_id' =>
            null,

            /*
            | Lectura que provocó el riego.
            */
            'trigger_reading_id' =>
            $readingId,

            'mode' =>
            'automatic',

            'status' =>
            'started',

            /*
            | En automático representa la duración programada.
            */
            'duration_minutes' =>
            $setting->duration_minutes,

            'water_liters' =>
            $setting->water_liters,

            'soil_humidity_before' =>
            $humidity,

            'soil_humidity_after' =>
            null,

            'started_at' =>
            $recordedAt ?? now(),

            'ended_at' =>
            null,

            'notes' =>
            'Riego automático iniciado por humedad del suelo por debajo del mínimo configurado.',
        ]);
    }


    /**
     * Devuelve la humedad actual del sensor.
     */
    public function current(
        Sensor $sensor
    ): JsonResponse {

        $user = auth('api')->user();

        if (!($user instanceof \App\Models\User)) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $user->loadMissing('company');

        $user
            ->company
            ?->syncSensorPlanRestrictions();

        $sensor->refresh();


        if (
            $sensor->sensor_type
            !== 'soil_humidity'
        ) {

            return response()->json([
                'message' =>
                'El sensor seleccionado no es de humedad del suelo.',
            ], 422);
        }


        $sensor->load([
            'device.zone.greenhouse.thresholds',
            'latestReading',
        ]);


        $greenhouse =
            $sensor
            ->device
            ->zone
            ->greenhouse;


        /*
        |--------------------------------------------------------------------------
        | Aislamiento por empresa
        |--------------------------------------------------------------------------
        */

        if (
            $greenhouse->company_id
            !== $user->company_id
        ) {

            return response()->json([
                'message' =>
                'No tienes permiso para consultar este sensor.',
            ], 403);
        }

        if (
            $greenhouse->plan_restricted
            ||
            $sensor->device->zone->plan_restricted
            ||
            $sensor->plan_restricted
        ) {

            return response()->json([
                'data' => [

                    'sensor_id' =>
                    $sensor->id,

                    'sensor_name' =>
                    $sensor->name,

                    'humidity' =>
                    null,

                    'unit' =>
                    '%',

                    'level' =>
                    null,

                    'level_label' =>
                    'Restringido',

                    'connection_status' =>
                    'restricted',

                    'connection_label' =>
                    'Restringido',

                    'recorded_at' =>
                    null,

                    'minutes_since_last_reading' =>
                    null,

                    'threshold' =>
                    null,

                    'restricted_message' =>
                    'Monitoreo restringido por el plan actual.',
                ],
            ]);
        }


        {
            $metric = app(\App\Services\GreenhouseSnapshotService::class)->soilMetricForSensor($sensor);
            return response()->json(['data' => [
                'sensor_id' => $metric['sensor_id'], 'sensor_name' => $metric['sensor_name'],
                'humidity' => $metric['value'], 'unit' => $metric['unit'],
                'level' => $metric['unit'] === 'raw' || $metric['value'] === null ? null : $metric['status'], 'level_label' => $metric['status_label'],
                'connection_status' => $metric['connection_status'], 'connection_label' => $metric['connection_label'],
                'recorded_at' => $metric['recorded_at'], 'threshold' => $metric['unit'] === 'raw' ? null : $this->formatThreshold($greenhouse->thresholds->firstWhere('variable', 'soil_humidity')),
                'measurement_is_stale' => $metric['measurement_is_stale'] ?? false, 'measurement_label' => $metric['measurement_label'] ?? null,
                'last_authenticated_communication_at' => $metric['last_authenticated_communication_at'] ?? null,
                'source' => $metric['source'] ?? null, 'source_label' => $metric['source_label'] ?? 'Sin lecturas',
                'provenance' => $metric['provenance'] ?? null,
                'minutes_since_last_reading' => $metric['recorded_at'] === null ? null :
                    (int) \Carbon\Carbon::parse($metric['recorded_at'])->diffInMinutes(now()),
            ]]);
        }

    }


    /**
     * Historial de las últimas 24 horas de un sensor.
     */
    public function history(Request $request, Sensor $sensor): JsonResponse
    {
        $user = auth('api')->user();
        $owner = $sensor->device->zone->greenhouse;
        abort_unless($owner->company_id === $user->company_id, 403);
        $user->company->syncSensorPlanRestrictions();
        $input = $request->validate(['unit' => ['nullable', 'in:raw,%'], 'page' => ['sometimes', 'integer', 'between:1,100000'], 'per_page' => ['sometimes', 'integer', 'between:1,500']]);
        $history = app(\App\Services\MeasurementHistory::class);
        $result = $history->page($history->query($user->company_id, 'soil_humidity', now()->subDay(), now(), $input + ['sensor_id' => $sensor->id]), $input['page'] ?? 1, $input['per_page'] ?? 200);
        $rows = array_map(fn ($row) => $row + ['humidity' => $row['value']], $result['data']);
        return response()->json(['data' => ['sensor_id' => $sensor->id, 'sensor_name' => $sensor->name, 'period_hours' => 24, 'total_readings' => $result['summary']['total_readings'],
            'readings' => $rows, 'summary' => $result['summary'], 'pagination' => $result['pagination']]]);
    }


    /**
     * Devuelve la humedad del suelo actual de un invernadero.
     */
    public function currentByGreenhouse(
        Greenhouse $greenhouse
    ): JsonResponse {

        $user = auth('api')->user();

        if (!($user instanceof \App\Models\User)) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }


        /*
        |--------------------------------------------------------------------------
        | Aislamiento por empresa
        |--------------------------------------------------------------------------
        */

        if (
            $greenhouse->company_id
            !== $user->company_id
        ) {

            return response()->json([
                'message' =>
                'No tienes permiso para consultar este invernadero.',
            ], 403);
        }

        $user->loadMissing('company');

        $user
            ->company
            ?->syncSensorPlanRestrictions();

        $greenhouse->refresh();

        if ($greenhouse->plan_restricted) {

            return response()->json([
                'data' => [

                    'sensor_id' =>
                    null,

                    'sensor_name' =>
                    null,

                    'humidity' =>
                    null,

                    'unit' =>
                    '%',

                    'level' =>
                    null,

                    'level_label' =>
                    'Restringido',

                    'connection_status' =>
                    'restricted',

                    'connection_label' =>
                    'Restringido',

                    'recorded_at' =>
                    null,

                    'minutes_since_last_reading' =>
                    null,

                    'threshold' =>
                    null,

                    'restricted_message' =>
                    'Monitoreo restringido por el plan actual.',
                ],
            ]);
        }


        $metric = app(\App\Services\GreenhouseSnapshotService::class)->soilMetricForGreenhouse($greenhouse);
        $threshold = $metric['unit'] === 'raw' ? null : $greenhouse->thresholds()
            ->where('variable', 'soil_humidity')->first();

        return response()->json(['data' => [
            'sensor_id' => $metric['sensor_id'],
            'sensor_name' => $metric['sensor_name'],
            'humidity' => $metric['value'],
            'unit' => $metric['unit'],
            'level' => $metric['value'] === null ? null : ($metric['status'] === 'normal' ? 'medium' : $metric['status']),
            'level_label' => $metric['connection_status'] === 'no_sensor' ? 'Sin sensor' : $metric['status_label'],
            'connection_status' => $metric['connection_status'],
            'connection_label' => $metric['connection_label'],
            'measurement_is_stale' => $metric['measurement_is_stale'] ?? false, 'measurement_label' => $metric['measurement_label'] ?? null,
                'last_authenticated_communication_at' => $metric['last_authenticated_communication_at'] ?? null,
                'source' => $metric['source'] ?? null,
            'source_label' => $metric['source_label'] ?? 'Sin lecturas',
            'recorded_at' => $metric['recorded_at'],
            'minutes_since_last_reading' => $metric['recorded_at'] === null ? null :
                (int) \Carbon\Carbon::parse($metric['recorded_at'])->diffInMinutes(now()),
            'threshold' => $this->formatThreshold($threshold),
        ]]);
    }


    /**
     * Historial de humedad del suelo de un invernadero.
     */
    public function historyByGreenhouse(Request $request, Greenhouse $greenhouse): JsonResponse
    {
        $user = auth('api')->user();
        $owner = $greenhouse;
        abort_unless($owner->company_id === $user->company_id, 403);
        $user->company->syncSensorPlanRestrictions();
        $input = $request->validate(['unit' => ['nullable', 'in:raw,%'], 'page' => ['sometimes', 'integer', 'between:1,100000'], 'per_page' => ['sometimes', 'integer', 'between:1,500']]);
        $history = app(\App\Services\MeasurementHistory::class);
        $result = $history->page($history->query($user->company_id, 'soil_humidity', now()->subDay(), now(), $input + ['greenhouse_id' => $greenhouse->id]), $input['page'] ?? 1, $input['per_page'] ?? 200);
        $rows = array_map(fn ($row) => $row + ['humidity' => $row['value']], $result['data']);
        return response()->json(['data' => ['greenhouse_id' => $greenhouse->id, 'period_hours' => 24, 'total_readings' => $result['summary']['total_readings'],
            'readings' => $rows, 'summary' => $result['summary'], 'pagination' => $result['pagination']]]);
    }


    /**
     * Calcula Bajo / Medio / Alto.
     */
    private function calculateLevel(
        float $humidity,
        $threshold
    ): string {

        if (!$threshold) {

            return 'medium';
        }


        $minimum =
            $threshold->min_value !== null
            ? (float) $threshold->min_value
            : null;


        $maximum =
            $threshold->max_value !== null
            ? (float) $threshold->max_value
            : null;


        if (
            $minimum !== null
            &&
            $humidity < $minimum
        ) {

            return 'low';
        }


        if (
            $maximum !== null
            &&
            $humidity > $maximum
        ) {

            return 'high';
        }


        return 'medium';
    }


    /**
     * Traduce el nivel para la interfaz.
     */
    private function levelLabel(
        string $level
    ): string {

        return match ($level) {

            'low' =>
            'Bajo',

            'high' =>
            'Alto',

            default =>
            'Medio',
        };
    }


    /**
     * Resuelve alertas activas de humedad baja.
     */
    private function resolveLowHumidityAlerts(
        Sensor $sensor
    ): void {

        Alert::query()
            ->where(
                'sensor_id',
                $sensor->id
            )
            ->where(
                'type',
                'low_soil_humidity'
            )
            ->where(
                'status',
                'active'
            )
            ->update([
                'status' =>
                'resolved',

                'resolved_at' =>
                now(),
            ]);
    }


    /**
     * Formatea el umbral.
     */
    private function formatThreshold(
        $threshold
    ): ?array {

        if (!$threshold) {

            return null;
        }


        return [
            'min' =>
            $threshold->min_value !== null
                ? (float) $threshold->min_value
                : null,

            'max' =>
            $threshold->max_value !== null
                ? (float) $threshold->max_value
                : null,

            'unit' =>
            $threshold->unit,
        ];
    }
}
