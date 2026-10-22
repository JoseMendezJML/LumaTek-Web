<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Company;
use App\Models\Device;
use App\Models\Greenhouse;
use App\Models\IrrigationEvent;
use App\Models\Sensor;
use App\Models\Zone;
use App\Models\ZoneIrrigationSetting;

/**
 * Resumen del estado de los invernaderos de una empresa.
 *
 * Lo usan el dashboard y el asistente para que ambos
 * muestren las mismas métricas y estados.
 */
class GreenhouseSnapshotService
{
    private const ASSISTANT_ALERT_LIMIT = 10;

    /**
     * Resumen compacto para el asistente, sin IDs ni datos personales.
     */
    public function forAssistant(Company $company): array
    {
        $metrics = collect(
            $this->dashboard($company->id)['greenhouses']
        )->keyBy('id');

        $greenhouses = Greenhouse::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->where('plan_restricted', false)
            ->with([
                'thresholds',
                'zones' => function ($query) {
                    $query
                        ->where('status', 'active')
                        ->where('plan_restricted', false)
                        ->orderBy('name');
                },
            ])
            ->orderBy('name')
            ->get();

        return [
            'generated_at' => now()->format('Y-m-d H:i'),
            'company' => $company->name,
            'greenhouses' => $greenhouses
                ->map(fn (Greenhouse $greenhouse) => $this->assistantGreenhouse(
                    $greenhouse,
                    $metrics->get($greenhouse->id)['metrics'] ?? []
                ))
                ->values()
                ->all(),
        ];
    }

    private function assistantGreenhouse(
        Greenhouse $greenhouse,
        array $metrics
    ): array {
        $zoneIds = $greenhouse->zones->pluck('id');

        $settings = ZoneIrrigationSetting::query()
            ->whereIn('zone_id', $zoneIds)
            ->get()
            ->keyBy('zone_id');

        $activeAlerts = Alert::query()
            ->where('status', 'active')
            ->whereHas('sensor', function ($query) use ($zoneIds) {
                $query
                    ->where('status', 'active')
                    ->where('plan_restricted', false)
                    ->whereHas('device', fn ($device) => $device->whereIn('zone_id', $zoneIds));
            });

        // Solo las más recientes, para acotar el tamaño (y el costo) del resumen.
        $alerts = (clone $activeAlerts)
            ->with('sensor.device.zone')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::ASSISTANT_ALERT_LIMIT)
            ->get();

        $lastIrrigation = IrrigationEvent::query()
            ->whereIn('zone_id', $zoneIds)
            ->with('zone')
            ->orderByDesc('started_at')
            ->first();

