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
                    'is_company_owner' =>
    (bool) $user->is_company_owner,

'can_manage' =>
    $user->id !== $authenticatedUser->id
    &&
    in_array(
        $user->role?->name,
        ['company_admin', 'employee'],
        true
    )
    &&
    (
        !$user->is_company_owner
        ||
        $authenticatedUser->is_company_owner
    ),
                    'created_at' =>
                        $user->created_at?->toDateTimeString(),
                ];
            });

        return response()->json([
            'data' => $users,
        ]);
    }

    /**
 * Registra un usuario para la empresa
 * del administrador autenticado.
 */
public function store(Request $request): JsonResponse
{
    $authenticatedUser = auth('api')->user();

    if (!$this->isCompanyAdmin($authenticatedUser)) {
        return response()->json([
            'message' =>
                'No tienes permiso para crear usuarios.',
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

        'role' => [
            'required',
            Rule::in([
                'company_admin',
                'employee',
            ]),
        ],
    ]);

    /*
    |--------------------------------------------------------------------------
    | Rol seleccionado
    |--------------------------------------------------------------------------
    */

    $role = Role::query()
        ->where(
            'name',
            $validated['role']
        )
        ->first();

    if (!$role) {
        return response()->json([
            'message' =>
                'El rol seleccionado no está configurado.',
        ], 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Crear usuario
    |--------------------------------------------------------------------------
    */

    $newUser = DB::transaction(
        function () use (
            $validated,
            $authenticatedUser,
            $role
        ) {
            return User::create([
                'company_id' =>
                    $authenticatedUser->company_id,

                'role_id' =>
                    $role->id,

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
    */

    $newUser->sendEmailVerificationNotification();

    $newUser->load('role');

    $roleLabel =
        $this->roleLabel(
            $newUser->role?->name
        );

    return response()->json([
        'message' =>
            $roleLabel
            . ' registrado correctamente.',

        'data' => [
            'id' =>
                $newUser->id,

            'name' =>
                $newUser->name,

            'email' =>
                $newUser->email,

            'status' =>
                $newUser->status,

            'role' => [
                'id' =>
                    $newUser->role?->id,

                'name' =>
                    $newUser->role?->name,

                'label' =>
                    $roleLabel,
            ],
        ],
    ], 201);
}

    /**
 * Actualiza los datos y el rol de un usuario
 * perteneciente a la misma empresa.
 */
public function update(
    Request $request,
    User $user
): JsonResponse {

    $authenticatedUser =
        auth('api')->user();


    /*
    |--------------------------------------------------------------------------
    | Permiso
    |--------------------------------------------------------------------------
    */

    if (
        !$this->isCompanyAdmin(
            $authenticatedUser
        )
    ) {

        return response()->json([
            'message' =>
                'No tienes permiso para modificar usuarios.',
        ], 403);
    }


    /*
    |--------------------------------------------------------------------------
    | Seguridad multiempresa
    |--------------------------------------------------------------------------
    */

    if (
        $user->company_id
        !==
        $authenticatedUser->company_id
    ) {

        return response()->json([
            'message' =>
                'No tienes permiso para modificar este usuario.',
        ], 403);
    }


    $user->load('role');


    /*
    |--------------------------------------------------------------------------
    | No modificar la propia cuenta desde este módulo
    |--------------------------------------------------------------------------
    */

    if (
        $user->id
        ===
        $authenticatedUser->id
    ) {

        return response()->json([
            'message' =>
                'Tu propia cuenta no puede modificarse desde este módulo.',
        ], 422);
    }

    if (
    $user->is_company_owner
    &&
    !$authenticatedUser->is_company_owner
) {
    return response()->json([
        'message' =>
            'El administrador principal no puede ser modificado por otro administrador.',
    ], 403);
}


    /*
    |--------------------------------------------------------------------------
    | Solo roles administrables
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $user->role?->name,
            [
                'company_admin',
                'employee',
            ],
            true
        )
    ) {

        return response()->json([
            'message' =>
                'Este usuario no puede administrarse desde este módulo.',
        ], 422);
    }


    /*
    |--------------------------------------------------------------------------
    | Validación
    |--------------------------------------------------------------------------
    */

    $validated =
        $request->validate([

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
                )->ignore(
                    $user->id
                ),
            ],

            'role' => [
                'required',

                Rule::in([
                    'company_admin',
                    'employee',
                ]),
            ],
        ]);


    /*
    |--------------------------------------------------------------------------
    | Obtener nuevo rol
    |--------------------------------------------------------------------------
    */

    $newRole =
        Role::query()
            ->where(
                'name',
                $validated['role']
            )
            ->first();


    if (!$newRole) {

        return response()->json([
            'message' =>
                'El rol seleccionado no está configurado.',
        ], 422);
    }


    /*
    |--------------------------------------------------------------------------
    | Protección del último administrador
    |--------------------------------------------------------------------------
    */

    if (
        $user->role?->name === 'company_admin'
        &&
        $validated['role'] === 'employee'
    ) {

        $otherActiveAdmins =
            User::query()
                ->where(
                    'company_id',
                    $authenticatedUser->company_id
                )
                ->where(
                    'status',
                    'active'
                )
                ->where(
                    'id',
                    '!=',
                    $user->id
                )
                ->whereHas(
                    'role',
                    function ($query) {

                        $query->where(
                            'name',
                            'company_admin'
                        );
                    }
                )
                ->count();


        if ($otherActiveAdmins < 1) {

            return response()->json([
                'message' =>
                    'La empresa debe conservar al menos un administrador activo.',
            ], 422);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Detectar cambio de correo
    |--------------------------------------------------------------------------
    */

    $emailChanged =
        strtolower(
            trim(
                $validated['email']
            )
        )
        !==
        strtolower(
            $user->email
        );


    /*
    |--------------------------------------------------------------------------
    | Actualizar usuario
    |--------------------------------------------------------------------------
    */

    $user->update([

        'name' =>
            $validated['name'],

        'email' =>
            strtolower(
                trim(
                    $validated['email']
                )
            ),

        'role_id' =>
            $newRole->id,

        'email_verified_at' =>
            $emailChanged
                ? null
                : $user->email_verified_at,
    ]);


    /*
    |--------------------------------------------------------------------------
    | Verificación de correo
    |--------------------------------------------------------------------------
    */

    if ($emailChanged) {

        $user
            ->sendEmailVerificationNotification();
    }


    $user->load('role');


    return response()->json([

        'message' =>
            'Usuario actualizado correctamente.',

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

            'role' => [

                'id' =>
                    $user->role?->id,

                'name' =>
                    $user->role?->name,

                'label' =>
                    $this->roleLabel(
                        $user->role?->name
                    ),
            ],
        ],
    ]);
}

    /**
 * Activa o desactiva un usuario de la empresa.
 */
public function changeStatus(
    Request $request,
    User $user
): JsonResponse {

    $authenticatedUser =
        auth('api')->user();


    /*
    |--------------------------------------------------------------------------
    | Permiso
    |--------------------------------------------------------------------------
    */

    if (
        !$this->isCompanyAdmin(
            $authenticatedUser
        )
    ) {

        return response()->json([
            'message' =>
                'No tienes permiso para cambiar el estado de usuarios.',
        ], 403);
    }


    /*
    |--------------------------------------------------------------------------
    | Seguridad multiempresa
    |--------------------------------------------------------------------------
    */

    if (
        $user->company_id
        !==
        $authenticatedUser->company_id
    ) {

        return response()->json([
            'message' =>
                'No tienes permiso para modificar este usuario.',
        ], 403);
    }


    $user->load('role');


    /*
    |--------------------------------------------------------------------------
    | No permitir modificar la propia cuenta
    |--------------------------------------------------------------------------
    */

    if (
        $user->id
        ===
        $authenticatedUser->id
    ) {

        return response()->json([
            'message' =>
                'No puedes cambiar el estado de tu propia cuenta desde este módulo.',
        ], 422);
    }

    if (
    $user->is_company_owner
    &&
    !$authenticatedUser->is_company_owner
) {
    return response()->json([
        'message' =>
            'El administrador principal no puede ser desactivado por otro administrador.',
    ], 403);
}


    /*
    |--------------------------------------------------------------------------
    | Solo roles administrables
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $user->role?->name,
            [
                'company_admin',
                'employee',
            ],
            true
        )
    ) {

        return response()->json([
            'message' =>
                'Este usuario no puede administrarse desde este módulo.',
        ], 422);
    }


    /*
    |--------------------------------------------------------------------------
    | Validación
    |--------------------------------------------------------------------------
    */

    $validated =
        $request->validate([

            'status' => [
                'required',

                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],
        ]);


    /*
    |--------------------------------------------------------------------------
    | Protección de administradores
    |--------------------------------------------------------------------------
    */

    if (
        $user->role?->name
        === 'company_admin'
        &&
        $validated['status']
        === 'inactive'
    ) {

        $otherActiveAdmins =
            User::query()
                ->where(
                    'company_id',
                    $authenticatedUser->company_id
                )
                ->where(
                    'status',
                    'active'
                )
                ->where(
                    'id',
                    '!=',
                    $user->id
                )
                ->whereHas(
                    'role',
                    function ($query) {

                        $query->where(
                            'name',
                            'company_admin'
                        );
                    }
                )
                ->count();


        if ($otherActiveAdmins < 1) {

            return response()->json([
                'message' =>
                    'La empresa debe conservar al menos un administrador activo.',
            ], 422);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Actualizar estado
    |--------------------------------------------------------------------------
    */

    $user->update([
        'status' =>
            $validated['status'],
    ]);


    $roleLabel =
        $this->roleLabel(
            $user->role?->name
        );


    return response()->json([

        'message' =>
            $validated['status'] === 'active'
                ? $roleLabel . ' activado correctamente.'
                : $roleLabel . ' desactivado correctamente.',

        'data' => [

            'id' =>
                $user->id,

            'status' =>
                $user->status,

            'role' => [

                'name' =>
                    $user->role?->name,

                'label' =>
                    $roleLabel,
            ],
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
