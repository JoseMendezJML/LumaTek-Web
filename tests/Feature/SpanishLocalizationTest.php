<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class SpanishLocalizationTest extends TestCase
{
    use UsesInMemoryDatabase;

    public function test_all_framework_validation_rules_have_spanish_translations(): void
    {
        $this->assertSame('es', app()->getLocale());
        $this->assertSame('es', config('app.fallback_locale'));
        $english = Arr::dot(require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php'));
        $spanish = Arr::dot(require lang_path('es/validation.php'));
        foreach ($english as $key => $message) {
            if (str_starts_with($key, 'custom.') || $key === 'attributes') {
                continue;
            }
            $this->assertArrayHasKey($key, $spanish);
            $this->assertNotSame($message, $spanish[$key], $key);
            preg_match_all('/:[a-z_]+/', $message, $expected);
            preg_match_all('/:[a-z_]+/', $spanish[$key], $actual);
            $this->assertEqualsCanonicalizing(array_unique($expected[0]), array_unique($actual[0]), $key);
        }
    }

    public static function passwords(): array
    {
        return [
            'length' => ['Aa1!', 'La contraseña debe tener al menos 8 caracteres.'],
            'mixed case' => ['abcdef1!', 'La contraseña debe contener al menos una letra mayúscula y una minúscula.'],
            'number' => ['Abcdefgh!', 'La contraseña debe contener al menos un número.'],
            'symbol' => ['Abcdefg1', 'La contraseña debe contener al menos un símbolo.'],
        ];
    }

    #[DataProvider('passwords')]
    public function test_password_rules_are_translated_in_registration_and_reset(string $password, string $message): void
    {
        foreach (['/api/register', '/api/password/reset'] as $uri) {
            $response = $this->postJson($uri, [
                'company_name' => 'Empresa', 'name' => 'Ana', 'email' => 'ana@example.com',
                'token' => 'test-token', 'password' => $password, 'password_confirmation' => $password,
            ])->assertUnprocessable()->assertJsonValidationErrors('password');
            $this->assertContains($message, $response->json('errors.password'));
            $this->assertStringNotContainsString('The password', $response->json('message'));
            $this->assertStringNotContainsString('more error', $response->json('message'));
        }
    }

    public function test_common_validation_messages_and_attribute_names_are_spanish(): void
    {
        $validator = Validator::make([
            'email' => 'invalid', 'name' => str_repeat('x', 121), 'status' => 'unknown',
            'password' => 'Correcta-123!', 'password_confirmation' => 'Diferente-123!',
            'starts_at' => 'invalid', 'duration_minutes' => -1,
        ], [
            'email' => 'email', 'name' => 'max:120', 'status' => 'in:active,inactive',
            'company_name' => 'required', 'password' => 'confirmed', 'starts_at' => 'date',
            'duration_minutes' => 'integer|min:1',
        ]);
        $errors = $validator->errors();
        $this->assertSame('El correo electrónico ingresado no es válido.', $errors->first('email'));
        $this->assertSame('El campo nombre debe tener como máximo 120 caracteres.', $errors->first('name'));
        $this->assertSame('El valor seleccionado para estado no es válido.', $errors->first('status'));
        $this->assertSame('El campo nombre de la empresa es obligatorio.', $errors->first('company_name'));
        $this->assertSame('Las contraseñas no coinciden.', $errors->first('password'));
        $this->assertSame('El campo fecha de inicio debe ser una fecha válida.', $errors->first('starts_at'));
        $this->assertSame('El campo duración en minutos debe ser mayor o igual que 1.', $errors->first('duration_minutes'));

        $this->postJson('/api/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->postJson('/api/auth/google', [])->assertUnprocessable()
            ->assertJsonPath('errors.id_token.0', 'El campo credencial de Google es obligatorio.');
    }

    public function test_duplicate_email_is_translated_without_changing_validation_keys(): void
    {
        $user = $this->user();
        $this->postJson('/api/register', [
            'company_name' => 'Empresa', 'name' => 'Ana', 'email' => $user->email,
            'password' => 'Correcta-123!', 'password_confirmation' => 'Diferente-123!',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Este correo electrónico ya está registrado.')
            ->assertJsonPath('errors.password.0', 'Las contraseñas no coinciden.');
    }

    public function test_web_validation_session_errors_are_spanish(): void
    {
        $this->withSession(['google_pending_user' => [
            'google_id' => 'pending', 'name' => 'Ana', 'email' => 'ana@example.com',
        ]])->from('/auth/google/complete')->post('/auth/google/complete', [
            'company_name' => str_repeat('x', 151),
        ])->assertRedirect('/auth/google/complete')
            ->assertSessionHasErrors(['company_name' => 'El campo nombre de la empresa debe tener como máximo 150 caracteres.']);
    }

    public function test_framework_json_errors_are_translated_and_preserve_status_and_headers(): void
    {
        config(['app.debug' => false]);
        $this->getJson('/api/greenhouses')->assertUnauthorized()
            ->assertExactJson(['message' => 'Debes iniciar sesión para continuar.']);
        Route::get('/_test/not-found', fn () => User::findOrFail(999999));
        $this->getJson('/_test/not-found')->assertNotFound()->assertExactJson(['message' => 'Página no encontrada']);
        $this->getJson('/api/nonexistent-route')->assertNotFound()
            ->assertExactJson(['message' => 'Página no encontrada']);
        $this->getJson('/api/login')->assertStatus(405)->assertHeader('Allow', 'POST')
            ->assertExactJson(['message' => 'Método no permitido']);
        $this->get('/api/login')->assertStatus(405)->assertSee('El método utilizado no está permitido.');
        Route::get('/_test/forbidden', fn () => abort(403));
        $this->getJson('/_test/forbidden')->assertForbidden()->assertExactJson(['message' => 'No tienes permiso para realizar esta acción.']);
        Route::get('/_test/csrf', fn () => throw new TokenMismatchException('CSRF token mismatch.'));
        $this->getJson('/_test/csrf')->assertStatus(419)
            ->assertExactJson(['message' => 'La sesión ha vencido. Recarga la página e intenta nuevamente.']);
        Route::get('/_test/throttle', fn () => throw new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => 60]));
        $this->getJson('/_test/throttle')->assertStatus(429)->assertHeader('Retry-After', '60')
            ->assertExactJson(['message' => 'Demasiados intentos. Espera un momento e intenta nuevamente.']);
        Route::get('/_test/error', fn () => throw new \RuntimeException('Internal technical details'));
        $this->getJson('/_test/error')->assertStatus(500)
            ->assertExactJson(['message' => 'Ocurrió un error en el servidor']);
        $this->get('/_test/error')->assertStatus(500)->assertSee('Ocurrió un error en el servidor');
    }

    public function test_verification_and_reset_emails_are_spanish_without_sending_mail(): void
    {
        $user = $this->user();
        $verification = (new VerifyEmail)->toMail($user);
        $this->assertSame('Verifica tu correo electrónico', $verification->subject);
        $this->assertSame('Verificar correo electrónico', $verification->actionText);
        $this->assertSame(['Haz clic en el botón para verificar tu correo electrónico.'], $verification->introLines);
        $reset = (new ResetPassword('test-token'))->toMail($user);
        $this->assertSame('Restablece tu contraseña', $reset->subject);
        $this->assertSame('Restablecer contraseña', $reset->actionText);
        foreach ([$verification, $reset] as $mail) {
            $html = (string) $mail->render();
            $this->assertStringContainsString('¡Hola!', $html);
            $this->assertStringContainsString('Saludos,', $html);
            $this->assertStringContainsString('Todos los derechos reservados.', $html);
            $this->assertStringNotContainsString('If you', $html);
            $this->assertStringNotContainsString('Hello!', $html);
        }
        $this->assertSame('La contraseña se restableció correctamente.', __('passwords.reset'));
        $this->assertSame('El correo electrónico o la contraseña son incorrectos.', __('auth.failed'));
    }

    private function user(): User
    {
        $company = Company::create(['name' => 'Empresa', 'email' => 'empresa@example.com', 'status' => 'active']);

        return User::factory()->create([
            'company_id' => $company->id, 'role_id' => Role::where('name', 'company_admin')->firstOrFail()->id,
            'is_company_owner' => true, 'status' => 'active',
        ]);
    }
}
