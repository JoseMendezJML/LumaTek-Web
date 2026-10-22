<?php

namespace App\Http\Controllers;

use App\Services\GreenhouseSnapshotService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Devuelve la información principal del dashboard.
     */
    public function index(
        GreenhouseSnapshotService $snapshots
    ): JsonResponse {
        $user = auth('api')->user();


        if (!($user instanceof \App\Models\User)) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $user->loadMissing('company');

        $user->company?->syncSensorPlanRestrictions();

        $companyId = $user->company_id;

        return response()->json([
            'data' =>
            $snapshots->dashboard($companyId),
        ]);
    }
}
