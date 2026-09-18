<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Greenhouse;
use App\Models\Sensor;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Devuelve la información principal del dashboard.
     */
    public function index(): JsonResponse
    {
        $user = auth('api')->user();

        $companyId = $user->company_id;

        /*
        |--------------------------------------------------------------------------
        | Resumen general
        |--------------------------------------------------------------------------
        */

        $summary = [
            'greenhouses' => Greenhouse::query()
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->count(),

            'zones' => Zone::query()
                ->where('status', 'active')
                ->whereHas(
                    'greenhouse',
                    function ($query) use ($companyId) {
                        $query->where(
                            'company_id',
                            $companyId
                        );
                    }
                )
                ->count(),

            'devices' => Device::query()
                ->where('status', 'active')
                ->whereHas(
                    'zone.greenhouse',
                    function ($query) use ($companyId) {
                        $query->where(
                            'company_id',
                            $companyId
                        );
                    }
                )
                ->count(),

            'sensors' => Sensor::query()
                ->where('status', 'active')
                ->whereHas(
                    'device.zone.greenhouse',
                    function ($query) use ($companyId) {
                        $query->where(
                            'company_id',
                            $companyId
                        );
                    }
                )
                ->count(),

            'active_alerts' => Alert::query()
                ->where('status', 'active')
                ->whereHas(
                    'sensor.device.zone.greenhouse',
                    function ($query) use ($companyId) {
                        $query->where(
                            'company_id',
                            $companyId
                        );
                    }
                )
                ->count(),
        ];


        /*
        |--------------------------------------------------------------------------
        | Invernaderos
        |--------------------------------------------------------------------------
        */

        $greenhouses = Greenhouse::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->with([
                'thresholds',
                'zones' => function ($query) {
                    $query->where(
                        'status',
                        'active'
                    );
                },
                'zones.devices' => function ($query) {
                    $query->where(
                        'status',
                        'active'
                    );
                },
                'zones.devices.sensors' => function ($query) {
                    $query->where(
                        'status',
                        'active'
                    );
                },
                'zones.devices.sensors.latestReading',
            ])
            ->orderBy('name')
            ->get();


        $greenhouseData = $greenhouses->map(
            function ($greenhouse) {

                $sensors = $greenhouse
                    ->zones
                    ->flatMap(
                        function ($zone) {
                            return $zone
                                ->devices
                                ->flatMap(
                                    function ($device) {
                                        return $device->sensors;
                                    }
                                );
                        }
                    );


                $temperatureThreshold = $greenhouse
                    ->thresholds
                    ->firstWhere(
                        'variable',
                        'temperature'
                    );


                $soilThreshold = $greenhouse
                    ->thresholds
                    ->firstWhere(
                        'variable',
                        'soil_humidity'
                    );


                $ambientThreshold = $greenhouse
                    ->thresholds
                    ->firstWhere(
                        'variable',
                        'ambient_humidity'
                    );


                return [
                    'id' => $greenhouse->id,

                    'name' => $greenhouse->name,

                    'crop_type' => $greenhouse->crop_type,

                    'location' => $greenhouse->location,

                    'zones_count' => $greenhouse
                        ->zones
                        ->count(),

                    'devices_count' => $greenhouse
                        ->zones
                        ->sum(
                            function ($zone) {
                                return $zone
                                    ->devices
                                    ->count();
                            }
                        ),

                    'sensors_count' => $sensors
                        ->count(),

                    'metrics' => [
                        'temperature' =>
                            $this->buildMetric(
                                $sensors,
                                'temperature',
                                $temperatureThreshold
                            ),

                        'soil_humidity' =>
                            $this->buildMetric(
                                $sensors,
                                'soil_humidity',
                                $soilThreshold
                            ),

                        'ambient_humidity' =>
                            $this->buildMetric(
                                $sensors,
                                'ambient_humidity',
                                $ambientThreshold
                            ),
                    ],

                    'active_alerts' =>
                        Alert::query()
                            ->where('status', 'active')
                            ->whereHas(
                                'sensor.device.zone',
                                function ($query) use ($greenhouse) {
                                    $query->where(
                                        'greenhouse_id',
                                        $greenhouse->id
                                    );
                                }
                            )
                            ->count(),
                ];
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Alertas recientes
        |--------------------------------------------------------------------------
        */

        $recentAlerts = Alert::query()
            ->whereHas(
                'sensor.device.zone.greenhouse',
                function ($query) use ($companyId) {
                    $query->where(
                        'company_id',
                        $companyId
                    );
                }
            )
            ->with([
                'sensor.device.zone.greenhouse',
                'reading',
            ])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(
                function ($alert) {

                    return [
                        'id' => $alert->id,

                        'type' => $alert->type,

                        'message' => $alert->message,

                        'severity' => $alert->severity,

                        'status' => $alert->status,

                        'created_at' =>
                            $alert->created_at
                                ?->toDateTimeString(),

                        'reading' => $alert->reading
                            ? [
                                'value' =>
                                    (float) $alert
                                        ->reading
                                        ->value,

                                'recorded_at' =>
                                    $alert
                                        ->reading
                                        ->recorded_at
                                        ?->toDateTimeString(),
                            ]
                            : null,

                        'sensor' => [
                            'name' =>
                                $alert
                                    ->sensor
                                    ?->name,

                            'unit' =>
                                $alert
                                    ->sensor
                                    ?->unit,
                        ],

                        'zone' => [
                            'name' =>
                                $alert
                                    ->sensor
                                    ?->device
                                    ?->zone
                                    ?->name,
                        ],

                        'greenhouse' => [
                            'name' =>
                                $alert
                                    ->sensor
                                    ?->device
                                    ?->zone
                                    ?->greenhouse
                                    ?->name,
                        ],
                    ];
                }
            );


        /*
        |--------------------------------------------------------------------------
        | Respuesta
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'data' => [
                'summary' =>
                    $summary,

                'greenhouses' =>
                    $greenhouseData,

                'recent_alerts' =>
                    $recentAlerts,
            ],
        ]);
    }


    /**
     * Construye la información de una métrica actual.
     */
    private function buildMetric(
        $sensors,
        string $sensorType,
        $threshold
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Sensores del tipo solicitado con lecturas
        |--------------------------------------------------------------------------
        */

        $sensor = $sensors
            ->filter(
                function ($sensor) use ($sensorType) {

                    return
                        $sensor->sensor_type === $sensorType
                        && $sensor->latestReading;
                }
            )
            ->sortByDesc(
                function ($sensor) {

                    return $sensor
                        ->latestReading
                        ?->recorded_at;
                }
            )
            ->first();


        /*
        |--------------------------------------------------------------------------
        | Sin datos
        |--------------------------------------------------------------------------
        */

        if (!$sensor) {

            return [
                'value' =>
                    null,

                'unit' =>
                    $sensorType === 'temperature'
                        ? '°C'
                        : '%',

                'status' =>
                    'no_data',

                'status_label' =>
                    'Sin datos',

                'connection_status' =>
                    'no_data',

                'connection_label' =>
                    'Sin datos',

                'sensor_id' =>
                    null,

                'sensor_name' =>
                    null,

                'recorded_at' =>
                    null,
            ];
        }


        $reading =
            $sensor->latestReading;


        $value =
            (float) $reading->value;


        /*
        |--------------------------------------------------------------------------
        | Estado de conexión
        |--------------------------------------------------------------------------
        */

        $isDisconnected = $reading
            ->recorded_at
            ->lt(
                now()->subMinutes(10)
            );


        /*
        |--------------------------------------------------------------------------
        | Estado del valor
        |--------------------------------------------------------------------------
        */

        $status =
            $this->metricStatus(
                $sensorType,
                $value,
                $threshold
            );


        return [
            'value' =>
                round(
                    $value,
                    1
                ),

            'unit' =>
                $sensor->unit,

            'status' =>
                $status,

            'status_label' =>
                $this->statusLabel(
                    $sensorType,
                    $status
                ),

            'connection_status' =>
                $isDisconnected
                    ? 'disconnected'
                    : 'connected',

            'connection_label' =>
                $isDisconnected
                    ? 'Sin conexión'
                    : 'Conectado',

            'sensor_id' =>
                $sensor->id,

            'sensor_name' =>
                $sensor->name,

            'recorded_at' =>
                $reading
                    ->recorded_at
                    ->toDateTimeString(),
        ];
    }


    /**
     * Determina el estado actual de una métrica.
     */
    private function metricStatus(
        string $sensorType,
        float $value,
        $threshold
    ): string {

        if (!$threshold) {
            return 'normal';
        }


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
        | Temperatura
        |--------------------------------------------------------------------------
        */

        if ($sensorType === 'temperature') {

            if (
                $minimum !== null
                && $value < $minimum
            ) {
                return 'low';
            }

            if (
                $maximum !== null
                && $value > $maximum
            ) {
                return 'high';
            }

            return 'normal';
        }


        /*
        |--------------------------------------------------------------------------
        | Humedad del suelo
        |--------------------------------------------------------------------------
        */

        if ($sensorType === 'soil_humidity') {

            if (
                $minimum !== null
                && $value < $minimum
            ) {
                return 'low';
            }

            if (
                $maximum !== null
                && $value > $maximum
            ) {
                return 'high';
            }

            return 'medium';
        }


        /*
        |--------------------------------------------------------------------------
        | Humedad ambiental
        |--------------------------------------------------------------------------
        */

        if ($sensorType === 'ambient_humidity') {

            if (
                $maximum !== null
                && $value > $maximum
            ) {
                return 'high';
            }

            return 'normal';
        }


        return 'normal';
    }


    /**
     * Convierte los estados técnicos en textos legibles.
     */
    private function statusLabel(
        string $sensorType,
        string $status
    ): string {

        if ($status === 'no_data') {
            return 'Sin datos';
        }


        if ($sensorType === 'temperature') {

            return match ($status) {

                'low' =>
                    'Baja',

                'high' =>
                    'Alta',

                default =>
                    'Normal',
            };
        }


        if ($sensorType === 'soil_humidity') {

            return match ($status) {

                'low' =>
                    'Baja',

                'high' =>
                    'Alta',

                default =>
                    'Media',
            };
        }


        if ($sensorType === 'ambient_humidity') {

            return $status === 'high'
                ? 'Alta'
                : 'Normal';
        }


        return 'Normal';
    }
}