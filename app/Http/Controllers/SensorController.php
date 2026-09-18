<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SensorController extends Controller
{
    /**
     * Lista los sensores asociados a un dispositivo.
     */
    public function index(
        Device $device
    ): JsonResponse {

        $user = auth('api')->user();

        $device->load(
            'zone.greenhouse'
        );

        /*
        |--------------------------------------------------------------------------
        | Aislamiento por empresa
        |--------------------------------------------------------------------------
        */

        if (
            $device
                ->zone
                ->greenhouse
                ->company_id
            !==
            $user->company_id
        ) {

            return response()->json([
                'message' =>
                    'No tienes permiso para consultar este dispositivo.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Obtener sensores
        |--------------------------------------------------------------------------
        */

        $sensors = $device
            ->sensors()
            ->with([
                'latestReading',
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' =>
                $sensors,
        ]);
    }


    /**
     * Registra un nuevo sensor.
     */
    public function store(
        Request $request,
        Device $device
    ): JsonResponse {

        $user = auth('api')->user();

        $device->load(
            'zone.greenhouse'
        );

        /*
        |--------------------------------------------------------------------------
        | Aislamiento por empresa
        |--------------------------------------------------------------------------
        */

        if (
            $device
                ->zone
                ->greenhouse
                ->company_id
            !==
            $user->company_id
        ) {

            return response()->json([
                'message' =>
                    'No tienes permiso para modificar este dispositivo.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Validar dispositivo y zona activos
        |--------------------------------------------------------------------------
        */

        if ($device->status !== 'active') {

            return response()->json([
                'message' =>
                    'No puedes agregar sensores a un dispositivo inactivo.',
            ], 422);
        }

        if ($device->zone->status !== 'active') {

            return response()->json([
                'message' =>
                    'No puedes agregar sensores a una zona inactiva.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Validación
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'sensor_code' => [
                'required',
                'string',
                'max:100',
                'unique:sensors,sensor_code',
            ],

            'sensor_type' => [
                'required',
                'in:temperature,soil_humidity,ambient_humidity',
            ],

            'model' => [
                'nullable',
                'string',
                'max:150',
            ],

            'position_x' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],

            'position_y' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],
        ], [
            'name.required' =>
                'El nombre del sensor es obligatorio.',

            'sensor_code.required' =>
                'El código del sensor es obligatorio.',

            'sensor_code.unique' =>
                'Ya existe un sensor registrado con ese código.',

            'sensor_type.required' =>
                'El tipo de sensor es obligatorio.',

            'sensor_type.in' =>
                'El tipo de sensor seleccionado no es válido.',

            'position_x.between' =>
                'La posición X debe estar entre 0 y 100.',

            'position_y.between' =>
                'La posición Y debe estar entre 0 y 100.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Determinar unidad automáticamente
        |--------------------------------------------------------------------------
        */

        $unit = $this->unitForType(
            $validated['sensor_type']
        );

        /*
        |--------------------------------------------------------------------------
        | Crear sensor
        |--------------------------------------------------------------------------
        */

        $sensor = $device
            ->sensors()
            ->create([
                'name' =>
                    trim(
                        $validated['name']
                    ),

                'sensor_code' =>
                    strtoupper(
                        trim(
                            $validated['sensor_code']
                        )
                    ),

                'sensor_type' =>
                    $validated['sensor_type'],

                'unit' =>
                    $unit,

                'model' =>
                    isset($validated['model'])
                    && $validated['model'] !== ''
                        ? trim(
                            $validated['model']
                        )
                        : null,

                'position_x' =>
                    $validated['position_x']
                    ?? null,

                'position_y' =>
                    $validated['position_y']
                    ?? null,

                'status' =>
                    'active',
            ]);

        return response()->json([
            'message' =>
                'Sensor registrado correctamente.',

            'data' =>
                $sensor,
        ], 201);
    }


    /**
     * Actualiza un sensor existente.
     */
    public function update(
        Request $request,
        Sensor $sensor
    ): JsonResponse {

        $user = auth('api')->user();

        $sensor->load(
            'device.zone.greenhouse'
        );

        /*
        |--------------------------------------------------------------------------
        | Aislamiento por empresa
        |--------------------------------------------------------------------------
        */

        if (
            $sensor
                ->device
                ->zone
                ->greenhouse
                ->company_id
            !==
            $user->company_id
        ) {

            return response()->json([
                'message' =>
                    'No tienes permiso para modificar este sensor.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Validación
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'sensor_code' => [
                'required',
                'string',
                'max:100',
                'unique:sensors,sensor_code,' . $sensor->id,
            ],

            'sensor_type' => [
                'required',
                'in:temperature,soil_humidity,ambient_humidity',
            ],

            'model' => [
                'nullable',
                'string',
                'max:150',
            ],

            'position_x' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],

            'position_y' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],

            'status' => [
                'nullable',
                'in:active,inactive',
            ],
        ], [
            'name.required' =>
                'El nombre del sensor es obligatorio.',

            'sensor_code.required' =>
                'El código del sensor es obligatorio.',

            'sensor_code.unique' =>
                'Ya existe otro sensor registrado con ese código.',

            'sensor_type.required' =>
                'El tipo de sensor es obligatorio.',

            'sensor_type.in' =>
                'El tipo de sensor seleccionado no es válido.',

            'position_x.between' =>
                'La posición X debe estar entre 0 y 100.',

            'position_y.between' =>
                'La posición Y debe estar entre 0 y 100.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Unidad automática
        |--------------------------------------------------------------------------
        */

        $unit = $this->unitForType(
            $validated['sensor_type']
        );

        /*
        |--------------------------------------------------------------------------
        | Actualizar
        |--------------------------------------------------------------------------
        */

        $sensor->update([
            'name' =>
                trim(
                    $validated['name']
                ),

            'sensor_code' =>
                strtoupper(
                    trim(
                        $validated['sensor_code']
                    )
                ),

            'sensor_type' =>
                $validated['sensor_type'],

            'unit' =>
                $unit,

            'model' =>
                isset($validated['model'])
                && $validated['model'] !== ''
                    ? trim(
                        $validated['model']
                    )
                    : null,

            'position_x' =>
                $validated['position_x']
                ?? null,

            'position_y' =>
                $validated['position_y']
                ?? null,

            'status' =>
                $validated['status']
                ?? $sensor->status,
        ]);

        return response()->json([
            'message' =>
                'Sensor actualizado correctamente.',

            'data' =>
                $sensor->fresh(),
        ]);
    }


    /**
     * Activa o desactiva un sensor.
     *
     * No eliminamos físicamente el sensor para conservar
     * lecturas y alertas históricas.
     */
    public function changeStatus(
        Request $request,
        Sensor $sensor
    ): JsonResponse {

        $user = auth('api')->user();

        $sensor->load(
            'device.zone.greenhouse'
        );

        /*
        |--------------------------------------------------------------------------
        | Aislamiento por empresa
        |--------------------------------------------------------------------------
        */

        if (
            $sensor
                ->device
                ->zone
                ->greenhouse
                ->company_id
            !==
            $user->company_id
        ) {

            return response()->json([
                'message' =>
                    'No tienes permiso para modificar este sensor.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'in:active,inactive',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | No activar sensor si el dispositivo está inactivo
        |--------------------------------------------------------------------------
        */

        if (
            $validated['status'] === 'active'
            && $sensor->device->status !== 'active'
        ) {

            return response()->json([
                'message' =>
                    'No puedes activar el sensor mientras su dispositivo esté inactivo.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | No activar sensor si la zona está inactiva
        |--------------------------------------------------------------------------
        */

        if (
            $validated['status'] === 'active'
            && $sensor->device->zone->status !== 'active'
        ) {

            return response()->json([
                'message' =>
                    'No puedes activar el sensor mientras su zona esté inactiva.',
            ], 422);
        }

        $sensor->update([
            'status' =>
                $validated['status'],
        ]);

        return response()->json([
            'message' =>
                $sensor->status === 'active'
                    ? 'Sensor activado correctamente.'
                    : 'Sensor desactivado correctamente.',

            'data' =>
                $sensor,
        ]);
    }


    /**
     * Obtiene la unidad correspondiente al tipo de sensor.
     */
    private function unitForType(
        string $sensorType
    ): string {

        return match ($sensorType) {

            'temperature' =>
                '°C',

            'soil_humidity' =>
                '%',

            'ambient_humidity' =>
                '%',

            default =>
                '',
        };
    }
}