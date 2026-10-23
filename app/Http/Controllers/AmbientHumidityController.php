<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Greenhouse;
use App\Models\Sensor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AmbientHumidityController extends Controller
{
    /**
     * Registra una nueva lectura de humedad ambiental.
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

        if ($sensor->sensor_type !== 'ambient_humidity') {
            return response()->json([
                'message' => 'El sensor seleccionado no es de humedad ambiental.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Cargar relaciones y validar empresa
        |--------------------------------------------------------------------------
        */

        $sensor->load(
            'device.zone.greenhouse.thresholds'
        );

        $greenhouse =
            $sensor
            ->device
            ->zone
            ->greenhouse;

        if ($greenhouse->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'No tienes permiso para registrar lecturas en este sensor.',
            ], 403);
        }

        if ($greenhouse->plan_restricted) {
            return response()->json([
                'message' =>
                'Este invernadero está restringido por el plan actual. '
                    . 'Renueva Pro para continuar registrando lecturas.',
            ], 403);
        }

        if ($sensor->device->zone->plan_restricted) {
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
        | Validar lectura
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'value' => [
                'required',
                'numeric',
                'between:0,100',
            ],

            'recorded_at' => [
                'nullable',
                'date',
                'before_or_equal:now',
            ],

            'source' => [
                'nullable',
                'in:simulation,iot',
            ],
        ], [
            'value.required' =>
            'La humedad ambiental es obligatoria.',

            'value.numeric' =>
            'La humedad ambiental debe ser un valor numérico.',

            'value.between' =>
            'La humedad ambiental debe estar entre 0 y 100 %.',

            'recorded_at.date' =>
            'La fecha de la lectura no es válida.',

            'recorded_at.before_or_equal' =>
            'La fecha de la lectura no puede ser futura.',

            'source.in' =>
            'La fuente debe ser simulation o iot.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Registrar lectura y comprobar umbral
        |--------------------------------------------------------------------------
        */

        $result = DB::transaction(
            function () use (
                $validated,
                $sensor,
                $greenhouse
            ) {

                /*
                |--------------------------------------------------------------------------
                | CA03 - Guardar lectura con marca de tiempo
                |--------------------------------------------------------------------------
                */

                $reading =
                    $sensor->readings()->create([
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
                | Buscar umbral
                |--------------------------------------------------------------------------
                */

                $threshold =
                    $greenhouse
                    ->thresholds
                    ->firstWhere(
                        'variable',
                        'ambient_humidity'
                    );

                $humidity = (float) $reading->value;
                $status = $threshold && $threshold->max_value !== null && $humidity > (float) $threshold->max_value ? 'high' : 'normal';
                $alert = app(\App\Services\ReadingAlertEvaluator::class)->evaluate($sensor, $reading);

                return [
                    'reading' =>
                    $reading,

                    'threshold' =>
                    $threshold,

                    'status' =>
                    $status,

                    'alert' =>
                    $alert,
                ];
            }
        );

        return response()->json([
            'message' =>
            'Lectura de humedad ambiental registrada correctamente.',

            'data' => [
                'sensor_id' =>
                $sensor->id,

                'sensor_name' =>
                $sensor->name,

                /*
                |--------------------------------------------------------------------------
                | CA02 - Porcentaje con un decimal
                |--------------------------------------------------------------------------
                */

                'humidity' =>
                round(
                    (float) $result['reading']->value,
                    1
                ),

                'unit' =>
                '%',

                'status' =>
                $result['status'],

                'status_label' =>
                $result['status'] === 'high'
                    ? 'Alta'
                    : 'Normal',

                'recorded_at' =>
                $result['reading']
                    ->recorded_at
                    ->toDateTimeString(),

                'source' =>
                $result['reading']->source,

                'threshold' =>
                $result['threshold']
                    ? [
                        'min' =>
                        (float) $result['threshold']->min_value,

                        'max' =>
                        (float) $result['threshold']->max_value,

                        'unit' =>
                        $result['threshold']->unit,
                    ]
                    : null,

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
            ],
        ], 201);
    }

    /**
     * Devuelve la humedad ambiental actual de un sensor.
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

        if ($sensor->sensor_type !== 'ambient_humidity') {
            return response()->json([
                'message' => 'El sensor seleccionado no es de humedad ambiental.',
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

        if ($greenhouse->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'No tienes permiso para consultar este sensor.',
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
                    'sensor_id' => $sensor->id,
                    'sensor_name' => $sensor->name,
                    'humidity' => null,
                    'unit' => '%',
                    'status' => null,
                    'status_label' => 'Restringido',
                    'connection_status' => 'restricted',
                    'connection_label' => 'Restringido',
                    'recorded_at' => null,
                    'minutes_since_last_reading' => null,
                    'threshold' => null,
                    'restricted_message' =>
                    'Monitoreo restringido por el plan actual.',
                ],
            ]);
        }

        $latestReading =
            $sensor->latestReading;

        $threshold =
            $greenhouse
            ->thresholds
            ->firstWhere(
                'variable',
                'ambient_humidity'
            );

        /*
        |--------------------------------------------------------------------------
        | Sensor sin lecturas
        |--------------------------------------------------------------------------
        */

        if (!$latestReading) {

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

                    'status' =>
                    null,

                    'status_label' =>
                    'Sin datos',

                    'connection_status' =>
                    'no_data',

                    'connection_label' =>
                    'Sin datos',

                    'recorded_at' =>
                    null,

                    'minutes_since_last_reading' =>
                    null,

                    'threshold' =>
                    $threshold
                        ? [
                            'min' =>
                            (float) $threshold->min_value,

                            'max' =>
                            (float) $threshold->max_value,

                            'unit' =>
                            $threshold->unit,
                        ]
                        : null,
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Estado de conexión
        |--------------------------------------------------------------------------
        */


        $humidity =
            (float) $latestReading->value;

        $status =
            $this->calculateStatus(
                $humidity,
                $threshold
            );

        return response()->json([
            'data' => [
                'sensor_id' =>
                $sensor->id,

                'sensor_name' =>
                $sensor->name,

                'humidity' =>
                round(
                    $humidity,
                    1
                ),

                'unit' =>
                $latestReading?->unit ?? $sensor->unit,

                'status' =>
                $status,

                'status_label' =>
                $status === 'high'
                    ? 'Alta'
                    : 'Normal',

                'connection_status' =>
                $latestReading->connectionStatus($sensor->device->measurement_interval, $sensor),

                'connection_label' =>
                $latestReading->connectionLabel($sensor->device->measurement_interval, $sensor),

                ...$latestReading->connectionDetails($sensor),
                'source' => $latestReading->source,
                'provenance' => $latestReading->provenance,
                'source_label' => $latestReading->source_label,

                'recorded_at' =>
                $latestReading
                    ->recorded_at
                    ->toDateTimeString(),

                'minutes_since_last_reading' =>
                (int) $latestReading
                    ->recorded_at
                    ->diffInMinutes(
                        now()
                    ),

                'threshold' =>
                $threshold
                    ? [
                        'min' =>
                        (float) $threshold->min_value,

                        'max' =>
                        (float) $threshold->max_value,

                        'unit' =>
                        $threshold->unit,
                    ]
                    : null,
            ],
        ]);
    }

    /**
     * Devuelve la humedad ambiental actual de un invernadero.
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

        if ($greenhouse->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'No tienes permiso para consultar este invernadero.',
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

                    'status' =>
                    null,

                    'status_label' =>
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

        /*
        |--------------------------------------------------------------------------
        | Buscar sensor de humedad ambiental
        |--------------------------------------------------------------------------
        */

        $sensor = Sensor::query()
            ->where(
                'sensor_type',
                'ambient_humidity'
            )
            ->where(
                'status',
                'active'
            )
            ->where(
                'plan_restricted',
                false
            )
            ->whereHas(
                'device',
                function ($query) {

                    $query->where(
                        'status',
                        'active'
                    );
                }
            )
            ->whereHas(
                'device.zone',
                function ($query) use ($greenhouse) {

                    $query
                        ->where(
                            'greenhouse_id',
                            $greenhouse->id
                        )
                        ->where(
                            'status',
                            'active'
                        )
                        ->where(
                            'plan_restricted',
                            false
                        );
                }
            )
            ->with([
                'latestReading',
            ])
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Sin sensor
        |--------------------------------------------------------------------------
        */

        if (!$sensor) {

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

                    'status' =>
                    null,

                    'status_label' =>
                    'Sin sensor',

                    'connection_status' =>
                    'no_sensor',

                    'connection_label' =>
                    'Sin sensor',

                    'recorded_at' =>
                    null,

                    'minutes_since_last_reading' =>
                    null,

                    'threshold' =>
                    null,
                ],
            ]);
        }

        $threshold =
            $greenhouse
            ->thresholds()
            ->where(
                'variable',
                'ambient_humidity'
            )
            ->first();

        $latestReading =
            $sensor->latestReading;

        /*
        |--------------------------------------------------------------------------
        | Sensor sin lecturas
        |--------------------------------------------------------------------------
        */

        if (!$latestReading) {

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

                    'status' =>
                    null,

                    'status_label' =>
                    'Sin datos',

                    'connection_status' =>
                    'no_data',

                    'connection_label' =>
                    'Sin datos',

                    'recorded_at' =>
                    null,

                    'minutes_since_last_reading' =>
                    null,

                    'threshold' =>
                    $threshold
                        ? [
                            'min' =>
                            (float) $threshold->min_value,

                            'max' =>
                            (float) $threshold->max_value,

                            'unit' =>
                            $threshold->unit,
                        ]
                        : null,
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Estado actual
        |--------------------------------------------------------------------------
        */

        $humidity =
            (float) $latestReading->value;

        $status =
            $this->calculateStatus(
                $humidity,
                $threshold
            );


        return response()->json([
            'data' => [
                'sensor_id' =>
                $sensor->id,

                'sensor_name' =>
                $sensor->name,

                'humidity' =>
                round(
                    $humidity,
                    1
                ),

                'unit' =>
                $latestReading?->unit ?? $sensor->unit,

                'status' =>
                $status,

                'status_label' =>
                $status === 'high'
                    ? 'Alta'
                    : 'Normal',

                'connection_status' =>
                $latestReading->connectionStatus($sensor->device->measurement_interval, $sensor),

                'connection_label' =>
                $latestReading->connectionLabel($sensor->device->measurement_interval, $sensor),

                ...$latestReading->connectionDetails($sensor),
                'source' => $latestReading->source,
                'provenance' => $latestReading->provenance,
                'source_label' => $latestReading->source_label,

                'recorded_at' =>
                $latestReading
                    ->recorded_at
                    ->toDateTimeString(),

                'minutes_since_last_reading' =>
                (int) $latestReading
                    ->recorded_at
                    ->diffInMinutes(
                        now()
                    ),

                'threshold' =>
                $threshold
                    ? [
                        'min' =>
                        (float) $threshold->min_value,

                        'max' =>
                        (float) $threshold->max_value,

                        'unit' =>
                        $threshold->unit,
                    ]
                    : null,
            ],
        ]);
    }

    /**
     * Determina si la humedad está normal o alta.
     */
    private function calculateStatus(
        float $humidity,
        $threshold
    ): string {

        if (!$threshold) {
            return 'normal';
        }

        $maximum =
            $threshold->max_value !== null
            ? (float) $threshold->max_value
            : null;

        if (
            $maximum !== null
            && $humidity > $maximum
        ) {
            return 'high';
        }

        return 'normal';
    }

    /**
     * Resuelve alertas activas por humedad ambiental alta.
     */
    private function resolveHighHumidityAlerts(
        Sensor $sensor
    ): void {

        Alert::query()
            ->where(
                'sensor_id',
                $sensor->id
            )
            ->where(
                'type',
                'high_ambient_humidity'
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
}
