<?php

namespace App\Http\Controllers;

use App\Models\Greenhouse;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    /**
     * Lista las zonas de un invernadero.
     */
    public function index(
        Greenhouse $greenhouse
    ): JsonResponse {

        $user = auth('api')->user();

        if ($greenhouse->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'No tienes permiso para consultar este invernadero.',
            ], 403);
        }

        $zones = $greenhouse
            ->zones()
            ->withCount([
                'devices',
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $zones,
        ]);
    }


    /**
     * Crea una zona dentro de un invernadero.
     */
    public function store(
        Request $request,
        Greenhouse $greenhouse
    ): JsonResponse {

        $user = auth('api')->user();

        if ($greenhouse->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'No tienes permiso para modificar este invernadero.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
            ],

            'description' => [
                'nullable',
                'string',
                'max:500',
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
                'El nombre de la zona es obligatorio.',

            'name.max' =>
                'El nombre de la zona no puede superar 120 caracteres.',

            'description.max' =>
                'La descripción no puede superar 500 caracteres.',

            'position_x.between' =>
                'La posición X debe estar entre 0 y 100.',

            'position_y.between' =>
                'La posición Y debe estar entre 0 y 100.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Evitar zonas duplicadas dentro del mismo invernadero
        |--------------------------------------------------------------------------
        */

        $exists = $greenhouse
            ->zones()
            ->whereRaw(
                'LOWER(name) = ?',
                [
                    strtolower(
                        trim($validated['name'])
                    ),
                ]
            )
            ->exists();

        if ($exists) {
            return response()->json([
                'message' =>
                    'Ya existe una zona con ese nombre en este invernadero.',
            ], 422);
        }

        $zone = $greenhouse
            ->zones()
            ->create([
                'name' =>
                    trim($validated['name']),

                'description' =>
                    $validated['description']
                    ?? null,

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
                'Zona creada correctamente.',

            'data' =>
                $zone,
        ], 201);
    }


    /**
     * Actualiza una zona.
     */
    public function update(
        Request $request,
        Zone $zone
    ): JsonResponse {

        $user = auth('api')->user();

        $zone->load(
            'greenhouse'
        );

        if ($zone->greenhouse->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'No tienes permiso para modificar esta zona.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
            ],

            'description' => [
                'nullable',
                'string',
                'max:500',
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
        ]);

        $exists = Zone::query()
            ->where(
                'greenhouse_id',
                $zone->greenhouse_id
            )
            ->where(
                'id',
                '!=',
                $zone->id
            )
            ->whereRaw(
                'LOWER(name) = ?',
                [
                    strtolower(
                        trim($validated['name'])
                    ),
                ]
            )
            ->exists();

        if ($exists) {
            return response()->json([
                'message' =>
                    'Ya existe otra zona con ese nombre en el invernadero.',
            ], 422);
        }

        $zone->update([
            'name' =>
                trim($validated['name']),

            'description' =>
                $validated['description']
                ?? null,

            'position_x' =>
                $validated['position_x']
                ?? null,

            'position_y' =>
                $validated['position_y']
                ?? null,

            'status' =>
                $validated['status']
                ?? $zone->status,
        ]);

        return response()->json([
            'message' =>
                'Zona actualizada correctamente.',

            'data' =>
                $zone->fresh(),
        ]);
    }


    /**
     * Activa o desactiva una zona.
     *
     * No eliminamos físicamente la zona para conservar
     * dispositivos, sensores y lecturas históricas.
     */
    public function changeStatus(
        Request $request,
        Zone $zone
    ): JsonResponse {

        $user = auth('api')->user();

        $zone->load(
            'greenhouse'
        );

        if ($zone->greenhouse->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'No tienes permiso para modificar esta zona.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'in:active,inactive',
            ],
        ]);

        $zone->update([
            'status' =>
                $validated['status'],
        ]);

        return response()->json([
            'message' =>
                $zone->status === 'active'
                    ? 'Zona activada correctamente.'
                    : 'Zona desactivada correctamente.',

            'data' =>
                $zone,
        ]);
    }
}