<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Greenhouse;
use App\Models\IrrigationEvent;
use App\Models\Reading;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /**
     * Genera la información base del reporte
     * para un invernadero y periodo determinado.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $validated = $request->validate([
            'greenhouse_id' => [
                'required',
                'integer',
            ],

            'start_date' => [
                'nullable',
                'date',
            ],

            'end_date' => [
                'nullable',
                'date',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Periodo
        |--------------------------------------------------------------------------
        */

        $startDate = isset($validated['start_date'])
            ? Carbon::parse(
                $validated['start_date']
            )->startOfDay()
            : now()->subDays(7)->startOfDay();

        $endDate = isset($validated['end_date'])
            ? Carbon::parse(
                $validated['end_date']
            )->endOfDay()
            : now()->endOfDay();

        if ($startDate->greaterThan($endDate)) {
            return response()->json([
                'message' =>
                    'La fecha inicial no puede ser mayor que la fecha final.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Validar invernadero y empresa
        |--------------------------------------------------------------------------
        */

        $greenhouse = Greenhouse::query()
            ->with('company')
            ->where(
                'id',
                $validated['greenhouse_id']
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

        /*
        |--------------------------------------------------------------------------
        | Resumen de variables ambientales
        |--------------------------------------------------------------------------
        */

        $metrics = [
            'temperature' =>
                $this->metricSummary(
                    $greenhouse->id,
                    'temperature',
                    $startDate,
                    $endDate
                ),

            'soil_humidity' =>
                $this->metricSummary(
                    $greenhouse->id,
                    'soil_humidity',
                    $startDate,
                    $endDate
                ),

            'ambient_humidity' =>
                $this->metricSummary(
                    $greenhouse->id,
                    'ambient_humidity',
                    $startDate,
                    $endDate
                ),
        ];

        /*
        |--------------------------------------------------------------------------
        | Alertas
        |--------------------------------------------------------------------------
        */

        $alerts = Alert::query()
            ->with([
                'sensor.device.zone',
            ])
            ->whereBetween(
                'created_at',
                [
                    $startDate,
                    $endDate,
                ]
            )
            ->whereHas(
                'sensor.device.zone',
                function ($query) use ($greenhouse) {
                    $query->where(
                        'greenhouse_id',
                        $greenhouse->id
                    );
                }
            )
            ->orderByDesc('created_at')
            ->get();

        $alertsSummary = [
            'total' =>
                $alerts->count(),

            'active' =>
                $alerts
                    ->where(
                        'status',
                        'active'
                    )
                    ->count(),

            'acknowledged' =>
                $alerts
                    ->where(
                        'status',
                        'acknowledged'
                    )
                    ->count(),

            'resolved' =>
                $alerts
                    ->where(
                        'status',
                        'resolved'
                    )
                    ->count(),

            'info' =>
                $alerts
                    ->where(
                        'severity',
                        'info'
                    )
                    ->count(),

            'warning' =>
                $alerts
                    ->where(
                        'severity',
                        'warning'
                    )
                    ->count(),

            'critical' =>
                $alerts
                    ->where(
                        'severity',
                        'critical'
                    )
                    ->count(),
        ];

        /*
        |--------------------------------------------------------------------------
        | Riegos
        |--------------------------------------------------------------------------
        */

        $irrigations = IrrigationEvent::query()
            ->with([
                'zone',
                'user',
            ])
            ->whereBetween(
                'started_at',
                [
                    $startDate,
                    $endDate,
                ]
            )
            ->whereHas(
                'zone',
                function ($query) use ($greenhouse) {
                    $query->where(
                        'greenhouse_id',
                        $greenhouse->id
                    );
                }
            )
            ->orderByDesc('started_at')
            ->get();

        $irrigationSummary = [
            'total' =>
                $irrigations->count(),

            'manual' =>
                $irrigations
                    ->where(
                        'mode',
                        'manual'
                    )
                    ->count(),

            'automatic' =>
                $irrigations
                    ->where(
                        'mode',
                        'automatic'
                    )
                    ->count(),

            'started' =>
                $irrigations
                    ->where(
                        'status',
                        'started'
                    )
                    ->count(),

            'completed' =>
                $irrigations
                    ->where(
                        'status',
                        'completed'
                    )
                    ->count(),

            'cancelled' =>
                $irrigations
                    ->where(
                        'status',
                        'cancelled'
                    )
                    ->count(),

            'failed' =>
                $irrigations
                    ->where(
                        'status',
                        'failed'
                    )
                    ->count(),

            'total_duration_minutes' =>
                $irrigations
                    ->whereNotNull(
                        'duration_minutes'
                    )
                    ->sum(
                        'duration_minutes'
                    ),

            'total_water_liters' =>
                round(
                    (float) $irrigations
                        ->whereNotNull(
                            'water_liters'
                        )
                        ->sum(
                            'water_liters'
                        ),
                    2
                ),
        ];

        /*
        |--------------------------------------------------------------------------
        | Eventos recientes
        |--------------------------------------------------------------------------
        */

        $recentAlerts = $alerts
            ->take(10)
            ->map(function ($alert) {

                $sensor = $alert->sensor;
                $device = $sensor?->device;
                $zone = $device?->zone;

                return [
                    'id' =>
                        $alert->id,

                    'type' =>
                        $alert->type,

                    'type_label' =>
                        $this->alertTypeLabel(
                            $alert->type
                        ),

                    'severity' =>
                        $alert->severity,

                    'severity_label' =>
                        $this->severityLabel(
                            $alert->severity
                        ),

                    'status' =>
                        $alert->status,

                    'status_label' =>
                        $this->alertStatusLabel(
                            $alert->status
                        ),

                    'message' =>
                        $alert->message,

                    'created_at' =>
                        optional(
                            $alert->created_at
                        )->format(
                            'Y-m-d H:i:s'
                        ),

                    'sensor' =>
                        $sensor
                            ? [
                                'id' =>
                                    $sensor->id,

                                'name' =>
                                    $sensor->name,

                                'code' =>
                                    $sensor->sensor_code,
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
                ];
            })
            ->values();

        $recentIrrigations = $irrigations
            ->take(10)
            ->map(function ($event) {

                return [
                    'id' =>
                        $event->id,

                    'mode' =>
                        $event->mode,

                    'mode_label' =>
                        $event->mode === 'automatic'
                            ? 'Automático'
                            : 'Manual',

                    'status' =>
                        $event->status,

                    'status_label' =>
                        $this->irrigationStatusLabel(
                            $event->status
                        ),

                    'duration_minutes' =>
                        $event->duration_minutes,

                    'water_liters' =>
                        $event->water_liters !== null
                            ? (float) $event->water_liters
                            : null,

                    'started_at' =>
                        optional(
                            $event->started_at
                        )->format(
                            'Y-m-d H:i:s'
                        ),

                    'ended_at' =>
                        optional(
                            $event->ended_at
                        )->format(
                            'Y-m-d H:i:s'
                        ),

                    'zone' =>
                        $event->zone
                            ? [
                                'id' =>
                                    $event->zone->id,

                                'name' =>
                                    $event->zone->name,
                            ]
                            : null,

                    'user' =>
                        $event->user
                            ? [
                                'id' =>
                                    $event->user->id,

                                'name' =>
                                    $event->user->name,
                            ]
                            : null,
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Respuesta
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'message' =>
                'Reporte generado correctamente.',

            'report' => [

                'company' => [
                    'id' =>
                        $greenhouse
                            ->company
                            ?->id,

                    'name' =>
                        $greenhouse
                            ->company
                            ?->name,

                    'legal_name' =>
                        $greenhouse
                            ->company
                            ?->legal_name,

                    'email' =>
                        $greenhouse
                            ->company
                            ?->email,

                    'phone' =>
                        $greenhouse
                            ->company
                            ?->phone,
                ],

                'greenhouse' => [
                    'id' =>
                        $greenhouse->id,

                    'name' =>
                        $greenhouse->name,

                    'crop_type' =>
                        $greenhouse->crop_type,

                    'area' =>
                        $greenhouse->area !== null
                            ? (float) $greenhouse->area
                            : null,

                    'location' =>
                        $greenhouse->location,

                    'planting_date' =>
                        optional(
                            $greenhouse->planting_date
                        )->format(
                            'Y-m-d'
                        ),
                ],

                'period' => [
                    'start_date' =>
                        $startDate->format(
                            'Y-m-d'
                        ),

                    'end_date' =>
                        $endDate->format(
                            'Y-m-d'
                        ),
                ],

                'generated_by' => [
                    'id' =>
                        $user->id,

                    'name' =>
                        $user->name,

                    'email' =>
                        $user->email,
                ],

                'generated_at' =>
                    now()->format(
                        'Y-m-d H:i:s'
                    ),

                'metrics' =>
                    $metrics,

                'alerts' => [
                    'summary' =>
                        $alertsSummary,

                    'recent' =>
                        $recentAlerts,
                ],

                'irrigation' => [
                    'summary' =>
                        $irrigationSummary,

                    'recent' =>
                        $recentIrrigations,
                ],
            ],
        ]);
    }


    /**
 * Genera el reporte de monitoreo en formato PDF.
 */
public function pdf(Request $request): Response
{
    /*
    |--------------------------------------------------------------------------
    | Reutilizar la información del reporte
    |--------------------------------------------------------------------------
    */

    $reportResponse =
        $this->index($request);


    /*
    |--------------------------------------------------------------------------
    | Si ocurrió algún error, conservar la respuesta original
    |--------------------------------------------------------------------------
    */

    if (
        $reportResponse->getStatusCode() !== 200
    ) {

        return $reportResponse;
    }


    $responseData =
        $reportResponse->getData(true);


    $report =
        $responseData['report'] ?? null;


    if (!$report) {

        return response()->json([
            'message' =>
                'No fue posible generar la información del reporte.',
        ], 422);
    }


    /*
    |--------------------------------------------------------------------------
    | Nombre del archivo
    |--------------------------------------------------------------------------
    */

    $greenhouseName =
        $report['greenhouse']['name']
        ?? 'invernadero';


    $fileName =
        'reporte-lumatek-'
        . Str::slug(
            $greenhouseName
        )
        . '-'
        . now()->format(
            'Ymd-His'
        )
        . '.pdf';


    /*
    |--------------------------------------------------------------------------
    | Generar PDF
    |--------------------------------------------------------------------------
    */

    $pdf =
        Pdf::loadView(
            'reports.pdf',
            [
                'report' =>
                    $report,
            ]
        )
        ->setPaper(
            'a4',
            'portrait'
        );


    return $pdf->download(
        $fileName
    );
}


    /**
     * Obtiene mínimo, máximo y promedio
     * de una variable monitoreada.
     */
    private function metricSummary(
        int $greenhouseId,
        string $metric,
        Carbon $startDate,
        Carbon $endDate
    ): array {

        $readings = Reading::query()
            ->whereBetween(
                'recorded_at',
                [
                    $startDate,
                    $endDate,
                ]
            )
            ->whereHas(
                'sensor',
                function ($query) use ($metric) {
                    $query->where(
                        'sensor_type',
                        $metric
                    );
                }
            )
            ->whereHas(
                'sensor.device.zone',
                function ($query) use ($greenhouseId) {
                    $query->where(
                        'greenhouse_id',
                        $greenhouseId
                    );
                }
            )
            ->get([
                'value',
            ]);

        $values = $readings
            ->pluck('value')
            ->map(
                fn ($value) =>
                    (float) $value
            );

        return [
            'type' =>
                $metric,

            'label' =>
                $this->metricLabel(
                    $metric
                ),

            'unit' =>
                $this->metricUnit(
                    $metric
                ),

            'total_readings' =>
                $values->count(),

            'average' =>
                $values->isNotEmpty()
                    ? round(
                        $values->avg(),
                        2
                    )
                    : null,

            'minimum' =>
                $values->isNotEmpty()
                    ? round(
                        $values->min(),
                        2
                    )
                    : null,

            'maximum' =>
                $values->isNotEmpty()
                    ? round(
                        $values->max(),
                        2
                    )
                    : null,
        ];
    }


    private function metricLabel(
        string $metric
    ): string {

        return match ($metric) {

            'temperature' =>
                'Temperatura',

            'soil_humidity' =>
                'Humedad del suelo',

            'ambient_humidity' =>
                'Humedad ambiental',

            default =>
                'Medición',
        };
    }


    private function metricUnit(
        string $metric
    ): string {

        return match ($metric) {

            'temperature' =>
                '°C',

            'soil_humidity',
            'ambient_humidity' =>
                '%',

            default =>
                '',
        };
    }


    private function alertTypeLabel(
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


    private function alertStatusLabel(
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


    private function irrigationStatusLabel(
        string $status
    ): string {

        return match ($status) {

            'started' =>
                'En curso',

            'completed' =>
                'Completado',

            'cancelled' =>
                'Cancelado',

            'failed' =>
                'Fallido',

            default =>
                $status,
        };
    }
}