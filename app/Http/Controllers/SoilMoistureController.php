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
                    */

                    elseif (
                        $maximum !== null
                        &&
                        $humidity > $maximum
                    ) {

                        $level =
                            'high';


                        $this->resolveLowHumidityAlerts(
                            $sensor
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | HUMEDAD MEDIA
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
                        $this->formatThreshold(
                            $threshold
                        ),
                ],
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Conexión
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
                    $this->formatThreshold(
                        $threshold
                    ),
            ],
        ]);
    }


    /**
     * Historial de las últimas 24 horas de un sensor.
     */
    public function history(
        Sensor $sensor
    ): JsonResponse {

        $user = auth('api')->user();


        if (
            $sensor->sensor_type
            !== 'soil_humidity'
        ) {

            return response()->json([
                'message' =>
                    'El sensor seleccionado no es de humedad del suelo.',
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

        if (
            $greenhouse->company_id
            !== $user->company_id
        ) {

            return response()->json([
                'message' =>
                    'No tienes permiso para consultar este sensor.',
            ], 403);
        }


        /*
        |--------------------------------------------------------------------------
        | Últimas 24 horas
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
                ->map(
                    function ($reading) {

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
                    }
                );


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
     * Devuelve la humedad del suelo actual de un invernadero.
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

        if (
            $greenhouse->company_id
            !== $user->company_id
        ) {

            return response()->json([
                'message' =>
                    'No tienes permiso para consultar este invernadero.',
            ], 403);
        }


        /*
        |--------------------------------------------------------------------------
        | Buscar sensor
        |--------------------------------------------------------------------------
        */

        $sensor =
            Sensor::query()
                ->where(
                    'sensor_type',
                    'soil_humidity'
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

                    'level' =>
                        null,

                    'level_label' =>
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
                        $this->formatThreshold(
                            $threshold
                        ),
                ],
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Conexión
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
                    $this->formatThreshold(
                        $threshold
                    ),
            ],
        ]);
    }


    /**
     * Historial de humedad del suelo de un invernadero.
     */
    public function historyByGreenhouse(
        Greenhouse $greenhouse
    ): JsonResponse {

        $user = auth('api')->user();


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


        /*
        |--------------------------------------------------------------------------
        | Buscar sensor
        |--------------------------------------------------------------------------
        */

        $sensor =
            Sensor::query()
                ->where(
                    'sensor_type',
                    'soil_humidity'
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
                ->first();


        if (!$sensor) {

            return response()->json([
                'data' => [

                    'sensor_id' =>
                        null,

                    'sensor_name' =>
                        null,

                    'period_hours' =>
                        24,

                    'total_readings' =>
                        0,

                    'readings' =>
                        [],
                ],
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Últimas 24 horas
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
                ->map(
                    function ($reading) {

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
                    }
                );


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


    /*
    |--------------------------------------------------------------------------
    | MÉTODOS AUXILIARES
    |--------------------------------------------------------------------------
    */


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