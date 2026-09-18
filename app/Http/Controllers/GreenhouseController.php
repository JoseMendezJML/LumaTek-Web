<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGreenhouseRequest;
use App\Models\Greenhouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GreenhouseController extends Controller
{
    /**
     * Umbrales iniciales provisionales.
     *
     * Estos valores podrán ajustarse posteriormente según cultivo
     * o indicaciones del Product Owner.
     */
    private const DEFAULT_THRESHOLDS = [
        [
            'variable' => 'temperature',
            'min_value' => 18,
            'max_value' => 30,
            'unit' => '°C',
        ],
        [
            'variable' => 'soil_humidity',
            'min_value' => 40,
            'max_value' => 80,
            'unit' => '%',
        ],
        [
            'variable' => 'ambient_humidity',
            'min_value' => 40,
            'max_value' => 85,
            'unit' => '%',
        ],
    ];

    /**
     * Lista los invernaderos de la empresa autenticada.
     */
    public function index(): JsonResponse
    {
        $user = auth('api')->user();

        $greenhouses = Greenhouse::query()
            ->where('company_id', $user->company_id)
            ->with('thresholds')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $greenhouses,
        ]);
    }

    /**
     * Guarda un nuevo invernadero.
     */
    public function store(
        StoreGreenhouseRequest $request
    ): JsonResponse {
        $user = auth('api')->user();

        $greenhouse = DB::transaction(function () use ($request, $user) {

            $greenhouse = Greenhouse::create([
                'company_id' => $user->company_id,
                'name' => $request->name,
                'crop_type' => $request->crop_type,
                'area' => $request->area,
                'location' => $request->location,
                'planting_date' => $request->planting_date,
                'nominal_flow' => $request->nominal_flow,
                'status' => 'active',
            ]);

            foreach (self::DEFAULT_THRESHOLDS as $threshold) {

                $greenhouse->thresholds()->create([
                    'variable' => $threshold['variable'],
                    'min_value' => $threshold['min_value'],
                    'max_value' => $threshold['max_value'],
                    'unit' => $threshold['unit'],
                    'is_default' => true,
                ]);
            }

            return $greenhouse;
        });

        $greenhouse->load('thresholds');

        return response()->json([
            'message' => 'Invernadero registrado correctamente.',
            'data' => $greenhouse,
        ], 201);
    }

    /**
     * Muestra un invernadero específico.
     */
    public function show(
        Greenhouse $greenhouse
    ): JsonResponse {
        $user = auth('api')->user();

        if ($greenhouse->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'No tienes permiso para acceder a este invernadero.',
            ], 403);
        }

        $greenhouse->load('thresholds');

        return response()->json([
            'data' => $greenhouse,
        ]);
    }

    /**
     * Actualiza el perfil de un invernadero.
     */
    public function update(
        StoreGreenhouseRequest $request,
        Greenhouse $greenhouse
    ): JsonResponse {
        $user = auth('api')->user();

        if ($greenhouse->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'No tienes permiso para modificar este invernadero.',
            ], 403);
        }

        $greenhouse->update([
            'name' => $request->name,
            'crop_type' => $request->crop_type,
            'area' => $request->area,
            'location' => $request->location,
            'planting_date' => $request->planting_date,
            'nominal_flow' => $request->nominal_flow,
        ]);

        $greenhouse->load('thresholds');

        return response()->json([
            'message' => 'Perfil del invernadero actualizado correctamente.',
            'data' => $greenhouse,
        ]);
    }
}