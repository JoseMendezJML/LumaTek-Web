<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use App\Models\ZoneIrrigationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneIrrigationSettingController extends Controller
{
    /**
     * Consulta la configuración de riego automático de una zona.
     */
    public function show(
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
        | Configuración
        |--------------------------------------------------------------------------
        |
        | Si todavía no existe, devolvemos los valores predeterminados
        | sin crear nada en la base de datos.
        |
        */

        $setting = ZoneIrrigationSetting::query()
            ->where(
                'zone_id',
                $zone->id
            )
            ->first();


        /*
        |--------------------------------------------------------------------------
        | Umbral actual de humedad del suelo
        |--------------------------------------------------------------------------
        */

        $zone->load([
            'greenhouse.thresholds',
        ]);


        $soilThreshold =
            $zone
                ->greenhouse
                ?->thresholds
                ?->firstWhere(
                    'variable',
                    'soil_humidity'
                );


        return response()->json([
            'data' => [
                'zone' => [
                    'id' =>
                        $zone->id,

                    'name' =>
                        $zone->name,

                    'greenhouse_id' =>
                        $zone->greenhouse?->id,

                    'greenhouse_name' =>
                        $zone->greenhouse?->name,
                ],

                'automatic_enabled' =>
                    $setting
                        ? (bool) $setting->automatic_enabled
                        : false,

                'duration_minutes' =>
                    $setting
                        ? (int) $setting->duration_minutes
                        : 10,

                'water_liters' =>
                    $setting
                    && $setting->water_liters !== null
                        ? (float) $setting->water_liters
                        : null,

                'cooldown_minutes' =>
                    $setting
                        ? (int) $setting->cooldown_minutes
                        : 60,

                'soil_humidity_threshold' => [
                    'min_value' =>
                        $soilThreshold?->min_value !== null
                            ? (float) $soilThreshold->min_value
                            : null,

                    'max_value' =>
                        $soilThreshold?->max_value !== null
                            ? (float) $soilThreshold->max_value
                            : null,
                ],
            ],
        ]);
    }


    /**
     * Guarda o actualiza la configuración de riego automático.
     */
    public function update(
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
        | Validación
        |--------------------------------------------------------------------------
        */

        $data = $request->validate([
            'automatic_enabled' => [
                'required',
                'boolean',
            ],

            'duration_minutes' => [
                'required',
                'integer',
                'min:1',
                'max:1440',
            ],

            'water_liters' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],

            'cooldown_minutes' => [
                'required',
                'integer',
                'min:1',
                'max:10080',
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | No permitir activar automatización en una zona inactiva
        |--------------------------------------------------------------------------
        */

        if (
            $data['automatic_enabled']
            &&
            $zone->status !== 'active'
        ) {

            return response()->json([
                'message' =>
                    'No se puede activar el riego automático en una zona inactiva.',
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | Verificar que exista umbral mínimo de humedad del suelo
        |--------------------------------------------------------------------------
        */

        $zone->load([
            'greenhouse.thresholds',
        ]);


        $soilThreshold =
            $zone
                ->greenhouse
                ?->thresholds
                ?->firstWhere(
                    'variable',
                    'soil_humidity'
                );


        if (
            $data['automatic_enabled']
            &&
            (
                !$soilThreshold
                ||
                $soilThreshold->min_value === null
            )
        ) {

            return response()->json([
                'message' =>
                    'Configura primero el umbral mínimo de humedad del suelo del invernadero.',
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | Crear o actualizar configuración
        |--------------------------------------------------------------------------
        */

        $setting =
            ZoneIrrigationSetting::updateOrCreate(
                [
                    'zone_id' =>
                        $zone->id,
                ],
                [
                    'automatic_enabled' =>
                        $data['automatic_enabled'],

                    'duration_minutes' =>
                        $data['duration_minutes'],

                    'water_liters' =>
                        $data['water_liters']
                        ?? null,

                    'cooldown_minutes' =>
                        $data['cooldown_minutes'],
                ]
            );


        /*
        |--------------------------------------------------------------------------
        | Respuesta
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'message' =>
                $setting->automatic_enabled
                    ? 'Riego automático configurado y activado correctamente.'
                    : 'Configuración guardada. El riego automático está desactivado.',

            'data' => [
                'id' =>
                    $setting->id,

                'zone_id' =>
                    $setting->zone_id,

                'automatic_enabled' =>
                    (bool) $setting->automatic_enabled,

                'duration_minutes' =>
                    (int) $setting->duration_minutes,

                'water_liters' =>
                    $setting->water_liters !== null
                        ? (float) $setting->water_liters
                        : null,

                'cooldown_minutes' =>
                    (int) $setting->cooldown_minutes,

                'soil_humidity_minimum' =>
                    $soilThreshold?->min_value !== null
                        ? (float) $soilThreshold->min_value
                        : null,
            ],
        ]);
    }


    /**
     * Comprueba que la zona pertenezca a la empresa autenticada.
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
}