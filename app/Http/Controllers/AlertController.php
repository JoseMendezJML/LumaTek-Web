<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Greenhouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * Lista las alertas de la empresa autenticada.
     */
    public function index(
        Request $request
    ): JsonResponse {

        $user = auth('api')->user();

        $query = Alert::query()
            ->whereHas(
                'sensor.device.zone.greenhouse',
                function ($query) use ($user) {
                    $query->where(
                        'company_id',
                        $user->company_id
                    );
                }
            )
            ->with([
                'sensor.device.zone.greenhouse',
                'reading',
                'acknowledgedBy',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Filtro por estado
        |--------------------------------------------------------------------------
        */

        if ($request->filled('status')) {

            $request->validate([
                'status' => [
                    'in:active,acknowledged,resolved',
                ],
            ]);

            $query->where(
                'status',
                $request->status
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Filtro por severidad
        |--------------------------------------------------------------------------
        */

        if ($request->filled('severity')) {

            $request->validate([
                'severity' => [
                    'in:info,warning,critical',
                ],
            ]);

            $query->where(
                'severity',
                $request->severity
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Filtro por invernadero
        |--------------------------------------------------------------------------
        */

        if ($request->filled('greenhouse_id')) {

            $greenhouse =
                Greenhouse::query()
                    ->where(
                        'id',
                        $request->greenhouse_id
                    )
                    ->where(
                        'company_id',
                        $user->company_id
                    )
                    ->first();

            if (!$greenhouse) {

                return response()->json([
                    'message' =>
                        'El invernadero seleccionado no es válido.',
                ], 422);
            }

            $query->whereHas(
                'sensor.device.zone',
                function ($query) use ($greenhouse) {

                    $query->where(
                        'greenhouse_id',
                        $greenhouse->id
                    );

                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Orden
        |--------------------------------------------------------------------------
        */

        $alerts =
            $query
                ->orderByRaw(
                    "
                    CASE
                        WHEN status = 'active' THEN 1
                        WHEN status = 'acknowledged' THEN 2
                        ELSE 3
                    END
                    "
                )
                ->orderByDesc('created_at')
                ->get();

        /*
        |--------------------------------------------------------------------------
        | Transformar respuesta
        |--------------------------------------------------------------------------
        */

        $data =
            $alerts->map(
                function ($alert) {

                    $sensor =
                        $alert->sensor;

                    $device =
                        $sensor?->device;

                    $zone =
                        $device?->zone;

                    $greenhouse =
                        $zone?->greenhouse;

                    return [
                        'id' =>
                            $alert->id,

                        'type' =>
                            $alert->type,

                        'type_label' =>
                            $this->typeLabel(
                                $alert->type
                            ),

                        'severity' =>
                            $alert->severity,

                        'severity_label' =>
                            $this->severityLabel(
                                $alert->severity
                            ),

                        'message' =>
                            $alert->message,

                        'status' =>
                            $alert->status,

                        'status_label' =>
                            $this->statusLabel(
                                $alert->status
                            ),

                        'created_at' =>
                            $alert->created_at
                                ?->toDateTimeString(),

                        'acknowledged_at' =>
                            $alert->acknowledged_at
                                ?->toDateTimeString(),

                        'resolved_at' =>
                            $alert->resolved_at
                                ?->toDateTimeString(),

                        'acknowledged_by' =>
                            $alert->acknowledgedBy
                                ? [
                                    'id' =>
                                        $alert
                                            ->acknowledgedBy
                                            ->id,

                                    'name' =>
                                        $alert
                                            ->acknowledgedBy
                                            ->name,
                                ]
                                : null,

                        'reading' =>
                            $alert->reading
                                ? [
                                    'id' =>
                                        $alert->reading->id,

                                    'value' =>
                                        (float)
                                        $alert
                                            ->reading
                                            ->value,

                                    'recorded_at' =>
                                        $alert
                                            ->reading
                                            ->recorded_at
                                            ?->toDateTimeString(),
                                ]
                                : null,

                        'sensor' =>
                            $sensor
                                ? [
                                    'id' =>
                                        $sensor->id,

                                    'name' =>
                                        $sensor->name,

                                    'code' =>
                                        $sensor->sensor_code,

                                    'type' =>
                                        $sensor->sensor_type,

                                    'unit' =>
                                        $sensor->unit,
                                ]
                                : null,

                        'device' =>
                            $device
                                ? [
                                    'id' =>
                                        $device->id,

                                    'name' =>
                                        $device->name,

                                    'code' =>
                                        $device->device_code,
                                ]
                                : null,

                        'zone' =>
                            $zone
                                ? [
                                    'id' =>
                                        $zone->id,

                                    'name' =>
                                        $zone->name,
                                ]
                                : null,

                        'greenhouse' =>
                            $greenhouse
                                ? [
                                    'id' =>
                                        $greenhouse->id,

                                    'name' =>
                                        $greenhouse->name,
                                ]
                                : null,
                    ];
                }
            );

        /*
        |--------------------------------------------------------------------------
        | Resumen
        |--------------------------------------------------------------------------
        */

        $summary = [
            'total' =>
                $data->count(),

            'active' =>
                $data
                    ->where(
                        'status',
                        'active'
                    )
                    ->count(),

            'acknowledged' =>
                $data
                    ->where(
                        'status',
                        'acknowledged'
                    )
                    ->count(),

            'resolved' =>
                $data
                    ->where(
                        'status',
                        'resolved'
                    )
                    ->count(),
        ];

        return response()->json([
            'summary' =>
                $summary,

            'data' =>
                $data->values(),
        ]);
    }


    /**
     * Marca una alerta activa como atendida.
     */
    public function acknowledge(
        Alert $alert
    ): JsonResponse {

        $user = auth('api')->user();

        $alert->load(
            'sensor.device.zone.greenhouse'
        );

        /*
        |--------------------------------------------------------------------------
        | Aislamiento por empresa
        |--------------------------------------------------------------------------
        */

        if (
            !$alert->sensor
            || !$alert->sensor->device
            || !$alert->sensor->device->zone
            || !$alert->sensor->device->zone->greenhouse
            || $alert
                ->sensor
                ->device
                ->zone
                ->greenhouse
                ->company_id
                !==
                $user->company_id
        ) {

            return response()->json([
                'message' =>
                    'No tienes permiso para modificar esta alerta.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Validar estado
        |--------------------------------------------------------------------------
        */

        if ($alert->status === 'resolved') {

            return response()->json([
                'message' =>
                    'La alerta ya se encuentra resuelta.',
            ], 422);
        }

        if ($alert->status === 'acknowledged') {

            return response()->json([
                'message' =>
                    'La alerta ya fue atendida.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Atender alerta
        |--------------------------------------------------------------------------
        */

        $alert->update([
            'status' =>
                'acknowledged',

            'acknowledged_by' =>
                $user->id,

            'acknowledged_at' =>
                now(),
        ]);

        return response()->json([
            'message' =>
                'Alerta marcada como atendida correctamente.',

            'data' =>
                $alert->fresh([
                    'acknowledgedBy',
                ]),
        ]);
    }


    /**
     * Permite resolver manualmente una alerta.
     */
    public function resolve(
        Alert $alert
    ): JsonResponse {

        $user = auth('api')->user();

        $alert->load(
            'sensor.device.zone.greenhouse'
        );

        /*
        |--------------------------------------------------------------------------
        | Aislamiento por empresa
        |--------------------------------------------------------------------------
        */

        if (
            !$alert->sensor
            || !$alert->sensor->device
            || !$alert->sensor->device->zone
            || !$alert->sensor->device->zone->greenhouse
            || $alert
                ->sensor
                ->device
                ->zone
                ->greenhouse
                ->company_id
                !==
                $user->company_id
        ) {

            return response()->json([
                'message' =>
                    'No tienes permiso para modificar esta alerta.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Ya resuelta
        |--------------------------------------------------------------------------
        */

        if ($alert->status === 'resolved') {

            return response()->json([
                'message' =>
                    'La alerta ya se encuentra resuelta.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Resolver
        |--------------------------------------------------------------------------
        */

        $data = [
            'status' =>
                'resolved',

            'resolved_at' =>
                now(),
        ];

        /*
        | Si nunca había sido atendida, guardamos quién
        | realizó la acción.
        */

        if (!$alert->acknowledged_by) {

            $data['acknowledged_by'] =
                $user->id;

            $data['acknowledged_at'] =
                now();
        }

        $alert->update(
            $data
        );

        return response()->json([
            'message' =>
                'Alerta resuelta correctamente.',

            'data' =>
                $alert->fresh([
                    'acknowledgedBy',
                ]),
        ]);
    }


    /**
     * Etiqueta legible para el tipo de alerta.
     */
    private function typeLabel(
        string $type
    ): string {

        return match ($type) {

            'high_temperature' =>
                'Temperatura alta',

            'low_temperature' =>
                'Temperatura baja',

            'low_soil_humidity' =>
                'Humedad del suelo baja',

            'high_ambient_humidity' =>
                'Humedad ambiental alta',

            default =>
                'Alerta de monitoreo',
        };
    }


    /**
     * Etiqueta legible para severidad.
     */
    private function severityLabel(
        string $severity
    ): string {

        return match ($severity) {

            'critical' =>
                'Crítica',

            'warning' =>
                'Advertencia',

            default =>
                'Informativa',
        };
    }


    /**
     * Etiqueta legible para estado.
     */
    private function statusLabel(
        string $status
    ): string {

        return match ($status) {

            'active' =>
                'Activa',

            'acknowledged' =>
                'Atendida',

            'resolved' =>
                'Resuelta',

            default =>
                $status,
        };
    }
}