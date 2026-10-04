<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanySettingsController extends Controller
{
    /**
     * Muestra la información de la empresa
     * del administrador autenticado.
     */
    public function show(): JsonResponse
    {
        $user = auth('api')->user();

        if (!$this->isCompanyAdmin($user)) {
            return response()->json([
                'message' =>
                    'No tienes permiso para administrar la configuración de la empresa.',
            ], 403);
        }

        $company = Company::find(
            $user->company_id
        );

        if (!$company) {
            return response()->json([
                'message' =>
                    'No se encontró la empresa asociada al usuario.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' =>
                    $company->id,

                'name' =>
                    $company->name,

                'legal_name' =>
                    $company->legal_name,

                'email' =>
                    $company->email,

                'phone' =>
                    $company->phone,

                'status' =>
                    $company->status,

                'created_at' =>
                    $company->created_at
                        ?->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Actualiza la información general
     * de la empresa autenticada.
     */
    public function update(
        Request $request
    ): JsonResponse {
        $user = auth('api')->user();

        if (!$this->isCompanyAdmin($user)) {
            return response()->json([
                'message' =>
                    'No tienes permiso para modificar la configuración de la empresa.',
            ], 403);
        }

        $company = Company::find(
            $user->company_id
        );

        if (!$company) {
            return response()->json([
                'message' =>
                    'No se encontró la empresa asociada al usuario.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'min:3',
                'max:150',
            ],

            'legal_name' => [
                'nullable',
                'string',
                'max:200',
            ],

            'email' => [
                'required',
                'email',
                'max:150',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],
        ]);

        $company->update([
            'name' =>
                trim(
                    $validated['name']
                ),

            'legal_name' =>
                isset(
                    $validated['legal_name']
                )
                    ? trim(
                        $validated['legal_name']
                    )
                    : null,

            'email' =>
                strtolower(
                    trim(
                        $validated['email']
                    )
                ),

            'phone' =>
                isset(
                    $validated['phone']
                )
                    ? trim(
                        $validated['phone']
                    )
                    : null,
        ]);

        return response()->json([
            'message' =>
                'Configuración de la empresa actualizada correctamente.',

            'data' => [
                'id' =>
                    $company->id,

                'name' =>
                    $company->name,

                'legal_name' =>
                    $company->legal_name,

                'email' =>
                    $company->email,

                'phone' =>
                    $company->phone,

                'status' =>
                    $company->status,
            ],
        ]);
    }

    /**
     * Determina si el usuario autenticado
     * es administrador de empresa.
     */
    private function isCompanyAdmin(
        ?User $user
    ): bool {
        if (!$user) {
            return false;
        }

        $user->loadMissing(
            'role'
        );

        return
            $user->status === 'active'
            && $user->role?->name ===
                'company_admin';
    }
}
