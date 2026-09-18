<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Sensor;
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

        /*
        |--------------------------------------------------------------------------
        | Validar tipo de sensor
        |--------------------------------------------------------------------------
        */

        if ($sensor->sensor_type !== 'soil_humidity') {
            return response()->json([
                'message' => 'El sensor seleccionado no es de humedad del suelo.',
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
                'La humedad del suelo es obligatoria.',

            'value.numeric' =>
                'La humedad debe ser un valor numérico.',

            'value.between' =>
                'La humedad debe estar entre 0 y 100 %.',

            'recorded_at.date' =>
                'La fecha de la lectura no es válida.',

            'recorded_at.before_or_equal' =>
                'La fecha de la lectura no puede ser futura.',

            'source.in' =>
                'La fuente debe ser simulation o iot.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Registrar lectura
        |--------------------------------------------------------------------------
        */

        $result = DB::transaction(
            function () use (
                $validated,
                $sensor,
                $greenhouse
            ) {

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
                    | CA03 - Nivel Bajo
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $minimum !== null
                        && $humidity < $minimum
                    ) {

                        $level =
                            'low';

                        /*
                        |--------------------------------------------------------------------------
                        | CA05 - Alerta por humedad baja
                        |--------------------------------------------------------------------------
                        |
                        | Evitamos generar múltiples alertas activas
                        | para la misma condición.
                        |
                        */

                        $alert =
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
                                ->first();

                        if (!$alert) {

                            $alert =
                                $sensor
                                    ->alerts()
                                    ->create([
                                        'reading_id' =>
                                            $reading->id,

                                        'type' =>
                                            'low_soil_humidity',

                                        'severity' =>
                                            'warning',

                                        'message' =>
                                            'La humedad del suelo está por debajo del umbral mínimo configurado.',

                                        'status' =>
                                            'active',
                                    ]);
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | CA03 - Nivel Alto
                    |--------------------------------------------------------------------------
                    */

                    elseif (
                        $maximum !== null
                        && $humidity > $maximum
                    ) {

                        $level =
                            'high';

                        /*
                        | El criterio de aceptación solo solicita
                        | alerta cuando la humedad cae por debajo
                        | del mínimo.
                        */

                        $this->resolveLowHumidityAlerts(
                            $sensor
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | CA03 - Nivel Medio
                    |--------------------------------------------------------------------------
                    */

                    else {

                        $level =
                            'medium';

                        $this->resolveLowHumidityAlerts(
                            $sensor
                        );
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
                ];
            }
        );

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
                | CA02 - Un decimal
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
                | CA03 - Bajo / Medio / Alto
                |--------------------------------------------------------------------------
                */

                'level' =>
                    $result['level'],

                'level_label' =>
                    $this->levelLabel(
                        $result['level']
                    ),

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
     * Devuelve la humedad actual del sensor.
     */
    public function current(
        Sensor $sensor
    ): JsonResponse {

        $user = auth('api')->user();

        if ($sensor->sensor_type !== 'soil_humidity') {
            return response()->json([
                'message' => 'El sensor seleccionado no es de humedad del suelo.',
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
                    'soil_humidity'
                );

        /*
        |--------------------------------------------------------------------------
        | Sin lecturas
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

                    'level' =>
                        null,

                    'level_label' =>
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

        $level =
            $this->calculateLevel(
                $humidity,
                $threshold
            );

        return response()->json([
            'data' => [
                'sensor_id' =>
                    $sensor->id,

                'sensor_name' =>
                    $sensor->name,

                /*
                |--------------------------------------------------------------------------
                | CA02
                |--------------------------------------------------------------------------
                */

                'humidity' =>
                    round(
                        $humidity,
                        1
                    ),

                'unit' =>
                    '%',

                /*
                |--------------------------------------------------------------------------
                | CA03
                |--------------------------------------------------------------------------
                */

                'level' =>
                    $level,

                'level_label' =>
                    $this->levelLabel(
                        $level
                    ),

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
     * CA04 - Historial de las últimas 24 horas.
     */
    public function history(
        Sensor $sensor
    ): JsonResponse {

        $user = auth('api')->user();

        if ($sensor->sensor_type !== 'soil_humidity') {
            return response()->json([
                'message' => 'El sensor seleccionado no es de humedad del suelo.',
            ], 422);
        }

        $sensor->load(
            'device.zone.greenhouse'
        );

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

        /*
        |--------------------------------------------------------------------------
        | Lecturas de las últimas 24 horas
        |--------------------------------------------------------------------------
        */

        $readings =
            $sensor
                ->readings()
                ->where(
                    'recorded_at',
                    '>=',
                    now()->subHours(24)
                )
                ->orderBy(
                    'recorded_at'
                )
                ->get()
                ->map(function ($reading) {

                    return [
                        'id' =>
                            $reading->id,

                        'humidity' =>
                            round(
                                (float) $reading->value,
                                1
                            ),

                        'unit' =>
                            '%',

                        'recorded_at' =>
                            $reading
                                ->recorded_at
                                ->toDateTimeString(),

                        'source' =>
                            $reading->source,
                    ];
                });

        return response()->json([
            'data' => [
                'sensor_id' =>
                    $sensor->id,

                'sensor_name' =>
                    $sensor->name,

                'period_hours' =>
                    24,

                'total_readings' =>
                    $readings->count(),

                'readings' =>
                    $readings,
            ],
        ]);
    }

    /**
     * Calcula el nivel visual Bajo / Medio / Alto.
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
            && $humidity < $minimum
        ) {
            return 'low';
        }

        if (
            $maximum !== null
            && $humidity > $maximum
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
 * Devuelve la humedad del suelo actual de un invernadero.
 */
public function currentByGreenhouse(
    \App\Models\Greenhouse $greenhouse
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
    | Buscar sensor de humedad del suelo
    |--------------------------------------------------------------------------
    */

    $sensor = Sensor::query()
        ->where('sensor_type', 'soil_humidity')
        ->where('status', 'active')
        ->whereHas('device.zone', function ($query) use ($greenhouse) {

            $query->where(
                'greenhouse_id',
                $greenhouse->id
            );

        })
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
                'sensor_id' => null,
                'sensor_name' => null,
                'humidity' => null,
                'unit' => '%',
                'level' => null,
                'level_label' => 'Sin sensor',
                'connection_status' => 'no_sensor',
                'connection_label' => 'Sin sensor',
                'recorded_at' => null,
                'minutes_since_last_reading' => null,
                'threshold' => null,
            ],
        ]);
    }

    $threshold =
        $greenhouse
            ->thresholds()
            ->where(
                'variable',
                'soil_humidity'
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

                'level' =>
                    null,

                'level_label' =>
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

    $level =
        $this->calculateLevel(
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

            'level' =>
                $level,

            'level_label' =>
                $this->levelLabel(
                    $level
                ),

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
 * Devuelve el historial de humedad del suelo
 * de las últimas 24 horas de un invernadero.
 */
public function historyByGreenhouse(
    \App\Models\Greenhouse $greenhouse
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
    | Buscar sensor
    |--------------------------------------------------------------------------
    */

    $sensor = Sensor::query()
        ->where('sensor_type', 'soil_humidity')
        ->where('status', 'active')
        ->whereHas('device.zone', function ($query) use ($greenhouse) {

            $query->where(
                'greenhouse_id',
                $greenhouse->id
            );

        })
        ->first();

    if (!$sensor) {

        return response()->json([
            'data' => [
                'sensor_id' => null,
                'sensor_name' => null,
                'period_hours' => 24,
                'total_readings' => 0,
                'readings' => [],
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CA04 - Últimas 24 horas
    |--------------------------------------------------------------------------
    */

    $readings =
        $sensor
            ->readings()
            ->where(
                'recorded_at',
                '>=',
                now()->subHours(24)
            )
            ->orderBy(
                'recorded_at'
            )
            ->get()
            ->map(function ($reading) {

                return [
                    'id' =>
                        $reading->id,

                    'humidity' =>
                        round(
                            (float) $reading->value,
                            1
                        ),

                    'unit' =>
                        '%',

                    'recorded_at' =>
                        $reading
                            ->recorded_at
                            ->toDateTimeString(),

                    'source' =>
                        $reading->source,
                ];
            });

    return response()->json([
        'data' => [
            'sensor_id' =>
                $sensor->id,

            'sensor_name' =>
                $sensor->name,

            'period_hours' =>
                24,

            'total_readings' =>
                $readings->count(),

            'readings' =>
                $readings,
        ],
    ]);
}
}