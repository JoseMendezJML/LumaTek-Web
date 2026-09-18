<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Sensor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TemperatureController extends Controller
{
    /**
     * Registra una nueva lectura de temperatura.
     */
    public function store(
        Request $request,
        Sensor $sensor
    ): JsonResponse {

        $user = auth('api')->user();

        /*
        |--------------------------------------------------------------------------
        | Validar que sea un sensor de temperatura
        |--------------------------------------------------------------------------
        */

        if ($sensor->sensor_type !== 'temperature') {
            return response()->json([
                'message' => 'El sensor seleccionado no es de temperatura.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Validar que pertenezca a la empresa autenticada
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
        | Validación de la lectura
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'value' => [
                'required',
                'numeric',
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
                'La temperatura es obligatoria.',

            'value.numeric' =>
                'La temperatura debe ser un valor numérico.',

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
                | Actualizar última conexión del dispositivo
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
                | Obtener umbral de temperatura
                |--------------------------------------------------------------------------
                */

                $threshold =
                    $greenhouse
                        ->thresholds
                        ->firstWhere(
                            'variable',
                            'temperature'
                        );

                $alert = null;

                if ($threshold) {

                    $temperature =
                        (float) $reading->value;

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
                    | Temperatura superior al máximo
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $maximum !== null
                        && $temperature > $maximum
                    ) {

                        $alert =
                            $sensor
                                ->alerts()
                                ->create([
                                    'reading_id' =>
                                        $reading->id,

                                    'type' =>
                                        'high_temperature',

                                    'severity' =>
                                        'warning',

                                    'message' =>
                                        'La temperatura superó el límite máximo configurado.',

                                    'status' =>
                                        'active',
                                ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Temperatura inferior al mínimo
                    |--------------------------------------------------------------------------
                    */

                    elseif (
                        $minimum !== null
                        && $temperature < $minimum
                    ) {

                        $alert =
                            $sensor
                                ->alerts()
                                ->create([
                                    'reading_id' =>
                                        $reading->id,

                                    'type' =>
                                        'low_temperature',

                                    'severity' =>
                                        'warning',

                                    'message' =>
                                        'La temperatura está por debajo del límite mínimo configurado.',

                                    'status' =>
                                        'active',
                                ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Temperatura normal
                    |--------------------------------------------------------------------------
                    |
                    | Si el sensor vuelve a un rango normal,
                    | resolvemos sus alertas térmicas activas.
                    |
                    */

                    else {

                        Alert::query()
                            ->where(
                                'sensor_id',
                                $sensor->id
                            )
                            ->whereIn(
                                'type',
                                [
                                    'high_temperature',
                                    'low_temperature',
                                ]
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

                return [
                    'reading' => $reading,
                    'threshold' => $threshold,
                    'alert' => $alert,
                ];
            }
        );

        return response()->json([
            'message' =>
                'Lectura de temperatura registrada correctamente.',

            'data' => [
                'sensor_id' =>
                    $sensor->id,

                'sensor_name' =>
                    $sensor->name,

                'temperature' =>
                    round(
                        (float) $result['reading']->value,
                        1
                    ),

                'unit' =>
                    $sensor->unit,

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
     * Devuelve la temperatura actual del sensor.
     */
    public function current(
        Sensor $sensor
    ): JsonResponse {

        $user = auth('api')->user();

        if ($sensor->sensor_type !== 'temperature') {
            return response()->json([
                'message' => 'El sensor seleccionado no es de temperatura.',
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
                    'temperature'
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

                    'temperature' =>
                        null,

                    'unit' =>
                        $sensor->unit,

                    'connection_status' =>
                        'no_data',

                    'connection_label' =>
                        'Sin datos',

                    'recorded_at' =>
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
        | CA05 - Sin conexión después de 10 minutos
        |--------------------------------------------------------------------------
        */

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

                /*
                |--------------------------------------------------------------------------
                | CA02 - Un decimal
                |--------------------------------------------------------------------------
                */

                'temperature' =>
                    round(
                        (float) $latestReading->value,
                        1
                    ),

                'unit' =>
                    '°C',

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
 * Devuelve la temperatura actual de un invernadero.
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
    | Buscar sensor de temperatura
    |--------------------------------------------------------------------------
    */

    $sensor = Sensor::query()
        ->where('sensor_type', 'temperature')
        ->where('status', 'active')
        ->whereHas('device.zone', function ($query) use ($greenhouse) {

            $query->where(
                'greenhouse_id',
                $greenhouse->id
            );

        })
        ->with([
            'device.zone.greenhouse.thresholds',
            'latestReading',
        ])
        ->first();

    /*
    |--------------------------------------------------------------------------
    | Invernadero sin sensor de temperatura
    |--------------------------------------------------------------------------
    */

    if (!$sensor) {
        return response()->json([
            'data' => [
                'sensor_id' => null,
                'sensor_name' => null,
                'temperature' => null,
                'unit' => '°C',
                'connection_status' => 'no_sensor',
                'connection_label' => 'Sin sensor',
                'recorded_at' => null,
                'minutes_since_last_reading' => null,
                'threshold' => null,
                'alert_status' => null,
            ],
        ]);
    }

    $latestReading =
        $sensor->latestReading;

    $threshold =
        $greenhouse
            ->thresholds()
            ->where(
                'variable',
                'temperature'
            )
            ->first();

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

                'temperature' =>
                    null,

                'unit' =>
                    '°C',

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

                'alert_status' =>
                    null,
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

    /*
    |--------------------------------------------------------------------------
    | Estado respecto al umbral
    |--------------------------------------------------------------------------
    */

    $temperature =
        (float) $latestReading->value;

    $alertStatus =
        'normal';

    if ($threshold) {

        if (
            $threshold->max_value !== null
            && $temperature >
                (float) $threshold->max_value
        ) {
            $alertStatus =
                'high';
        }

        elseif (
            $threshold->min_value !== null
            && $temperature <
                (float) $threshold->min_value
        ) {
            $alertStatus =
                'low';
        }
    }

    return response()->json([
        'data' => [
            'sensor_id' =>
                $sensor->id,

            'sensor_name' =>
                $sensor->name,

            'temperature' =>
                round(
                    $temperature,
                    1
                ),

            'unit' =>
                '°C',

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

            'alert_status' =>
                $alertStatus,
        ],
    ]);
}
}