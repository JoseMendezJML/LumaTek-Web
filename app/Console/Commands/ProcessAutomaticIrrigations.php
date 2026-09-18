<?php

namespace App\Console\Commands;

use App\Models\IrrigationEvent;
use App\Models\Reading;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessAutomaticIrrigations extends Command
{
    /**
     * Nombre del comando.
     */
    protected $signature = 'irrigation:process-automatic';


    /**
     * Descripción.
     */
    protected $description =
        'Finaliza los riegos automáticos que ya cumplieron su duración programada.';


    /**
     * Ejecutar comando.
     */
    public function handle(): int
    {
        /*
        |--------------------------------------------------------------------------
        | Buscar riegos automáticos activos
        |--------------------------------------------------------------------------
        */

        $events =
            IrrigationEvent::query()
                ->where(
                    'mode',
                    'automatic'
                )
                ->where(
                    'status',
                    'started'
                )
                ->orderBy(
                    'started_at'
                )
                ->get();


        /*
        |--------------------------------------------------------------------------
        | No hay riegos activos
        |--------------------------------------------------------------------------
        */

        if ($events->isEmpty()) {

            $this->info(
                'No hay riegos automáticos pendientes.'
            );

            return self::SUCCESS;
        }


        $completed =
            0;


        /*
        |--------------------------------------------------------------------------
        | Revisar cada riego
        |--------------------------------------------------------------------------
        */

        foreach ($events as $event) {

            /*
            |--------------------------------------------------------------------------
            | Validar duración
            |--------------------------------------------------------------------------
            */

            $durationMinutes =
                (int) $event->duration_minutes;


            if ($durationMinutes <= 0) {

                $this->warn(
                    "El riego #{$event->id} no tiene una duración válida."
                );

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Momento programado de finalización
            |--------------------------------------------------------------------------
            */

            $scheduledEnd =
                $event
                    ->started_at
                    ->copy()
                    ->addMinutes(
                        $durationMinutes
                    );


            /*
            |--------------------------------------------------------------------------
            | Todavía no ha terminado
            |--------------------------------------------------------------------------
            */

            if (now()->lt($scheduledEnd)) {

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Buscar humedad al finalizar
            |--------------------------------------------------------------------------
            |
            | Se utiliza la última lectura de humedad del suelo
            | registrada entre el inicio y el momento programado
            | de finalización.
            |
            */

            $soilReadingAfter =
                $this->latestSoilReadingUntil(
                    $event->zone_id,
                    $event->started_at,
                    $scheduledEnd
                );


            /*
            |--------------------------------------------------------------------------
            | Finalizar evento
            |--------------------------------------------------------------------------
            */

            DB::transaction(
                function () use (
                    $event,
                    $scheduledEnd,
                    $soilReadingAfter
                ) {

                    /*
                    | Volvemos a comprobar el estado dentro
                    | de la transacción para evitar procesarlo
                    | dos veces.
                    */

                    $event->refresh();


                    if (
                        $event->status
                        !== 'started'
                    ) {

                        return;
                    }


                    $event->update([
                        'status' =>
                            'completed',

                        'soil_humidity_after' =>
                            $soilReadingAfter
                                ? (float) $soilReadingAfter->value
                                : null,

                        'ended_at' =>
                            $scheduledEnd,
                    ]);
                }
            );


            $completed++;


            $this->info(
                "Riego automático #{$event->id} finalizado."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Resultado
        |--------------------------------------------------------------------------
        */

        if ($completed === 0) {

            $this->info(
                'Los riegos automáticos activos todavía no han cumplido su duración.'
            );

        } else {

            $this->info(
                "Riegos automáticos finalizados: {$completed}."
            );
        }


        return self::SUCCESS;
    }


    /**
     * Obtiene la última lectura de humedad del suelo
     * registrada antes de finalizar el riego.
     */
    private function latestSoilReadingUntil(
        int $zoneId,
        $startedAt,
        $endedAt
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
            ->where(
                'recorded_at',
                '<=',
                $endedAt
            )
            ->orderByDesc(
                'recorded_at'
            )
            ->first();
    }
}