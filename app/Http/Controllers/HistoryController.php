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
            'greenhouse_id' => ['nullable', 'integer'], 'zone_id' => ['nullable', 'integer'], 'sensor_id' => ['nullable', 'integer'],
            'metric' => ['nullable', 'in:temperature,soil_humidity,ambient_humidity'],
            'unit' => ['nullable', 'in:raw,%,°C,unknown'], 'start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'], 'per_page' => ['sometimes', 'integer', 'between:1,500'],
        ]);
        $user->company->syncSensorPlanRestrictions();
        $metric = $validated['metric'] ?? 'temperature';
        $start = isset($validated['start_date']) ? Carbon::parse($validated['start_date'])->startOfDay() : now()->subDays(7)->startOfDay();
        $end = isset($validated['end_date']) ? Carbon::parse($validated['end_date'])->endOfDay() : now()->endOfDay();
        $history = app(\App\Services\MeasurementHistory::class);
        $query = $history->query($user->company_id, $metric, $start, $end, $validated);
        $result = $history->page($query, $validated['page'] ?? 1, $validated['per_page'] ?? 25);
        $result['chart'] = app(\App\Services\MeasurementChart::class)->period($query, $start, $end, $result['summary']['total_readings']);
        $result['chart_scope'] = 'full_period';

        return response()->json($result + [
            'message' => 'Historial obtenido correctamente.',
            'filters' => $validated + ['metric' => $metric, 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString()],
            'metric' => ['type' => $metric, 'label' => $this->metricLabel($metric), 'unit' => $result['summary']['unit'] ?? $this->metricUnit($metric)],
        ]);
    }

    public function sensor(Request $request, \App\Models\Sensor $sensor): JsonResponse
    {
        abort_unless($sensor->device->zone->greenhouse->company_id === auth('api')->user()->company_id, 403);
        $request->merge(['sensor_id' => $sensor->id, 'metric' => $request->input('metric', $sensor->sensor_type)]);
        return $this->index($request);
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
