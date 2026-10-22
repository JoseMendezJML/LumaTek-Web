<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Inicio de sesión con Google para la app móvil.
 *
 * La app obtiene un id_token con el inicio de sesión nativo
 * de Google y lo envía aquí. Laravel lo valida con Google
 * y responde con el mismo JWT que el login normal.
 */
class MobileGoogleAuthController extends Controller
{
    public function __construct(
        private readonly GoogleAccountService $accounts
    ) {}

    public function login(
        Request $request
    ): JsonResponse {

        $request->validate([
            'id_token' => [
                'required',
                'string',
            ],

            'company_name' => [
                'nullable',
                'string',
                'max:150',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Validar el id_token con Google
        |--------------------------------------------------------------------------
        */

        $payload =
            $this->verifyIdToken(
                $request->id_token
            );

        if (! $payload) {
            return response()->json([
                'message' => 'No fue posible validar tu cuenta de Google.',
            ], 401);
        }

        $googleId = (string) $payload['sub'];

        $email =
            strtolower(
                trim((string) $payload['email'])
            );

        $name =
            $payload['name'] ?? $email;

        /*
        |--------------------------------------------------------------------------
        | Usuario existente
        |--------------------------------------------------------------------------
        */

        $user =
            $this->accounts->findUser($googleId, $email);

        if ($user) {

            $blocked =
                $this->accounts->loginBlockReason($user, $googleId, $email);

            if ($blocked) {
                return response()->json([
                    'message' => $blocked[0],
                ], $blocked[1]);
            }

            try {
                $user = $this->accounts->link($user, $googleId, $email);
            } catch (ValidationException $error) {
                return response()->json(['message' => $error->errors()['email'][0]], 409);
            }

            return $this->tokenResponse($user);
        }

        /*
        |--------------------------------------------------------------------------
        | Usuario nuevo: la app debe pedir el nombre de la empresa
        |--------------------------------------------------------------------------
        */

        if (! $request->filled('company_name')) {
            return response()->json([
                'code' => 'company_required',
                'message' => 'Escribe el nombre de tu empresa para crear tu cuenta.',
                'name' => $name,
                'email' => $email,
            ], 422);
        }

        $user =
            $this->accounts->registerCompanyOwner(
                $googleId,
                $name,
                $email,
                $request->company_name
            );

        return $this->tokenResponse($user, 201);
    }

    /**
     * Devuelve los datos del token si es válido y fue emitido
     * para alguno de los clientes de LumaTek.
     */
    private function verifyIdToken(
        string $idToken
    ): ?array {

        try {

            $response = Http::timeout(10)->get(
                'https://oauth2.googleapis.com/tokeninfo',
                ['id_token' => $idToken]
            );

        } catch (Throwable $error) {

            Log::warning(
                'No se pudo contactar a Google para validar el id_token.',
                ['error' => $error->getMessage()]
            );

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        $allowedClients = array_filter(array_merge(
            [config('services.google.client_id')],
            config('services.google.mobile_client_ids', [])
        ));

        $isValid =
            in_array($payload['aud'] ?? null, $allowedClients, true)
            && in_array($payload['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            && ($payload['email_verified'] ?? null) === 'true'
            && ! empty($payload['sub'])
            && ! empty($payload['email']);

        return $isValid ? $payload : null;
    }

    /**
     * Misma respuesta que AuthController@login.
     */
    private function tokenResponse(
        User $user,
        int $status = 200
    ): JsonResponse {

        $user->loadMissing('role');

        return response()->json([
            'message' => 'Inicio de sesión correcto.',
            'access_token' => JWTAuth::fromUser($user),
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
            'redirect_to' => $this->accounts->homePath($user),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company_id' => $user->company_id,
                'role' => $user->role?->name,
            ],
        ], $status);
    }
}
