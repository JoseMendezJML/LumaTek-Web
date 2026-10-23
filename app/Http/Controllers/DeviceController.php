<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\User;
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
                'message' => 'No tienes permiso para consultar esta zona.',
            ], 403);
        }

        $devices = $zone
            ->devices()
            ->withCount([
                'sensors',
            ])
            ->withMax(['readings as last_manual_reading_at' => fn ($q) => $q->where('source', 'simulation')->whereNull('iot_reading_batch_id')], 'recorded_at')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $devices,
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

        if (! ($user instanceof User)) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $user->loadMissing('company');

        $user
            ->company
            ?->syncZonePlanRestrictions();

        $zone->refresh();

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
                'message' => 'No tienes permiso para modificar esta zona.',
            ], 403);
        }

        if ($zone->greenhouse->plan_restricted) {
            return response()->json([
                'message' => 'Este invernadero está restringido por el plan actual. '
                    .'Renueva Pro para administrar sus dispositivos.',
            ], 403);
        }

        if ($zone->plan_restricted) {
            return response()->json([
                'message' => 'Esta zona está restringida por el plan actual. '
                    .'Renueva Pro para agregar dispositivos.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | No registrar dispositivos en zonas inactivas
        |--------------------------------------------------------------------------
        */

        if ($zone->status !== 'active' || $zone->greenhouse->status !== 'active') {
            return response()->json([
                'message' => 'No puedes agregar dispositivos a una zona inactiva.',
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

            'board_model' => ['sometimes', 'in:esp32_devkit_v1'],
            'measurement_interval' => ['sometimes', 'integer', 'between:10,3600'],

            'device_type' => [
                'required',
                'in:ESP32',
            ],

            'connection_type' => [
                'required',
                'in:wifi',
            ],
        ], [
            'name.required' => 'El nombre del dispositivo es obligatorio.',

            'device_code.required' => 'El código del dispositivo es obligatorio.',

            'device_code.unique' => 'Ya existe un dispositivo registrado con ese código.',

            'device_type.required' => 'El tipo de dispositivo es obligatorio.',

            'connection_type.required' => 'El tipo de conexión es obligatorio.',

            'connection_type.in' => 'El tipo de conexión debe ser WiFi, Ethernet, LoRa o Simulación.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Crear dispositivo
        |--------------------------------------------------------------------------
        */

        $device = $zone
            ->devices()
            ->create([
                'name' => trim(
                    $validated['name']
                ),

                'device_code' => 'LT-'.strtoupper(bin2hex(random_bytes(16))),
                'board_model' => $validated['board_model'] ?? 'esp32_devkit_v1',
                'measurement_interval' => $validated['measurement_interval'] ?? 60,

                'device_type' => trim(
                    $validated['device_type']
                ),

                'connection_type' => $validated['connection_type'],

                'status' => 'active',

                'last_connection_at' => null,
            ]);

        return response()->json([
            'message' => 'Dispositivo registrado correctamente.',

            'data' => $device,
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
                'message' => 'No tienes permiso para modificar este dispositivo.',
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

            'board_model' => ['sometimes', 'in:esp32_devkit_v1'],
            'measurement_interval' => ['sometimes', 'integer', 'between:10,3600'],

            'device_type' => [
                'required',
                'in:ESP32',
            ],

            'connection_type' => [
                'required',
                'in:wifi',
            ],

        ], [
            'name.required' => 'El nombre del dispositivo es obligatorio.',

            'device_code.required' => 'El código del dispositivo es obligatorio.',

            'device_code.unique' => 'Ya existe otro dispositivo con ese código.',

            'device_type.required' => 'El tipo de dispositivo es obligatorio.',

            'connection_type.required' => 'El tipo de conexión es obligatorio.',

            'connection_type.in' => 'El tipo de conexión debe ser WiFi, Ethernet, LoRa o Simulación.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Actualizar
        |--------------------------------------------------------------------------
        */

        $device->update([
            'name' => trim(
                $validated['name']
            ),

            'board_model' => $validated['board_model'] ?? $device->board_model,
            'measurement_interval' => $validated['measurement_interval'] ?? $device->measurement_interval,

            'device_type' => trim(
                $validated['device_type']
            ),

            'connection_type' => $validated['connection_type'],

        ]);

        return response()->json([
            'message' => 'Dispositivo actualizado correctamente.',

            'data' => $device->fresh(),
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

        if (! ($user instanceof User)) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $user->loadMissing('company');

        $user
            ->company
            ?->syncZonePlanRestrictions();

        $device->refresh();

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
                'message' => 'No tienes permiso para modificar este dispositivo.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'in:active,inactive',
            ],
        ]);

        if (
            $validated['status'] === 'active'
            &&
            $device->zone->greenhouse->plan_restricted
        ) {
            return response()->json([
                'message' => 'Este invernadero está restringido por el plan actual. '
                    .'Renueva Pro para activar sus dispositivos.',
            ], 403);
        }

        if (
            $validated['status'] === 'active'
            &&
            $device->zone->plan_restricted
        ) {
            return response()->json([
                'message' => 'Esta zona está restringida por el plan actual. '
                    .'Renueva Pro para activar sus dispositivos.',
            ], 403);
        }

        $device->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => $device->status === 'active'
                ? 'Dispositivo activado correctamente.'
                : 'Dispositivo desactivado correctamente.',

            'data' => $device,
        ]);
    }
}
