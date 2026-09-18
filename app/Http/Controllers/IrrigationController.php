<?php

namespace App\Http\Controllers;

use App\Models\IrrigationEvent;
use App\Models\Reading;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IrrigationController extends Controller
{
    /**
     * Listado e historial de riegos de la empresa autenticada.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $query = IrrigationEvent::query()
            ->whereHas(
                'zone.greenhouse',
                function ($query) use ($user) {
                    $query->where(
                        'company_id',
                        $user->company_id
                    );
                }
            )
            ->with([
                'zone.greenhouse',
                'user',
                'triggerReading.sensor',
            ]);


        /*
        |--------------------------------------------------------------------------
        | Filtro por invernadero
        |--------------------------------------------------------------------------
        */

        if ($request->filled('greenhouse_id')) {

            $greenhouseId =
                (int) $request->greenhouse_id;


            $query->whereHas(
                'zone',
                function ($query) use ($greenhouseId) {
                    $query->where(
                        'greenhouse_id',
                        $greenhouseId
                    );
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Filtro por zona
        |--------------------------------------------------------------------------
        */

        if ($request->filled('zone_id')) {

            $query->where(
                'zone_id',
                (int) $request->zone_id
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Filtro por modo
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled('mode')
            &&
            in_array(
                $request->mode,
                ['manual', 'automatic'],
                true
            )
        ) {

            $query->where(
                'mode',
                $request->mode
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Filtro por estado
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled('status')
            &&
            in_array(
                $request->status,
                [
                    'started',
                    'completed',
                    'cancelled',
                    'failed',
                ],
                true
            )
        ) {

            $query->where(
                'status',
                $request->status
            );
        }


        $events = $query
            ->orderByDesc('started_at')
            ->get()
            ->map(
                fn ($event) =>
                    $this->formatEvent($event)
            );


        return response()->json([
            'data' => $events,
        ]);
    }


    /**
     * Inicia un riego manual.
     */
    public function startManual(
        Request $request,
        Zone $zone
    ): JsonResponse {

        $user = auth('api')->user();


        /*
        |--------------------------------------------------------------------------
        | Seguridad multiempresa
        |--------------------------------------------------------------------------
        */

        $this->ensureZoneBelongsToCompany(
            $zone,
            $user->company_id
        );


        /*
        |--------------------------------------------------------------------------
        | Zona activa
        |--------------------------------------------------------------------------
        */

        if ($zone->status !== 'active') {

            return response()->json([
                'message' =>
                    'No se puede iniciar el riego porque la zona está inactiva.',
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | Validación
        |--------------------------------------------------------------------------
        */

        $data = $request->validate([
            'water_liters' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | Evitar dos riegos simultáneos en la misma zona
        |--------------------------------------------------------------------------
        */

        $activeIrrigation =
            IrrigationEvent::query()
                ->where(
                    'zone_id',
                    $zone->id
                )
                ->where(
                    'status',
                    'started'
                )
                ->exists();


        if ($activeIrrigation) {

            return response()->json([
                'message' =>
                    'Ya existe un riego activo en esta zona.',
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | Última humedad del suelo
        |--------------------------------------------------------------------------
        */

        $soilReading =
            $this->latestSoilReading(
                $zone->id
            );


        /*
        |--------------------------------------------------------------------------
        | Crear evento
        |--------------------------------------------------------------------------
        */

        $event = DB::transaction(
            function () use (
                $zone,
                $user,
                $data,
                $soilReading
            ) {

                return IrrigationEvent::create([
                    'zone_id' =>
                        $zone->id,

                    'user_id' =>
                        $user->id,

                    'trigger_reading_id' =>
                        null,

                    'mode' =>
                        'manual',

                    'status' =>
                        'started',

                    'duration_minutes' =>
                        null,

                    'water_liters' =>
                        $data['water_liters']
                        ?? null,

                    'soil_humidity_before' =>
                        $soilReading
                            ? (float) $soilReading->value
                            : null,

                    'soil_humidity_after' =>
                        null,

                    'started_at' =>
                        now(),

                    'ended_at' =>
                        null,

                    'notes' =>
                        $data['notes']
                        ?? null,
                ]);
            }
        );


        $event->load([
            'zone.greenhouse',
            'user',
            'triggerReading.sensor',
        ]);


        return response()->json([
            'message' =>
                'Riego manual iniciado correctamente.',

            'data' =>
                $this->formatEvent($event),
        ], 201);
    }


    /**
     * Finaliza un riego activo.
     */
    public function complete(
        Request $request,
        IrrigationEvent $irrigationEvent
    ): JsonResponse {

        $user = auth('api')->user();


        /*
        |--------------------------------------------------------------------------
        | Seguridad multiempresa
        |--------------------------------------------------------------------------
        */

        $this->ensureEventBelongsToCompany(
            $irrigationEvent,
            $user->company_id
        );


        /*
        |--------------------------------------------------------------------------
        | Estado válido
        |--------------------------------------------------------------------------
        */

        if (
            $irrigationEvent->status
            !== 'started'
        ) {

            return response()->json([
                'message' =>
                    'Solamente se puede finalizar un riego que esté activo.',
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | Validación
        |--------------------------------------------------------------------------
        */

        $data = $request->validate([
            'water_liters' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | Lectura posterior al inicio del riego
        |--------------------------------------------------------------------------
        */

        $soilReadingAfter =
            $this->latestSoilReadingAfter(
                $irrigationEvent->zone_id,
                $irrigationEvent->started_at
            );


        $endedAt =
            now();


        /*
        |--------------------------------------------------------------------------
        | Duración
        |--------------------------------------------------------------------------
        */

        $durationMinutes =
            max(
                1,
                $irrigationEvent
                    ->started_at
                    ->diffInMinutes(
                        $endedAt
                    )
            );


        DB::transaction(
            function () use (
                $irrigationEvent,
                $data,
                $soilReadingAfter,
                $endedAt,
                $durationMinutes
            ) {

                $irrigationEvent->update([
                    'status' =>
                        'completed',

                    'duration_minutes' =>
                        $durationMinutes,

                    'water_liters' =>
                        $data['water_liters']
                        ?? $irrigationEvent->water_liters,

                    'soil_humidity_after' =>
                        $soilReadingAfter
                            ? (float) $soilReadingAfter->value
                            : null,

                    'ended_at' =>
                        $endedAt,

                    'notes' =>
                        $data['notes']
                        ?? $irrigationEvent->notes,
                ]);
            }
        );


        $irrigationEvent->load([
            'zone.greenhouse',
            'user',
            'triggerReading.sensor',
        ]);


        return response()->json([
            'message' =>
                'Riego finalizado correctamente.',

            'data' =>
                $this->formatEvent(
                    $irrigationEvent
                ),
        ]);
    }


    /**
     * Cancela un riego activo.
     */
    public function cancel(
        Request $request,
        IrrigationEvent $irrigationEvent
    ): JsonResponse {

        $user = auth('api')->user();


        /*
        |--------------------------------------------------------------------------
        | Seguridad multiempresa
        |--------------------------------------------------------------------------
        */

        $this->ensureEventBelongsToCompany(
            $irrigationEvent,
            $user->company_id
        );


        /*
        |--------------------------------------------------------------------------
        | Estado válido
        |--------------------------------------------------------------------------
        */

        if (
            $irrigationEvent->status
            !== 'started'
        ) {

            return response()->json([
                'message' =>
                    'Solamente se puede cancelar un riego que esté activo.',
            ], 422);
        }


        $data = $request->validate([
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);


        $endedAt =
            now();


        $durationMinutes =
            max(
                1,
                $irrigationEvent
                    ->started_at
                    ->diffInMinutes(
                        $endedAt
                    )
            );


        $irrigationEvent->update([
            'status' =>
                'cancelled',

            'duration_minutes' =>
                $durationMinutes,

            'ended_at' =>
                $endedAt,

            'notes' =>
                $data['notes']
                ?? $irrigationEvent->notes,
        ]);


        $irrigationEvent->load([
            'zone.greenhouse',
            'user',
            'triggerReading.sensor',
        ]);


        return response()->json([
            'message' =>
                'Riego cancelado correctamente.',

            'data' =>
                $this->formatEvent(
                    $irrigationEvent
                ),
        ]);
    }


    /**
     * Muestra un evento específico.
     */
    public function show(
        IrrigationEvent $irrigationEvent
    ): JsonResponse {

        $user = auth('api')->user();


        $this->ensureEventBelongsToCompany(
            $irrigationEvent,
            $user->company_id
        );


        $irrigationEvent->load([
            'zone.greenhouse',
            'user',
            'triggerReading.sensor',
        ]);


        return response()->json([
            'data' =>
                $this->formatEvent(
                    $irrigationEvent
                ),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | MÉTODOS AUXILIARES
    |--------------------------------------------------------------------------
    */


    /**
     * Última lectura de humedad del suelo de una zona.
     */
    private function latestSoilReading(
        int $zoneId
    ): ?Reading {

        return Reading::query()
            ->whereHas(
                'sensor.device',
                function ($query) use ($zoneId) {

                    $query->where(
                        'zone_id',
                        $zoneId
                    );
                }
            )
            ->whereHas(
                'sensor',
                function ($query) {

                    $query->where(
                        'sensor_type',
                        'soil_humidity'
                    );
                }
            )
            ->orderByDesc(
                'recorded_at'
            )
            ->first();
    }


    /**
     * Última lectura de humedad registrada después
     * de iniciar el riego.
     */
    private function latestSoilReadingAfter(
        int $zoneId,
        $startedAt
    ): ?Reading {

        return Reading::query()
            ->whereHas(
                'sensor.device',
                function ($query) use ($zoneId) {

                    $query->where(
                        'zone_id',
                        $zoneId
                    );
                }
            )
            ->whereHas(
                'sensor',
                function ($query) {

                    $query->where(
                        'sensor_type',
                        'soil_humidity'
                    );
                }
            )
            ->where(
                'recorded_at',
                '>=',
                $startedAt
            )
            ->orderByDesc(
                'recorded_at'
            )
            ->first();
    }


    /**
     * Comprueba que la zona pertenezca
     * a la empresa autenticada.
     */
    private function ensureZoneBelongsToCompany(
        Zone $zone,
        int $companyId
    ): void {

        $belongs =
            Zone::query()
                ->where(
                    'id',
                    $zone->id
                )
                ->whereHas(
                    'greenhouse',
                    function ($query) use ($companyId) {

                        $query->where(
                            'company_id',
                            $companyId
                        );
                    }
                )
                ->exists();


        abort_unless(
            $belongs,
            404,
            'Zona no encontrada.'
        );
    }


    /**
     * Comprueba que el evento pertenezca
     * a la empresa autenticada.
     */
    private function ensureEventBelongsToCompany(
        IrrigationEvent $event,
        int $companyId
    ): void {

        $belongs =
            IrrigationEvent::query()
                ->where(
                    'id',
                    $event->id
                )
                ->whereHas(
                    'zone.greenhouse',
                    function ($query) use ($companyId) {

                        $query->where(
                            'company_id',
                            $companyId
                        );
                    }
                )
                ->exists();


        abort_unless(
            $belongs,
            404,
            'Evento de riego no encontrado.'
        );
    }


    /**
     * Formato uniforme para API.
     */
    private function formatEvent(
        IrrigationEvent $event
    ): array {

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
                match ($event->status) {

                    'started' =>
                        'En curso',

                    'completed' =>
                        'Completado',

                    'cancelled' =>
                        'Cancelado',

                    'failed' =>
                        'Fallido',

                    default =>
                        $event->status,
                },

            'duration_minutes' =>
                $event->duration_minutes,

            'water_liters' =>
                $event->water_liters !== null
                    ? (float) $event->water_liters
                    : null,

            'soil_humidity_before' =>
                $event->soil_humidity_before !== null
                    ? (float) $event->soil_humidity_before
                    : null,

            'soil_humidity_after' =>
                $event->soil_humidity_after !== null
                    ? (float) $event->soil_humidity_after
                    : null,

            'started_at' =>
                $event->started_at
                    ?->toDateTimeString(),

            'ended_at' =>
                $event->ended_at
                    ?->toDateTimeString(),

            'notes' =>
                $event->notes,

            'zone' => [
                'id' =>
                    $event->zone?->id,

                'name' =>
                    $event->zone?->name,
            ],

            'greenhouse' => [
                'id' =>
                    $event
                        ->zone
                        ?->greenhouse
                        ?->id,

                'name' =>
                    $event
                        ->zone
                        ?->greenhouse
                        ?->name,
            ],

            'user' =>
                $event->user
                    ? [
                        'id' =>
                            $event->user->id,

                        'name' =>
                            $event->user->name,
                    ]
                    : null,

            'trigger_reading' =>
                $event->triggerReading
                    ? [
                        'id' =>
                            $event
                                ->triggerReading
                                ->id,

                        'value' =>
                            (float) $event
                                ->triggerReading
                                ->value,

                        'recorded_at' =>
                            $event
                                ->triggerReading
                                ->recorded_at
                                ?->toDateTimeString(),
                    ]
                    : null,
        ];
    }
}