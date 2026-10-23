<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\VerificationMail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class EmailVerificationController extends Controller
{
    public static function result(Request $request, string $state, int $status = 200): Response
    {
        $message = match ($state) {
            'verified' => 'Correo electrónico verificado correctamente. Ya puedes iniciar sesión.',
            'already_verified' => 'El correo electrónico ya estaba verificado. Puedes iniciar sesión.',
            'expired' => 'El enlace de verificación ha vencido. Solicita uno nuevo.',
            default => 'El enlace de verificación no es válido o fue alterado. Solicita uno nuevo.',
        };
        $response = $request->expectsJson()
            ? response()->json(['message' => $message, 'status' => $state], $status)
            : response()->view('auth.verification-result', compact('message', 'state'), $status);

        return $response->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function verify(Request $request, string $id, string $hash): Response
    {
        // The route's signed middleware must run before any identity lookup.
        $state = DB::transaction(function () use ($id, $hash) {
            $user = User::whereKey($id)->lockForUpdate()->first();
            if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
                return 'invalid';
            }
            if ($user->hasVerifiedEmail()) {
                return 'already_verified';
            }
            $user->markEmailAsVerified();
            event(new Verified($user));

            return 'verified';
        });

        return self::result($request, $state, $state === 'invalid' ? 403 : 200);
    }

    public function resend(Request $request, VerificationMail $mail): Response
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'max:1024'],
        ]);
        $email = mb_strtolower(trim($data['email']));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        // Always perform a password check, including for unknown addresses.
        $valid = Hash::check($data['password'], $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if ($valid && $user && ! $user->hasVerifiedEmail() && $user->status === 'active'
            && ($user->role?->name === 'superadmin' || $user->company?->status === 'active')) {
            if ($mail->send($user) === 'failed') {
                // Only a caller proving the password learns that transport failed.
                return response()->json([
                    'status' => 'delivery_failed',
                    'message' => 'No se pudo solicitar el envío del correo. Intenta más tarde; no necesitas registrar otra cuenta.',
                ], 503)->header('Cache-Control', 'no-store');
            }
        }

        return response()->json([
            'status' => 'verification_requested',
            'message' => 'Si los datos corresponden a una cuenta habilitada pendiente de verificación y no se alcanzó el límite de envíos, se solicitará un nuevo correo. Revisa también spam. Esto no confirma su entrega.',
        ], 202)->header('Cache-Control', 'no-store');
    }
}
