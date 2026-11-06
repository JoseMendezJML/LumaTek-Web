<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

#[TestDox('Seguridad de autenticación JWT y sesiones')]
class AuthenticationVersionTest extends TestCase
{
    use UsesInMemoryDatabase;

    private const INACTIVE_MESSAGE = 'La cuenta se encuentra inactiva.';

    private const EXPIRED_MESSAGE = 'Las credenciales han caducado. Inicia sesión nuevamente.';

    #[DataProvider('roles')]
    #[TestDox('Restablecer la contraseña invalida los JWT anteriores y conserva los datos del usuario — con $_dataName')]
    public function test_password_reset_revokes_old_jwts_and_preserves_identity(string $role): void
    {
        [, $user] = $this->createManagedUser($role);
        $oldToken = $this->loginApi($user);
        $secondToken = $this->loginApi($user);
        $identity = $user->only(['company_id', 'role_id', 'is_company_owner', 'status', 'email_verified_at']);
        $rememberToken = $user->remember_token;

        $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'token' => Password::createToken($user),
            'password' => 'Nueva-Clave-123!',
            'password_confirmation' => 'Nueva-Clave-123!',
        ])->assertOk();

        $version = $user->fresh()->authenticationVersion();
        $this->assertTrue(Str::isUuid($version));
        $this->assertNotSame($rememberToken, $user->fresh()->remember_token);
        $this->assertEquals($identity, $user->fresh()->only(array_keys($identity)));

        foreach ([$oldToken, $secondToken] as $token) {
            $this->api($token, 'GET', '/api/greenhouses')
                ->assertUnauthorized()->assertJsonPath('message', self::EXPIRED_MESSAGE);
        }

        $this->resetGuards();
        $refreshed = JWTAuth::setToken($secondToken)->refresh();
        $this->api($refreshed, 'GET', '/api/greenhouses')->assertUnauthorized();
        $this->resetGuards();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertUnauthorized();
        $this->resetGuards();
        $newToken = $this->postJson('/api/login', [
            'email' => $user->email, 'password' => 'Nueva-Clave-123!',
        ])->assertOk()->json('access_token');
        $this->assertSame($version, JWTAuth::setToken($newToken)->getPayload()->get('auth_version'));
        $this->api($newToken, 'GET', '/api/greenhouses')->assertOk();
    }

    #[TestDox('Restablecer la contraseña invalida sesiones web y cookies de recordar sesión')]
    public function test_password_reset_revokes_an_unvisited_web_session_and_remember_cookie(): void
    {
        [, $user] = $this->createManagedUser('employee');
        $response = $this->loginWeb($user, true);
        $session = session()->all();
        $recallerName = Auth::guard('web')->getRecallerName();
        $recaller = $response->getCookie($recallerName);
        $this->assertNotNull($recaller);

        $this->postJson('/api/password/reset', [
            'email' => $user->email, 'token' => Password::createToken($user),
            'password' => 'Nueva-Clave-123!', 'password_confirmation' => 'Nueva-Clave-123!',
        ])->assertOk();
        $this->withSession($session);
        $this->resetGuards();
        $this->get('/dashboard')->assertRedirect(route('login'))
            ->assertSessionHas('error', self::EXPIRED_MESSAGE);
        $this->assertGuest('web');

        $this->clearBrowserSession();
        $this->withCookie($recallerName, $recaller->getValue());
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest('web');

        $this->resetGuards();
        $this->withCredentials()->postJson('/login', [
            'email' => $user->email, 'password' => 'Nueva-Clave-123!',
        ])->assertOk();
        $this->assertSame($user->fresh()->authenticationVersion(), session(User::AUTH_VERSION_SESSION_KEY.'.version'));
        $this->resetGuards();
        $this->get('/dashboard')->assertOk();
    }

    #[TestDox('Un restablecimiento fallido no invalida las credenciales')]
    public function test_unsuccessful_reset_does_not_revoke_credentials(): void
    {
        [, $user] = $this->createManagedUser('employee');
        $token = $this->loginApi($user);
        $before = $user->fresh()->only(['password', 'auth_version', 'remember_token']);
        $this->postJson('/api/password/reset', [
            'email' => $user->email, 'token' => 'invalid-token',
            'password' => 'Nueva-Clave-123!', 'password_confirmation' => 'Nueva-Clave-123!',
        ])->assertUnprocessable();
        $this->assertSame($before, $user->fresh()->only(array_keys($before)));
        $this->api($token, 'GET', '/api/greenhouses')->assertOk();
    }

    public static function roles(): array
    {
        return [
             'empleado' => ['employee'],
            'administrador adicional de la empresa' => ['company_admin'],
        ];
    }

    #[DataProvider('roles')]
    #[TestDox('Los JWT antiguos siguen invalidados tras reactivar la cuenta — con $_dataName')]
    public function test_previously_issued_jwts_remain_revoked_after_administrator_reactivation(string $role): void
    {
        [$owner, $user] = $this->createManagedUser($role);
        $firstToken = $this->loginApi($user);
        $secondToken = $this->loginApi($user);
        $ownerToken = $this->loginApi($owner);
        $rememberToken = $user->remember_token;

        $this->api($firstToken, 'GET', '/api/greenhouses')->assertOk();
        $this->api($ownerToken, 'PATCH', '/api/users/'.$user->id.'/status', ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $inactiveVersion = $user->fresh()->authenticationVersion();
        $this->assertNotSame('0', $inactiveVersion);
        $this->assertNotSame($rememberToken, $user->fresh()->remember_token);

        foreach ([$firstToken, $secondToken] as $token) {
            $this->api($token, 'GET', '/api/greenhouses')
                ->assertForbidden()
                ->assertExactJson(['message' => self::INACTIVE_MESSAGE]);
        }

        $this->resetGuards();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertForbidden()
            ->assertExactJson(['message' => self::INACTIVE_MESSAGE]);

        $this->api($ownerToken, 'PATCH', '/api/users/'.$user->id.'/status', ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
        $activeVersion = $user->fresh()->authenticationVersion();
        $this->assertNotSame($inactiveVersion, $activeVersion);

        foreach ([$firstToken, $secondToken] as $token) {
            $this->api($token, 'GET', '/api/greenhouses')
                ->assertUnauthorized()
                ->assertExactJson(['message' => self::EXPIRED_MESSAGE]);
        }

        $newToken = $this->loginApi($user);
        $this->assertNotSame($firstToken, $newToken);
        $this->assertSame($activeVersion, JWTAuth::setToken($newToken)->getPayload()->get('auth_version'));
        $this->api($newToken, 'GET', '/api/greenhouses')->assertOk();
        $this->api($ownerToken, 'GET', '/api/users')->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'active']);
    }

    #[DataProvider('roles')]
    #[TestDox('Una sesión antigua se rechaza y un nuevo inicio de sesión funciona — con $_dataName')]
    public function test_unvisited_web_session_is_rejected_after_reactivation_and_new_login_succeeds(string $role): void
    {
        [$owner, $user] = $this->createManagedUser($role);
        $ownerToken = $this->loginApi($owner);
        $this->loginWeb($user);
        $sessionKey = Auth::guard('web')->getName();
        $this->assertSame([
            'user_id' => $user->id,
            'version' => '0',
        ], session(User::AUTH_VERSION_SESSION_KEY));
        $oldSession = session()->all();

        // Este navegador no realiza solicitudes mientras la cuenta está inactiva.
        $this->cycleAccount($ownerToken, $user);
        $this->withSession($oldSession);
        $this->resetGuards();

        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', self::EXPIRED_MESSAGE)
            ->assertSessionMissing($sessionKey)
            ->assertSessionMissing(User::AUTH_VERSION_SESSION_KEY);
        $this->assertGuest('web');

        $this->loginWeb($user);
        $this->assertSame($user->fresh()->authenticationVersion(), session(User::AUTH_VERSION_SESSION_KEY.'.version'));
        $this->resetGuards();
        $this->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($user, 'web');
    }

    #[DataProvider('roles')]
    #[TestDox('Una cookie anterior no permite autenticarse tras la reactivación — con $_dataName')]
    public function test_previous_remember_cookie_cannot_authenticate_after_reactivation(string $role): void
    {
        [$owner, $user] = $this->createManagedUser($role);
        $ownerToken = $this->loginApi($owner);
        $response = $this->loginWeb($user, true);
        $recallerName = Auth::guard('web')->getRecallerName();
        $recaller = $response->getCookie($recallerName);
        $this->assertNotNull($recaller);

        // Primero comprobamos que esta cookie permite autenticar una sesión nueva.
        $this->clearBrowserSession();
        $this->withCookie($recallerName, $recaller->getValue());
        $this->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertTrue(Auth::guard('web')->viaRemember());

        $this->cycleAccount($ownerToken, $user);
        $this->clearBrowserSession();
        $this->withCookie($recallerName, $recaller->getValue());
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest('web');

        $this->loginWeb($user, true);
        $this->resetGuards();
        $this->get('/dashboard')->assertOk();
    }

    #[DataProvider('roles')]
    #[TestDox('Un JWT antiguo sin versión mantiene acceso hasta cambiar el estado de la cuenta — con $_dataName')]
    public function test_legacy_jwt_without_version_preserves_active_access_until_a_status_change(string $role): void
    {
        [$owner, $user] = $this->createManagedUser($role);
        $ownerToken = $this->loginApi($owner);
        $payload = JWTAuth::factory()->customClaims([
            'sub' => $user->id,
            'prv' => sha1(User::class),
        ])->make(true);
        $legacyToken = JWTAuth::manager()->encode($payload)->get();

        $this->assertNull($payload->get('auth_version'));
        $this->assertNull($user->fresh()->auth_version);
        $this->api($legacyToken, 'GET', '/api/greenhouses')->assertOk();

        $this->cycleAccount($ownerToken, $user);
        $this->api($legacyToken, 'GET', '/api/greenhouses')
            ->assertUnauthorized()
            ->assertExactJson(['message' => self::EXPIRED_MESSAGE]);
    }

    #[DataProvider('roles')]
    #[TestDox('Una sesión web antigua sin versión no se recupera tras reactivar la cuenta — con $_dataName')]
    public function test_legacy_web_session_without_version_is_not_upgraded_after_reactivation(string $role): void
    {
        [$owner, $user] = $this->createManagedUser($role);
        $ownerToken = $this->loginApi($owner);
        $this->loginWeb($user);
        session()->forget(User::AUTH_VERSION_SESSION_KEY);
        session()->save();
        $oldSessionId = session()->getId();
        $storedSession = $this->readStoredSession($oldSessionId);
        $this->assertSame($user->id, $storedSession->get(Auth::guard('web')->getName()));
        $this->assertFalse($storedSession->has(User::AUTH_VERSION_SESSION_KEY));
        $this->assertNull($user->fresh()->auth_version);
        $this->resetGuards();
        $this->get('/dashboard')->assertOk();

        $this->cycleAccount($ownerToken, $user);
        $this->assertFalse($this->readStoredSession($oldSessionId)->has(User::AUTH_VERSION_SESSION_KEY));
        $this->withCookie(config('session.cookie'), $oldSessionId);
        $this->resetGuards();
        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', self::EXPIRED_MESSAGE);
        $this->assertGuest('web');
    }

    #[DataProvider('roles')]
    #[TestDox('Renovar el JWT no restaura credenciales revocadas — con $_dataName')]
    public function test_refresh_preserves_version_and_cannot_restore_revoked_credentials(string $role): void
    {
        [$owner, $user] = $this->createManagedUser($role);
        $ownerToken = $this->loginApi($owner);
        $this->cycleAccount($ownerToken, $user);
        $token = $this->loginApi($user);
        $version = $user->fresh()->authenticationVersion();
        $this->resetGuards();
        $refreshed = JWTAuth::setToken($token)->refresh();
        $this->assertSame($version, JWTAuth::setToken($refreshed)->getPayload()->get('auth_version'));
        $this->api($refreshed, 'GET', '/api/greenhouses')->assertOk();

        $this->cycleAccount($ownerToken, $user);
        $this->api($refreshed, 'GET', '/api/greenhouses')
            ->assertUnauthorized()
            ->assertExactJson(['message' => self::EXPIRED_MESSAGE]);

        // Aunque el token se renueve fuera de una ruta protegida,
        // su versión original no debe eludir la revocación de la cuenta.
        $this->resetGuards();
        $refreshedAfterRevocation = JWTAuth::setToken($refreshed)->refresh();
        $this->assertSame($version, JWTAuth::setToken($refreshedAfterRevocation)->getPayload()->get('auth_version'));
        $this->api($refreshedAfterRevocation, 'GET', '/api/greenhouses')
            ->assertUnauthorized()
            ->assertExactJson(['message' => self::EXPIRED_MESSAGE]);
    }

    #[TestDox('Solo los cambios de estado renuevan credenciales y la versión no admite asignación masiva')]
    public function test_only_status_changes_rotate_credentials_and_version_is_not_mass_assignable(): void
    {
        [, $user] = $this->createManagedUser('employee');
        $rememberToken = $user->remember_token;
        $this->assertSame('0', $user->authenticationVersion());

        $user->update(['name' => 'Nombre actualizado', 'auth_version' => (string) Str::uuid()]);
        $this->assertSame('Nombre actualizado', $user->fresh()->name);
        $this->assertNull($user->fresh()->auth_version);
        $this->assertSame($rememberToken, $user->fresh()->remember_token);

        $user->update(['status' => 'inactive']);
        $firstVersion = $user->fresh()->authenticationVersion();
        $firstRememberToken = $user->fresh()->remember_token;
        $this->assertTrue(Str::isUuid($firstVersion));
        $this->assertNotSame($rememberToken, $firstRememberToken);

        $user->update(['status' => 'inactive', 'name' => 'Cuenta inactiva']);
        $this->assertSame($firstVersion, $user->fresh()->authenticationVersion());
        $this->assertSame($firstRememberToken, $user->fresh()->remember_token);

        $user->update(['status' => 'active']);
        $secondVersion = $user->fresh()->authenticationVersion();
        $this->assertTrue(Str::isUuid($secondVersion));
        $this->assertNotSame($firstVersion, $secondVersion);
        $this->assertNotSame($firstRememberToken, $user->fresh()->remember_token);

        $user->update(['status' => 'inactive']);
        $thirdVersion = $user->fresh()->authenticationVersion();
        $this->assertNotContains($thirdVersion, [$firstVersion, $secondVersion, '0']);
    }

    #[TestDox('Empleados y administradores de otra empresa no pueden cambiar estados ni revocar credenciales')]
    public function test_employees_and_foreign_administrators_cannot_change_status_or_revoke_credentials(): void
    {
        [$owner, $user] = $this->createManagedUser('employee');
        $otherEmployee = $this->createUser($owner->company, 'employee');
        [$foreignOwner] = $this->createManagedUser('employee');
        $originalVersion = $user->fresh()->authenticationVersion();
        $originalRememberToken = $user->fresh()->remember_token;
        $userToken = $this->loginApi($user);

        foreach ([$otherEmployee, $foreignOwner] as $actor) {
            $this->api($this->loginApi($actor), 'PATCH', '/api/users/'.$user->id.'/status', [
                'status' => 'inactive',
            ])->assertForbidden();

            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'company_id' => $owner->company_id,
                'status' => 'active',
                'auth_version' => null,
                'remember_token' => $originalRememberToken,
            ]);
            $this->assertSame($originalVersion, $user->fresh()->authenticationVersion());
            $this->api($userToken, 'GET', '/api/greenhouses')->assertOk();
        }
    }

    public static function invalidSessionMarkers(): array
    {
        return [
            'otro usuario' => [['user_id' => 'another-user', 'version' => '0']],
            'identidad ausente' => [['version' => '0']],
            'versión ausente' => [['user_id' => 'current']],
            'versión de tipo incorrecto' => [['user_id' => 'current', 'version' => 0]],
            'versión incorrecta' => [['user_id' => 'current', 'version' => 'wrong-version']],
            'marcador malformado' => ['invalid'],
        ];
    }

    #[DataProvider('invalidSessionMarkers')]
    #[TestDox('Los marcadores de sesión inválidos se rechazan sin afectar credenciales válidas — con $_dataName')]
    public function test_invalid_session_markers_fail_closed_without_revoking_valid_credentials(array|string $marker): void
    {
        [, $user] = $this->createManagedUser('employee');
        $validToken = $this->loginApi($user);
        $this->loginWeb($user);
        $validSessionId = session()->getId();
        $sessionKey = Auth::guard('web')->getName();
        $rememberToken = $user->fresh()->remember_token;

        if (is_array($marker) && ($marker['user_id'] ?? null) === 'current') {
            $marker['user_id'] = $user->id;
        }

        // Conservamos la sesión válida del navegador y creamos otra independiente.
        session()->migrate(false);
        $invalidSessionId = session()->getId();
        $this->assertNotSame($validSessionId, $invalidSessionId);
        $this->withSession([User::AUTH_VERSION_SESSION_KEY => $marker]);
        // StartSession vuelve a cargar la sesión en la siguiente solicitud; por eso,
        // put() no reemplaza por sí solo las credenciales del inicio de sesión real.
        session()->save();
        $storedSession = $this->readStoredSession($invalidSessionId);
        $this->assertSame($marker, $storedSession->get(User::AUTH_VERSION_SESSION_KEY));
        $this->assertSame($user->id, $storedSession->get($sessionKey));
        $this->withCookie(config('session.cookie'), $invalidSessionId);
        $this->resetGuards();
        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', self::EXPIRED_MESSAGE)
            ->assertSessionMissing($sessionKey)
            ->assertSessionMissing(User::AUTH_VERSION_SESSION_KEY);
        $this->assertGuest('web');
        $this->assertNotSame($invalidSessionId, session()->getId());
        $this->assertSame('', session()->getHandler()->read($invalidSessionId));
        $this->assertSame($rememberToken, $user->fresh()->remember_token);
        $this->assertNull($user->fresh()->auth_version);

        // Rechazar un navegador no debe invalidar otro token o sesión válidos.
        $this->api($validToken, 'GET', '/api/greenhouses')->assertOk();
        $this->withCookie(config('session.cookie'), $validSessionId);
        $this->resetGuards();
        $this->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($user, 'web');
    }

    #[TestDox('Los modelos desactualizados no reutilizan versiones de credenciales')]
    public function test_stale_model_instances_do_not_reuse_credential_versions(): void
    {
        [, $user] = $this->createManagedUser('employee');
        $first = $user->fresh();
        $second = $user->fresh();

        $first->update(['status' => 'inactive']);
        $firstVersion = $user->fresh()->authenticationVersion();
        $user->fresh()->update(['status' => 'active']);
        $reactivatedVersion = $user->fresh()->authenticationVersion();
        $second->update(['status' => 'inactive']);
        $secondVersion = $user->fresh()->authenticationVersion();

        $this->assertTrue(Str::isUuid($secondVersion));
        $this->assertNotContains($secondVersion, [$firstVersion, $reactivatedVersion, '0']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'inactive']);
    }

    #[TestDox('La migración de versión de autenticación es reversible y conserva usuarios existentes')]
    public function test_auth_version_migration_is_additive_and_reversible_for_existing_users(): void
    {
        // La configuración compartida ya comprobó que la conexión usa SQLite :memory:.
        // Probamos solo esta migración; nunca ejecutamos migrate:rollback en una base de datos real.
        $migration = require database_path('migrations/2026_10_19_000000_add_auth_version_to_users_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'auth_version'));
        [, $user] = $this->createManagedUser('employee');
        $before = (array) DB::table('users')->find($user->id);

        $migration->up();
        $this->assertTrue(Schema::hasColumn('users', 'auth_version'));
        $after = (array) DB::table('users')->find($user->id);
        $this->assertArrayHasKey('auth_version', $after);
        $this->assertNull($after['auth_version']);
        unset($after['auth_version']);
        $this->assertSame($before, $after);

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'auth_version'));
        $this->assertSame($before, (array) DB::table('users')->find($user->id));
        $migration->up();
        $this->assertNull($user->fresh()->auth_version);
    }

    private function createManagedUser(string $role): array
    {
        $company = Company::create([
            'name' => 'Empresa '.fake()->uuid(),
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
        ]);
        $company->subscriptions()->create([
            'plan_id' => Plan::query()->where('slug', 'pro')->firstOrFail()->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => false,
        ]);

        return [
            $this->createUser($company, 'company_admin', true),
            $this->createUser($company, $role),
        ];
    }

    private function createUser(Company $company, string $role, bool $owner = false): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'role_id' => Role::query()->where('name', $role)->firstOrFail()->id,
            'is_company_owner' => $owner,
            'status' => 'active',
            'plan_restricted' => false,
        ]);
    }

    private function loginApi(User $user): string
    {
        $this->resetGuards();
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['access_token']);

        return $response->json('access_token');
    }

    private function loginWeb(User $user, bool $remember = false): TestResponse
    {
        $this->resetGuards();
        $response = $this->withCredentials()->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => $remember,
        ])->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertAuthenticatedAs($user, 'web');
        $this->withCookie(config('session.cookie'), session()->getId());

        return $response;
    }

    private function cycleAccount(string $ownerToken, User $user): void
    {
        foreach (['inactive', 'active'] as $status) {
            $this->api($ownerToken, 'PATCH', '/api/users/'.$user->id.'/status', ['status' => $status])
                ->assertOk()
                ->assertJsonPath('data.status', $status);
        }
    }

    private function clearBrowserSession(): void
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        session()->flush();
        session()->regenerate(true);
        $this->resetGuards();
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    private function readStoredSession(string $sessionId): Store
    {
        // Conservamos la serialización de la aplicación, pero descartamos su estado en memoria.
        $session = clone session()->driver();
        $session->flush();
        $session->setId($sessionId);
        $session->start();

        return $session;
    }

    private function resetGuards(): void
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->app['tymon.jwt']->unsetToken();
        $this->app['tymon.jwt.auth']->unsetToken();
    }

    private function api(string $token, string $method, string $uri, array $data = []): TestResponse
    {
        $this->resetGuards();

        return $this->json($method, $uri, $data, ['Authorization' => 'Bearer '.$token]);
    }
}
