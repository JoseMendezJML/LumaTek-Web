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
                            $validated['source']
                            ?? 'simulation',
                    ]);

                /*
                |--------------------------------------------------------------------------
                | Actualizar última conexión
                |--------------------------------------------------------------------------
                */

                $sensor
                    ->device
                    ->update([
                        'last_connection_at' =>
                            $reading->recorded_at,
                    ]);

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

                $humidity =
                    (float) $reading->value;

                $alert =
                    null;

                $status =
                    'normal';

                if ($threshold) {

                    $maximum =
                        $threshold->max_value !== null
                            ? (float) $threshold->max_value
                            : null;

                    /*
                    |--------------------------------------------------------------------------
                    | CA04 - Humedad superior al máximo
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $maximum !== null
                        && $humidity > $maximum
                    ) {

                        $status =
                            'high';

                        /*
                        | Evitamos crear varias alertas activas
                        | por la misma condición.
                        */

                        $alert =
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
                                ->first();

                        if (!$alert) {

                            $alert =
                                $sensor
                                    ->alerts()
                                    ->create([
                                        'reading_id' =>
                                            $reading->id,

                                        'type' =>
                                            'high_ambient_humidity',

                                        'severity' =>
                                            'warning',

                                        'message' =>
                                            'La humedad ambiental superó el umbral máximo configurado.',

                                        'status' =>
                                            'active',
                                    ]);
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Valor dentro del rango permitido
                    |--------------------------------------------------------------------------
                    */

                    else {

                        $status =
                            'normal';

                        $this->resolveHighHumidityAlerts(
                            $sensor
                        );
                    }
                }

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

        $isDisconnected =
            $latestReading
                ->recorded_at
                ->lt(
                    now()->subMinutes(10)
                );

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
                    '%',

                'status' =>
                    $status,

                'status_label' =>
                    $status === 'high'
                        ? 'Alta'
                        : 'Normal',

                'connection_status' =>
                    $isDisconnected
                        ? 'disconnected'
                        : 'connected',

                'connection_label' =>
                    $isDisconnected
                        ? 'Sin conexión'
                        : 'Conectado',

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
            ->whereHas(
                'device.zone',
                function ($query) use ($greenhouse) {

                    $query->where(
                        'greenhouse_id',
                        $greenhouse->id
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

        $isDisconnected =
            $latestReading
                ->recorded_at
                ->lt(
                    now()->subMinutes(10)
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
                    '%',

                'status' =>
                    $status,

                'status_label' =>
                    $status === 'high'
                        ? 'Alta'
                        : 'Normal',

                'connection_status' =>
                    $isDisconnected
                        ? 'disconnected'
                        : 'connected',

                'connection_label' =>
                    $isDisconnected
                        ? 'Sin conexión'
                        : 'Conectado',

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