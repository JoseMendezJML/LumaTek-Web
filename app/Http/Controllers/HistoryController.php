<?php

namespace App\Http\Controllers;

use App\Models\IrrigationEvent;
use App\Models\Reading;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $validated = $request->validate([
            'greenhouse_id' => ['nullable', 'integer'],
            'zone_id' => ['nullable', 'integer'],
            'metric' => [
                'nullable',
                'in:temperature,soil_humidity,ambient_humidity',
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $metric = $validated['metric'] ?? 'temperature';

        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : now()->subDays(7)->startOfDay();

        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : now()->endOfDay();

        if ($startDate->greaterThan($endDate)) {
            return response()->json([
                'message' => 'La fecha inicial no puede ser mayor que la fecha final.',
            ], 422);
        }

        $query = Reading::query()
            ->with([
                'sensor.device.zone.greenhouse',
            ])
            ->whereBetween('recorded_at', [
                $startDate,
                $endDate,
            ])
            ->whereHas('sensor', function ($sensorQuery) use ($metric) {
                $sensorQuery->where(
                    'sensor_type',
                    $metric
                );
            })
            ->whereHas(
                'sensor.device.zone.greenhouse',
                function ($greenhouseQuery) use ($user) {
                    $greenhouseQuery->where(
                        'company_id',
                        $user->company_id
                    );
                }
            );

        if (!empty($validated['greenhouse_id'])) {
            $greenhouseId = $validated['greenhouse_id'];

            $query->whereHas(
                'sensor.device.zone',
                function ($zoneQuery) use ($greenhouseId) {
                    $zoneQuery->where(
                        'greenhouse_id',
                        $greenhouseId
                    );
                }
            );
        }

        if (!empty($validated['zone_id'])) {
            $zoneId = $validated['zone_id'];

            $query->whereHas(
                'sensor.device',
                function ($deviceQuery) use ($zoneId) {
                    $deviceQuery->where(
                        'zone_id',
                        $zoneId
                    );
                }
            );
        }

        $readings = $query
            ->orderBy('recorded_at')
            ->get();

        $values = $readings
            ->pluck('value')
            ->map(
                fn ($value) => (float) $value
            );

        $summary = [
            'total_readings' => $values->count(),

            'minimum' => $values->isNotEmpty()
                ? round($values->min(), 2)
                : null,

            'maximum' => $values->isNotEmpty()
                ? round($values->max(), 2)
                : null,

            'average' => $values->isNotEmpty()
                ? round($values->avg(), 2)
                : null,
        ];

        $data = $readings->map(function ($reading) {
            $sensor = $reading->sensor;
            $device = $sensor?->device;
            $zone = $device?->zone;
            $greenhouse = $zone?->greenhouse;

            return [
                'id' => $reading->id,

                'value' => (float) $reading->value,

                'recorded_at' => optional(
                    $reading->recorded_at
                )->format('Y-m-d H:i:s'),

                'source' => $reading->source,

                'sensor' => $sensor ? [
                    'id' => $sensor->id,
                    'name' => $sensor->name,
                    'code' => $sensor->sensor_code,
                    'type' => $sensor->sensor_type,
                    'unit' => $sensor->unit,
                ] : null,

                'device' => $device ? [
                    'id' => $device->id,
                    'name' => $device->name,
                    'code' => $device->device_code,
                ] : null,

                'zone' => $zone ? [
                    'id' => $zone->id,
                    'name' => $zone->name,
                ] : null,

                'greenhouse' => $greenhouse ? [
                    'id' => $greenhouse->id,
                    'name' => $greenhouse->name,
                ] : null,
            ];
        });

        return response()->json([
            'message' => 'Historial obtenido correctamente.',

            'filters' => [
                'greenhouse_id' => $validated['greenhouse_id'] ?? null,
                'zone_id' => $validated['zone_id'] ?? null,
                'metric' => $metric,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],

            'metric' => [
                'type' => $metric,
                'label' => $this->metricLabel($metric),
                'unit' => $this->metricUnit($metric),
            ],

            'summary' => $summary,

            'data' => $data,
        ]);
    }


    public function irrigations(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $validated = $request->validate([
            'greenhouse_id' => ['nullable', 'integer'],
            'zone_id' => ['nullable', 'integer'],

            'mode' => [
                'nullable',
                'in:manual,automatic',
            ],

            'status' => [
                'nullable',
                'in:started,completed,cancelled,failed',
            ],

            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : now()->subDays(7)->startOfDay();

        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : now()->endOfDay();

        if ($startDate->greaterThan($endDate)) {
            return response()->json([
                'message' => 'La fecha inicial no puede ser mayor que la fecha final.',
            ], 422);
        }

        $query = IrrigationEvent::query()
            ->with([
                'zone.greenhouse',
                'user',
                'triggerReading.sensor',
            ])
            ->whereBetween('started_at', [
                $startDate,
                $endDate,
            ])
            ->whereHas(
                'zone.greenhouse',
                function ($greenhouseQuery) use ($user) {
                    $greenhouseQuery->where(
                        'company_id',
                        $user->company_id
                    );
                }
            );

        if (!empty($validated['greenhouse_id'])) {
            $greenhouseId = $validated['greenhouse_id'];

            $query->whereHas(
                'zone',
                function ($zoneQuery) use ($greenhouseId) {
                    $zoneQuery->where(
                        'greenhouse_id',
                        $greenhouseId
                    );
                }
            );
        }

        if (!empty($validated['zone_id'])) {
            $query->where(
                'zone_id',
                $validated['zone_id']
            );
        }

        if (!empty($validated['mode'])) {
            $query->where(
                'mode',
                $validated['mode']
            );
        }

        if (!empty($validated['status'])) {
            $query->where(
                'status',
                $validated['status']
            );
        }

        $events = $query
            ->orderByDesc('started_at')
            ->get();

        $summary = [
            'total' => $events->count(),

            'manual' => $events
                ->where('mode', 'manual')
                ->count(),

            'automatic' => $events
                ->where('mode', 'automatic')
                ->count(),

            'completed' => $events
                ->where('status', 'completed')
                ->count(),

            'cancelled' => $events
                ->where('status', 'cancelled')
                ->count(),

            'total_water_liters' => round(
                $events
                    ->whereNotNull('water_liters')
                    ->sum('water_liters'),
                2
            ),
        ];

        $data = $events->map(function ($event) {
            $zone = $event->zone;
            $greenhouse = $zone?->greenhouse;

            return [
                'id' => $event->id,

                'mode' => $event->mode,

                'mode_label' => match ($event->mode) {
                    'automatic' => 'Automático',
                    default => 'Manual',
                },

                'status' => $event->status,

                'status_label' => match ($event->status) {
                    'started' => 'En curso',
                    'completed' => 'Completado',
                    'cancelled' => 'Cancelado',
                    'failed' => 'Fallido',
                    default => $event->status,
                },

                'duration_minutes' => $event->duration_minutes,

                'water_liters' => $event->water_liters !== null
                    ? (float) $event->water_liters
                    : null,

                'soil_humidity_before' => $event->soil_humidity_before !== null
                    ? (float) $event->soil_humidity_before
                    : null,

                'soil_humidity_after' => $event->soil_humidity_after !== null
                    ? (float) $event->soil_humidity_after
                    : null,

                'started_at' => optional(
                    $event->started_at
                )->format('Y-m-d H:i:s'),

                'ended_at' => optional(
                    $event->ended_at
                )->format('Y-m-d H:i:s'),

                'notes' => $event->notes,

                'zone' => $zone ? [
                    'id' => $zone->id,
                    'name' => $zone->name,
                ] : null,

                'greenhouse' => $greenhouse ? [
                    'id' => $greenhouse->id,
                    'name' => $greenhouse->name,
                ] : null,

                'user' => $event->user ? [
                    'id' => $event->user->id,
                    'name' => $event->user->name,
                ] : null,

                'trigger_reading' => $event->triggerReading ? [
                    'id' => $event->triggerReading->id,
                    'value' => (float) $event->triggerReading->value,

                    'recorded_at' => optional(
                        $event->triggerReading->recorded_at
                    )->format('Y-m-d H:i:s'),
                ] : null,
            ];
        });

        return response()->json([
            'message' => 'Historial de riegos obtenido correctamente.',

            'filters' => [
                'greenhouse_id' => $validated['greenhouse_id'] ?? null,
                'zone_id' => $validated['zone_id'] ?? null,
                'mode' => $validated['mode'] ?? null,
                'status' => $validated['status'] ?? null,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],

            'summary' => $summary,

            'data' => $data,
        ]);
    }


    private function metricLabel(string $metric): string
    {
        return match ($metric) {
            'temperature' => 'Temperatura',
            'soil_humidity' => 'Humedad del suelo',
            'ambient_humidity' => 'Humedad ambiental',
            default => 'Medición',
        };
    }


    private function metricUnit(string $metric): string
    {
        return match ($metric) {
            'temperature' => '°C',

            'soil_humidity',
            'ambient_humidity' => '%',

            default => '',
        };
    }
}