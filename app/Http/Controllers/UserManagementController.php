<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    /**
     * Lista los usuarios pertenecientes a la empresa
     * del administrador autenticado.
     */
    public function index(): JsonResponse
    {
        $authenticatedUser = auth('api')->user();

        if (!$this->isCompanyAdmin($authenticatedUser)) {
            return response()->json([
                'message' => 'No tienes permiso para administrar usuarios.',
            ], 403);
        }

        $users = User::query()
            ->where(
                'company_id',
                $authenticatedUser->company_id
            )
            ->with('role:id,name,description')
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($authenticatedUser) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status,
                    'email_verified' =>
                        $user->email_verified_at !== null,
                    'role' => [
                        'id' => $user->role?->id,
                        'name' => $user->role?->name,
                        'label' => $this->roleLabel(
                            $user->role?->name
                        ),
                    ],
                    'is_current_user' =>
                        $user->id === $authenticatedUser->id,
                    'can_manage' =>
                        $user->id !== $authenticatedUser->id
                        && $user->role?->name === 'employee',
                    'created_at' =>
                        $user->created_at?->toDateTimeString(),
                ];
            });

        return response()->json([
            'data' => $users,
        ]);
    }

    /**
     * Registra un empleado para la empresa
     * del administrador autenticado.
     */
    public function store(Request $request): JsonResponse
    {
        $authenticatedUser = auth('api')->user();

        if (!$this->isCompanyAdmin($authenticatedUser)) {
            return response()->json([
                'message' => 'No tienes permiso para crear usuarios.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'min:3',
                'max:120',
            ],
            'email' => [
                'required',
                'email',
                'max:150',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $employeeRole = Role::query()
            ->where('name', 'employee')
            ->first();

        if (!$employeeRole) {
            return response()->json([
                'message' =>
                    'El rol de empleado no está configurado.',
            ], 422);
        }

        $employee = DB::transaction(
            function () use (
                $validated,
                $authenticatedUser,
                $employeeRole
            ) {
                return User::create([
                    'company_id' =>
                        $authenticatedUser->company_id,

                    'role_id' =>
                        $employeeRole->id,

                    'name' =>
                        $validated['name'],

                    'email' =>
                        strtolower(
                            trim(
                                $validated['email']
                            )
                        ),

                    'password' =>
                        $validated['password'],

                    'status' =>
                        'active',
                ]);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Verificación de correo
        |--------------------------------------------------------------------------
        |
        | El empleado queda creado, pero deberá verificar su correo
        | antes de poder iniciar sesión.
        |
        | La contraseña nunca se envía por correo.
        |
        */

        $employee->sendEmailVerificationNotification();

        $employee->load('role');

        return response()->json([
            'message' =>
                'Empleado registrado correctamente.',

            'data' => [
                'id' =>
                    $employee->id,

                'name' =>
                    $employee->name,

                'email' =>
                    $employee->email,

                'status' =>
                    $employee->status,

                'role' =>
                    $employee->role?->name,
            ],
        ], 201);
    }

    /**
     * Actualiza los datos de un empleado.
     */
    public function update(
        Request $request,
        User $user
    ): JsonResponse {
        $authenticatedUser = auth('api')->user();

        if (!$this->isCompanyAdmin($authenticatedUser)) {
            return response()->json([
                'message' =>
                    'No tienes permiso para modificar usuarios.',
            ], 403);
        }

        if (
            $user->company_id !==
            $authenticatedUser->company_id
        ) {
            return response()->json([
                'message' =>
                    'No tienes permiso para modificar este usuario.',
            ], 403);
        }

        $user->load('role');

        if (
            $user->id ===
            $authenticatedUser->id
        ) {
            return response()->json([
                'message' =>
                    'Tu cuenta de administrador no puede modificarse desde este módulo.',
            ], 422);
        }

        if ($user->role?->name !== 'employee') {
            return response()->json([
                'message' =>
                    'Desde este módulo solo pueden administrarse empleados.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'min:3',
                'max:120',
            ],
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique(
                    'users',
                    'email'
                )->ignore($user->id),
            ],
        ]);

        $emailChanged =
            strtolower(trim($validated['email']))
            !== strtolower($user->email);

        $user->update([
            'name' =>
                $validated['name'],

            'email' =>
                strtolower(
                    trim(
                        $validated['email']
                    )
                ),

            /*
            |--------------------------------------------------------------------------
            | Si cambia el correo
            |--------------------------------------------------------------------------
            |
            | El nuevo correo deberá verificarse nuevamente.
            |
            */

            'email_verified_at' =>
                $emailChanged
                    ? null
                    : $user->email_verified_at,
        ]);

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        $user->load('role');

        return response()->json([
            'message' =>
                'Empleado actualizado correctamente.',

            'data' => [
                'id' =>
                    $user->id,

                'name' =>
                    $user->name,

                'email' =>
                    $user->email,

                'status' =>
                    $user->status,

                'email_verified' =>
                    $user->email_verified_at !== null,

                'role' =>
                    $user->role?->name,
            ],
        ]);
    }

    /**
     * Activa o desactiva un empleado.
     */
    public function changeStatus(
        Request $request,
        User $user
    ): JsonResponse {
        $authenticatedUser = auth('api')->user();

        if (!$this->isCompanyAdmin($authenticatedUser)) {
            return response()->json([
                'message' =>
                    'No tienes permiso para cambiar el estado de usuarios.',
            ], 403);
        }

        if (
            $user->company_id !==
            $authenticatedUser->company_id
        ) {
            return response()->json([
                'message' =>
                    'No tienes permiso para modificar este usuario.',
            ], 403);
        }

        $user->load('role');

        if (
            $user->id ===
            $authenticatedUser->id
        ) {
            return response()->json([
                'message' =>
                    'No puedes desactivar tu propia cuenta desde este módulo.',
            ], 422);
        }

        if ($user->role?->name !== 'employee') {
            return response()->json([
                'message' =>
                    'Desde este módulo solo pueden administrarse empleados.',
            ], 422);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],
        ]);

        $user->update([
            'status' =>
                $validated['status'],
        ]);

        return response()->json([
            'message' =>
                $validated['status'] === 'active'
                    ? 'Empleado activado correctamente.'
                    : 'Empleado desactivado correctamente.',

            'data' => [
                'id' =>
                    $user->id,

                'status' =>
                    $user->status,
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

        $user->loadMissing('role');

        return
            $user->status === 'active'
            && $user->role?->name === 'company_admin';
    }

    /**
     * Etiqueta legible del rol.
     */
    private function roleLabel(
        ?string $role
    ): string {
        return match ($role) {
            'company_admin' =>
                'Administrador',

            'employee' =>
                'Empleado',

            'superadmin' =>
                'Superadministrador',

            default =>
                'Sin rol',
        };
    }
}
