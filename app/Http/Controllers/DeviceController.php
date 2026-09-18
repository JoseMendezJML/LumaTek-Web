<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * Lista los dispositivos de una zona.
     */
    public function index(
        Zone $zone
    ): JsonResponse {

        $user = auth('api')->user();

        $zone->load(
            'greenhouse'
        );

        /*
        |--------------------------------------------------------------------------
        | Aislamiento por empresa
        |--------------------------------------------------------------------------
        */

        if ($zone->greenhouse->company_id !== $user->company_id) {
            return response()->json([
                'message' =>
                    'No tienes permiso para consultar esta zona.',
            ], 403);
        }

        $devices = $zone
            ->devices()
            ->withCount([
                'sensors',
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' =>
                $devices,
        ]);
    }


    /**
     * Registra un nuevo dispositivo dentro de una zona.
     */
    public function store(
        Request $request,
        Zone $zone
    ): JsonResponse {

        $user = auth('api')->user();

        $zone->load(
            'greenhouse'
        );

        /*
        |--------------------------------------------------------------------------
        | Aislamiento por empresa
        |--------------------------------------------------------------------------
        */

        if ($zone->greenhouse->company_id !== $user->company_id) {
            return response()->json([
                'message' =>
                    'No tienes permiso para modificar esta zona.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | No registrar dispositivos en zonas inactivas
        |--------------------------------------------------------------------------
        */

        if ($zone->status !== 'active') {
            return response()->json([
                'message' =>
                    'No puedes agregar dispositivos a una zona inactiva.',
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

            'device_code' => [
                'required',
                'string',
                'max:100',
                'unique:devices,device_code',
            ],

            'device_type' => [
                'required',
                'string',
                'max:100',
            ],

            'connection_type' => [
                'required',
                'in:wifi,ethernet,lora,simulation',
            ],
        ], [
            'name.required' =>
                'El nombre del dispositivo es obligatorio.',

            'device_code.required' =>
                'El código del dispositivo es obligatorio.',

            'device_code.unique' =>
                'Ya existe un dispositivo registrado con ese código.',

            'device_type.required' =>
                'El tipo de dispositivo es obligatorio.',

            'connection_type.required' =>
                'El tipo de conexión es obligatorio.',

            'connection_type.in' =>
                'El tipo de conexión debe ser WiFi, Ethernet, LoRa o Simulación.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Crear dispositivo
        |--------------------------------------------------------------------------
        */

        $device = $zone
            ->devices()
            ->create([
                'name' =>
                    trim(
                        $validated['name']
                    ),

                'device_code' =>
                    strtoupper(
                        trim(
                            $validated['device_code']
                        )
                    ),

                'device_type' =>
                    trim(
                        $validated['device_type']
                    ),

                'connection_type' =>
                    $validated['connection_type'],

                'status' =>
                    'active',

                'last_connection_at' =>
                    null,
            ]);

        return response()->json([
            'message' =>
                'Dispositivo registrado correctamente.',

            'data' =>
                $device,
        ], 201);
    }


    /**
     * Actualiza un dispositivo existente.
     */
    public function update(
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
        | Validación
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'device_code' => [
                'required',
                'string',
                'max:100',
                'unique:devices,device_code,' . $device->id,
            ],

            'device_type' => [
                'required',
                'string',
                'max:100',
            ],

            'connection_type' => [
                'required',
                'in:wifi,ethernet,lora,simulation',
            ],

            'status' => [
                'nullable',
                'in:active,inactive',
            ],
        ], [
            'name.required' =>
                'El nombre del dispositivo es obligatorio.',

            'device_code.required' =>
                'El código del dispositivo es obligatorio.',

            'device_code.unique' =>
                'Ya existe otro dispositivo con ese código.',

            'device_type.required' =>
                'El tipo de dispositivo es obligatorio.',

            'connection_type.required' =>
                'El tipo de conexión es obligatorio.',

            'connection_type.in' =>
                'El tipo de conexión debe ser WiFi, Ethernet, LoRa o Simulación.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Actualizar
        |--------------------------------------------------------------------------
        */

        $device->update([
            'name' =>
                trim(
                    $validated['name']
                ),

            'device_code' =>
                strtoupper(
                    trim(
                        $validated['device_code']
                    )
                ),

            'device_type' =>
                trim(
                    $validated['device_type']
                ),

            'connection_type' =>
                $validated['connection_type'],

            'status' =>
                $validated['status']
                ?? $device->status,
        ]);

        return response()->json([
            'message' =>
                'Dispositivo actualizado correctamente.',

            'data' =>
                $device->fresh(),
        ]);
    }


    /**
     * Activa o desactiva un dispositivo.
     *
     * No eliminamos físicamente el dispositivo para conservar
     * los sensores y las lecturas históricas.
     */
    public function changeStatus(
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

        $validated = $request->validate([
            'status' => [
                'required',
                'in:active,inactive',
            ],
        ]);

        $device->update([
            'status' =>
                $validated['status'],
        ]);

        return response()->json([
            'message' =>
                $device->status === 'active'
                    ? 'Dispositivo activado correctamente.'
                    : 'Dispositivo desactivado correctamente.',

            'data' =>
                $device,
        ]);
    }
}