        return [
            'name' => $greenhouse->name,
            'crop_type' => $greenhouse->crop_type,
            'location' => $greenhouse->location,
            'metrics' => collect($metrics)
                ->map(function (array $metric, string $variable) use ($greenhouse) {
                    $threshold = $greenhouse->thresholds->firstWhere('variable', $variable);

                    return [
                        'value' => $metric['value'],
                        'unit' => $metric['unit'],
                        'status_label' => $metric['status_label'],
                        'threshold' => $threshold
                            ? [
                                'min' => $threshold->min_value !== null ? (float) $threshold->min_value : null,
                                'max' => $threshold->max_value !== null ? (float) $threshold->max_value : null,
                            ]
                            : null,
                        'connection_label' => $metric['connection_label'],
                        'recorded_at' => $metric['recorded_at'],
                    ];
                })
                ->all(),
            'zones' => $greenhouse->zones
                ->map(function ($zone) use ($settings) {
                    $setting = $settings->get($zone->id);

                    return [
                        'name' => $zone->name,
                        'status' => $zone->status,
                        'automatic_irrigation' => $setting
                            ? [
                                'enabled' => $setting->automatic_enabled,
                                'duration_minutes' => $setting->duration_minutes,
                                'water_liters' => (float) $setting->water_liters,
                            ]
                            : null,
                    ];
                })
                ->values()
                ->all(),
            'active_alerts_total' => $activeAlerts->count(),
            'active_alerts' => $alerts
                ->map(fn (Alert $alert) => [
                    'type' => $alert->type,
                    'severity' => $alert->severity,
                    'message' => $alert->message,
                    'zone' => $alert->sensor?->device?->zone?->name,
                    'since' => $alert->created_at?->toDateTimeString(),
                ])
                ->all(),
            'last_irrigation' => $lastIrrigation
                ? [
                    'zone' => $lastIrrigation->zone?->name,
                    'mode' => $lastIrrigation->mode,
                    'water_liters' => $lastIrrigation->water_liters !== null
                        ? (float) $lastIrrigation->water_liters
                        : null,
                    'soil_humidity_before' => $lastIrrigation->soil_humidity_before !== null
                        ? (float) $lastIrrigation->soil_humidity_before
                        : null,
                    'soil_humidity_after' => $lastIrrigation->soil_humidity_after !== null
                        ? (float) $lastIrrigation->soil_humidity_after
                        : null,
                    'started_at' => $lastIrrigation->started_at?->toDateTimeString(),
                ]
                : null,
        ];
    }

    /**
     * Datos del dashboard: resumen, invernaderos y alertas recientes.
     */
    public function dashboard(int $companyId): array
    {
        /*
        |--------------------------------------------------------------------------
        | Resumen general
        |--------------------------------------------------------------------------
        */

        $summary = [
            'greenhouses' => Greenhouse::query()
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->where('plan_restricted', false)
                ->count(),

            'zones' => Zone::query()
                ->where('status', 'active')
                ->where('plan_restricted', false)
                ->whereHas(
                    'greenhouse',
                    function ($query) use ($companyId) {
                        $query
                            ->where(
                                'company_id',
                                $companyId
                            )
                            ->where(
                                'plan_restricted',
                                false
                            );
                    }
                )
                ->count(),

            'devices' => Device::query()
                ->where('status', 'active')
                ->whereHas(
                    'zone',
                    function ($query) {
                        $query
                            ->where('status', 'active')
                            ->where('plan_restricted', false);
                    }
                )
                ->whereHas(
                    'zone.greenhouse',
                    function ($query) use ($companyId) {
                        $query
                            ->where(
                                'company_id',
                                $companyId
                            )
                            ->where(
                                'plan_restricted',
                                false
                            );
                    }
                )
                ->count(),

            'sensors' => Sensor::query()
                ->where('status', 'active')
                ->where('plan_restricted', false)
                ->whereHas(
                    'device.zone.greenhouse',
                    function ($query) use ($companyId) {
                        $query
                            ->where(
                                'company_id',
                                $companyId
                            )
                            ->where(
                                'plan_restricted',
                                false
                            );
                    }
                )
                ->count(),

            'active_alerts' => Alert::query()
                ->where('status', 'active')
                ->whereHas(
                    'sensor',
                    function ($query) {
                        $query
                            ->where('status', 'active')
                            ->where('plan_restricted', false);
                    }
                )
                ->whereHas(
                    'sensor.device.zone',
                    function ($query) {
                        $query
                            ->where('status', 'active')
                            ->where('plan_restricted', false);
                    }
                )
                ->whereHas(
                    'sensor.device.zone.greenhouse',
                    function ($query) use ($companyId) {
                        $query
                            ->where(
                                'company_id',
                                $companyId
                            )
                            ->where(
                                'plan_restricted',
                                false
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
            ->where('plan_restricted', false)
            ->with([
                'thresholds',
                'zones' => function ($query) {
                    $query
                        ->where(
                            'status',
                            'active'
                        )
                        ->where(
                            'plan_restricted',
                            false
                        );
                },
                'zones.devices' => function ($query) {
                    $query->where(
                        'status',
                        'active'
                    );
                },
                'zones.devices.sensors' => function ($query) {
                    $query
                        ->where(
                            'status',
                            'active'
                        )
                        ->where(
                            'plan_restricted',
                            false
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
                'sensor',
                function ($query) {
                    $query
                        ->where('status', 'active')
                        ->where('plan_restricted', false);
                }
            )
            ->whereHas(
                'sensor.device.zone',
                function ($query) {
                    $query
                        ->where('status', 'active')
                        ->where('plan_restricted', false);
                }
            )
            ->whereHas(
                'sensor.device.zone.greenhouse',
                function ($query) use ($companyId) {
                    $query
                        ->where(
                            'company_id',
                            $companyId
                        )
                        ->where(
                            'plan_restricted',
                            false
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

                        'type_label' => Alert::typeLabel($alert->type),

                        'message' => $alert->message,

                        'severity' => $alert->severity,

                        'severity_label' => Alert::severityLabel($alert->severity),

                        'status' => $alert->status,

                        'status_label' => Alert::statusLabel($alert->status),

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


        return [
            'summary' =>
            $summary,

            'greenhouses' =>
            $greenhouseData,

            'recent_alerts' =>
            $recentAlerts,
        ];
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
