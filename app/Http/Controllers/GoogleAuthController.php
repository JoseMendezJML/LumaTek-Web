<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GoogleAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;
use Tymon\JWTAuth\Facades\JWTAuth;

class GoogleAuthController extends Controller
{
    /**
     * Clave de sesión donde se guardan los datos de Google
     * mientras el usuario nuevo completa su registro.
     */
    private const PENDING_KEY = 'google_pending_user';

    public function __construct(
        private readonly GoogleAccountService $accounts
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Enviar al usuario a Google
    |--------------------------------------------------------------------------
    */

    public function redirect(
        Request $request
    ): SymfonyRedirectResponse {

        /*
         * Google regresa siempre a APP_URL. Si el usuario entró
         * por otra dirección (ej. 127.0.0.1 en vez de localhost),
         * la cookie de sesión no viajaría de vuelta y se perdería
         * la validación "state". Por eso empezamos desde APP_URL.
         */
        $appUrl =
            rtrim((string) config('app.url'), '/');

        if (
            strcasecmp(
                $request->getSchemeAndHttpHost(),
                $appUrl
            ) !== 0
        ) {
            return redirect()->to(
                $appUrl . '/auth/google/redirect'
            );
        }

        return Socialite::driver('google')
            ->redirect();
    }

    /*
    |--------------------------------------------------------------------------
    | Respuesta de Google
    |--------------------------------------------------------------------------
    */

    public function callback(
        Request $request
    ): View|RedirectResponse {

        try {

            $googleUser =
                Socialite::driver('google')
                    ->user();

        } catch (Throwable $error) {

            Log::warning(
                'Error en el inicio de sesión con Google.',
                ['type' => get_class($error), 'error' => $error->getMessage()]
            );

            return $this->backToLogin(
                'No fue posible iniciar sesión con Google. Intenta nuevamente.'
            );
        }


        $googleId =
            (string) $googleUser->getId();

        $email =
            strtolower(
                trim((string) $googleUser->getEmail())
            );

        if ($googleId === '' || $email === '') {

            return $this->backToLogin(
                'Tu cuenta de Google no compartió un correo electrónico.'
            );
        }

        /*
         * Solo confiamos en correos que Google marcó como verificados,
         * porque con ellos se vinculan cuentas existentes.
         */
        $emailVerified =
            $googleUser->user['email_verified'] ?? null;

        if ($emailVerified !== true && $emailVerified !== 'true') {

            return $this->backToLogin(
                'Tu correo de Google no está verificado.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Usuario existente: buscar por cuenta de Google o por correo
        |--------------------------------------------------------------------------
        */

        $user =
            $this->accounts->findUser($googleId, $email);

        if ($user) {

            $blocked =
                $this->accounts->loginBlockReason($user, $googleId);

            if ($blocked) {
                return $this->backToLogin($blocked[0]);
            }

            return $this->finishLogin(
                $request,
                $this->accounts->link($user, $googleId)
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Usuario nuevo: falta el nombre de la empresa
        |--------------------------------------------------------------------------
        */

        $request->session()->put(
            self::PENDING_KEY,
            [
                'google_id' => $googleId,
                'name' => $googleUser->getName() ?: $email,
                'email' => $email,
            ]
        );

        return redirect()->route('google.complete');
    }

    /*
    |--------------------------------------------------------------------------
    | Formulario para completar el registro
    |--------------------------------------------------------------------------
    */

    public function showComplete(
        Request $request
    ): View|RedirectResponse {

        $pending =
            $request->session()->get(self::PENDING_KEY);

        if (!$pending) {
            return redirect()->route('login');
        }

        return view('auth.google-complete', [
            'name' => $pending['name'],
            'email' => $pending['email'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Crear empresa y administrador con la cuenta de Google
    |--------------------------------------------------------------------------
    */

    public function complete(
        Request $request
    ): View|RedirectResponse {

        $pending =
            $request->session()->get(self::PENDING_KEY);

        if (!$pending) {
            return redirect()->route('login');
        }

        $request->validate(
            [
                'company_name' => [
                    'required',
                    'string',
                    'max:150',
                ],
            ],
            [
                'company_name.required' =>
                    'El nombre de la empresa es obligatorio.',
            ]
        );

        if ($this->accounts->findUser($pending['google_id'], $pending['email'])) {

            $request->session()->forget(self::PENDING_KEY);

            return $this->backToLogin(
                'Este correo electrónico ya está registrado. Inicia sesión con Google nuevamente.'
            );
        }

        $user =
            $this->accounts->registerCompanyOwner(
                $pending['google_id'],
                $pending['name'],
                $pending['email'],
                $request->company_name
            );

        $request->session()->forget(self::PENDING_KEY);

        return $this->finishLogin(
            $request,
            $user
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Sesión web + JWT (igual que webLogin)
    |--------------------------------------------------------------------------
    */

    private function finishLogin(
        Request $request,
        User $user
    ): View {

        Auth::guard('web')->login($user);

        $request
            ->session()
            ->regenerate();

        $token =
            JWTAuth::fromUser($user);

        $user->loadMissing('role');

        /*
         * El panel usa el JWT guardado en sessionStorage,
         * así que esta vista lo guarda y redirige según el rol.
         */
        return view('auth.google-callback', [
            'accessToken' => $token,
            'redirectTo' => $this->accounts->homePath($user),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company_id' => $user->company_id,
                'role' => $user->role?->name,
            ],
        ]);
    }

    private function backToLogin(
        string $message
    ): RedirectResponse {

        return redirect()
            ->route('login')
            ->with('google_error', $message);
    }
}
