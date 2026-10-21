<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Greenhouse;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class InactiveAccountAccessTest extends TestCase
{
    use UsesInMemoryDatabase;

    private const INACTIVE_MESSAGE = 'La cuenta se encuentra inactiva.';

    public static function roles(): array
    {
        return [
            'employee' => ['employee'],
            'company administrator' => ['company_admin'],
            'platform administrator' => ['superadmin'],
        ];
    }

    #[DataProvider('roles')]
    public function test_active_accounts_keep_access_to_protected_api_reads(string $role): void
    {
        $user = $this->createUser($role);
        $own = $this->createGreenhouse($user->company);
        $foreign = $this->createGreenhouse($this->createCompany());
        $token = JWTAuth::fromUser($user);

        $this->api($token, 'GET', '/api/greenhouses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonMissing(['id' => $foreign->id]);

        $this->api($token, 'GET', '/api/greenhouses/'.$own->id)
            ->assertOk()
            ->assertJsonPath('data.id', $own->id);

        foreach (['/api/dashboard', '/api/alerts', '/api/history', '/api/irrigation-events'] as $uri) {
            $this->api($token, 'GET', $uri)->assertOk();
        }

        $this->api($token, 'GET', '/api/reports?greenhouse_id='.$own->id)->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'active']);
    }

    #[DataProvider('roles')]
    public function test_previously_issued_jwt_is_rejected_after_database_deactivation(string $role): void
    {
        $user = $this->createUser($role);
        $greenhouse = $this->createGreenhouse($user->company);
        $token = JWTAuth::fromUser($user);

        $this->api($token, 'GET', '/api/greenhouses')->assertOk();
        $this->deactivateInDatabase($user);
        $storedUser = DB::table('users')->find($user->id);

        foreach ([
            '/api/greenhouses',
            '/api/greenhouses/'.$greenhouse->id,
            '/api/dashboard',
            '/api/alerts',
            '/api/history',
            '/api/irrigation-events',
            '/api/reports?greenhouse_id='.$greenhouse->id,
            '/api/users',
            '/api/company/settings',
        ] as $uri) {
            $this->api($token, 'GET', $uri)
                ->assertForbidden()
                ->assertExactJson(['message' => self::INACTIVE_MESSAGE]);
        }

        $this->assertEquals($storedUser, DB::table('users')->find($user->id));
    }

    public static function nonJsonApiAcceptHeaders(): array
    {
        return [
            'employee requesting HTML' => ['employee', ['Accept' => 'text/html']],
            'administrator requesting HTML' => ['company_admin', ['Accept' => 'text/html']],
            'employee without Accept' => ['employee', []],
            'administrator without Accept' => ['company_admin', []],
            'platform administrator requesting HTML' => ['superadmin', ['Accept' => 'text/html']],
            'platform administrator without Accept' => ['superadmin', []],
        ];
    }

    #[DataProvider('nonJsonApiAcceptHeaders')]
    public function test_inactive_jwt_always_gets_json_without_requiring_a_web_session(
        string $role,
        array $headers
    ): void {
        $user = $this->createUser($role);
        $token = JWTAuth::fromUser($user);
        $this->deactivateInDatabase($user);
        $this->resetGuards();

        $this->get('/api/greenhouses', [
            'Authorization' => 'Bearer '.$token,
            ...$headers,
        ])
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => self::INACTIVE_MESSAGE]);
    }

    #[DataProvider('roles')]
    public function test_only_active_company_administrators_can_create_zones(string $role): void
    {
        $user = $this->createUser($role);
        $greenhouse = $this->createGreenhouse($user->company);
        $token = JWTAuth::fromUser($user);

        $response = $this->api($token, 'POST', '/api/greenhouses/'.$greenhouse->id.'/zones', [
            'name' => 'Zona permitida',
            'description' => 'Creada por una cuenta activa',
        ]);

        if ($role !== 'company_admin') {
            $response->assertForbidden();
            $this->assertDatabaseCount('zones', 0);

            return;
        }

        $response->assertCreated()
            ->assertJsonPath('data.greenhouse_id', $greenhouse->id)
            ->assertJsonPath('data.name', 'Zona permitida');

        $this->assertDatabaseHas('zones', [
            'greenhouse_id' => $greenhouse->id,
            'name' => 'Zona permitida',
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('zones', 1);
    }

    #[DataProvider('roles')]
    public function test_inactive_accounts_cannot_write_or_trigger_plan_synchronization(string $role): void
    {
        $user = $this->createUser($role);
        $greenhouse = $this->createGreenhouse($user->company);
        $token = JWTAuth::fromUser($user);
        $this->deactivateInDatabase($user);

        // A permitted request would clear this flag while synchronizing the plan.
        DB::table('greenhouses')->where('id', $greenhouse->id)->update(['plan_restricted' => true]);
        $before = DB::table('greenhouses')->find($greenhouse->id);
        $storedUser = DB::table('users')->find($user->id);

        $this->api($token, 'POST', '/api/greenhouses/'.$greenhouse->id.'/zones', [
            'name' => 'Zona prohibida',
        ])
            ->assertForbidden()
            ->assertExactJson(['message' => self::INACTIVE_MESSAGE]);

        $this->assertDatabaseCount('zones', 0);
        $this->assertEquals($before, DB::table('greenhouses')->find($greenhouse->id));
        $this->assertEquals($storedUser, DB::table('users')->find($user->id));
    }

    #[DataProvider('roles')]
    public function test_active_accounts_still_cannot_read_or_write_another_company(string $role): void
    {
        $user = $this->createUser($role);
        $foreign = $this->createGreenhouse($this->createCompany());
        $before = $foreign->getAttributes();
        $token = JWTAuth::fromUser($user);

        $this->api($token, 'GET', '/api/greenhouses/'.$foreign->id)
            ->assertForbidden()
            ->assertJsonMissing(['name' => $foreign->name]);

        $this->api($token, 'POST', '/api/greenhouses/'.$foreign->id.'/zones', [
            'name' => 'Zona de otra empresa',
        ])->assertForbidden();

        $this->assertSame($before, $foreign->fresh()->getAttributes());
        $this->assertDatabaseCount('zones', 0);
    }

    #[DataProvider('roles')]
    public function test_account_state_is_checked_before_binding_protected_resources(string $role): void
    {
        $user = $this->createUser($role);
        $token = JWTAuth::fromUser($user);

        $this->api($token, 'GET', '/api/greenhouses/999999')->assertNotFound();
        $this->deactivateInDatabase($user);

        $this->api($token, 'GET', '/api/greenhouses/999999')
            ->assertForbidden()
            ->assertExactJson(['message' => self::INACTIVE_MESSAGE]);
    }

    #[DataProvider('roles')]
    public function test_active_accounts_keep_web_access_through_a_real_login(string $role): void
    {
        $user = $this->createUser($role);
        $this->loginWeb($user);

        foreach (['/greenhouses', '/zones', '/dashboard', '/alerts', '/history', '/irrigation', '/reports'] as $uri) {
            $this->resetGuards();
            $this->get($uri)->assertOk();
            $this->assertAuthenticatedAs($user, 'web');
        }

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'active']);
    }

    public static function inactiveWebRoutes(): array
    {
        $cases = [];

        foreach (['employee', 'company_admin', 'superadmin'] as $role) {
            foreach (['greenhouses', 'zones', 'dashboard', 'alerts', 'history', 'irrigation', 'reports', 'users', 'settings'] as $page) {
                $cases[$role.' '.$page] = [$role, '/'.$page];
            }
        }

        return $cases;
    }

    #[DataProvider('inactiveWebRoutes')]
    public function test_existing_web_session_is_invalidated_after_database_deactivation(
        string $role,
        string $uri
    ): void {
        $user = $this->createUser($role);
        $sessionKey = $this->loginWeb($user);
        $this->withSession(['protected_marker' => 'must be removed']);
        $sessionId = session()->getId();
        $csrfToken = session()->token();
        $this->deactivateInDatabase($user);
        $storedUser = DB::table('users')->find($user->id);

        $this->resetGuards();
        $this->get($uri)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', self::INACTIVE_MESSAGE)
            ->assertSessionMissing($sessionKey)
            ->assertSessionMissing('protected_marker');

        $this->assertGuest('web');
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($csrfToken, session()->token());
        $this->assertEquals($storedUser, DB::table('users')->find($user->id));
    }

    #[DataProvider('roles')]
    public function test_json_request_on_web_route_also_invalidates_the_inactive_session(string $role): void
    {
        $user = $this->createUser($role);
        $sessionKey = $this->loginWeb($user);
        $this->deactivateInDatabase($user);
        $this->resetGuards();

        $this->getJson('/dashboard')
            ->assertForbidden()
            ->assertExactJson(['message' => self::INACTIVE_MESSAGE])
            ->assertSessionMissing($sessionKey);

        $this->assertGuest('web');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'inactive',
            'remember_token' => $user->remember_token,
        ]);
    }

    #[DataProvider('roles')]
    public function test_active_roles_keep_existing_administrative_access_rules(string $role): void
    {
        $user = $this->createUser($role);
        $token = JWTAuth::fromUser($user);
        $expected = $role === 'company_admin' ? 200 : 403;

        $this->api($token, 'GET', '/api/users')->assertStatus($expected);
        $this->api($token, 'GET', '/api/company/settings')->assertStatus($expected);
        $this->loginWeb($user);

        foreach (['/users', '/settings'] as $uri) {
            $this->resetGuards();
            $this->get($uri)->assertStatus($expected);
        }
    }

    public function test_guests_keep_normal_web_redirects_and_api_unauthenticated_responses(): void
    {
        foreach (['/greenhouses', '/dashboard', '/users', '/settings'] as $uri) {
            $this->resetGuards();
            $this->get($uri)->assertRedirect(route('login'));
        }

        foreach (['/api/greenhouses', '/api/dashboard', '/api/users', '/api/company/settings'] as $uri) {
            $this->resetGuards();
            $this->getJson($uri)->assertUnauthorized();
        }

        $this->resetGuards();
        $this->postJson('/api/greenhouses/999999/zones', ['name' => 'Invitado'])
            ->assertUnauthorized();
        $this->assertDatabaseCount('zones', 0);
    }

    public function test_public_pages_and_authentication_validation_remain_accessible(): void
    {
        foreach (['/', '/login', '/register', '/forgot-password', '/reset-password/example-token', '/up'] as $uri) {
            $this->get($uri)->assertOk();
        }

        foreach (['/login', '/api/login', '/api/register', '/api/forgot-password', '/api/password/reset'] as $uri) {
            $this->postJson($uri)->assertUnprocessable()->assertJsonValidationErrors('email');
        }

        $this->assertDatabaseCount('users', 0);
    }

    #[DataProvider('roles')]
    public function test_login_endpoints_remain_public_and_preserve_their_account_checks(string $role): void
    {
        $user = $this->createUser($role);
        $credentials = ['email' => $user->email, 'password' => 'password'];

        $this->postJson('/api/login', $credentials)
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'user' => ['id']])
            ->assertJsonPath('user.id', $user->id);

        $this->deactivateInDatabase($user);

        foreach (['/login', '/api/login'] as $uri) {
            $this->resetGuards();
            $this->postJson($uri, $credentials)
                ->assertForbidden()
                ->assertExactJson(['message' => self::INACTIVE_MESSAGE]);
        }

        $this->assertGuest('web');
    }

    #[DataProvider('roles')]
    public function test_inactive_accounts_can_recover_password_without_reactivating_the_account(string $role): void
    {
        Notification::fake();
        $user = $this->createUser($role);
        $jwt = JWTAuth::fromUser($user);
        $this->deactivateInDatabase($user);

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class);
        $token = Password::createToken($user);
        $newPassword = 'Recovered-Password-61!';

        $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'token' => $token,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertOk();

        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'inactive']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->api($jwt, 'GET', '/api/greenhouses')
            ->assertForbidden()
            ->assertExactJson(['message' => self::INACTIVE_MESSAGE]);
    }

    public function test_public_email_verification_keeps_its_existing_signed_access(): void
    {
        $user = $this->createUser('employee');
        $user->forceFill(['email_verified_at' => null])->save();
        $this->deactivateInDatabase($user);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->getJson($url)->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'inactive']);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_all_authenticated_routes_check_activity_before_plan_access(): void
    {
        $protected = 0;
        $publicUris = [
            '/', 'login', 'register', 'forgot-password', 'reset-password/{token}', 'up',
            'api/register', 'api/login', 'api/forgot-password', 'api/password/reset',
            'api/email/verify/{id}/{hash}',
        ];

        foreach ($this->app['router']->getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            $authentication = array_values(array_filter(
                $middleware,
                static fn (string $entry): bool => $entry === 'auth' || str_starts_with($entry, 'auth:')
            ));

            if ($authentication !== []) {
                $protected++;
                $label = implode('|', $route->methods()).' '.$route->uri();
                $this->assertContains('active.user', $middleware, $label);
                $this->assertLessThan(
                    array_search('active.user', $middleware, true),
                    array_search($authentication[0], $middleware, true),
                    'Authentication must run first: '.$label
                );

                if (in_array('plan.access', $middleware, true)) {
                    $this->assertLessThan(
                        array_search('plan.access', $middleware, true),
                        array_search('active.user', $middleware, true),
                        'Activity must be checked before plan synchronization: '.$label
                    );
                }
            }

            if (in_array($route->uri(), $publicUris, true)) {
                $this->assertNotContains('active.user', $middleware, $route->uri());
                $this->assertSame([], $authentication, $route->uri());
            }
        }

        $this->assertGreaterThan(0, $protected);
    }

    private function createCompany(): Company
    {
        return Company::create([
            'name' => 'Empresa '.fake()->uuid(),
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
        ]);
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'company_id' => $this->createCompany()->id,
            'role_id' => Role::query()->where('name', $role)->firstOrFail()->id,
            'is_company_owner' => $role === 'company_admin',
            'status' => 'active',
            'plan_restricted' => false,
        ]);
    }

    private function createGreenhouse(Company $company): Greenhouse
    {
        return $company->greenhouses()->create([
            'name' => 'Invernadero '.fake()->uuid(),
            'crop_type' => 'Tomate',
            'area' => 100,
            'location' => 'Ubicación de prueba',
            'planting_date' => '2026-01-01',
            'nominal_flow' => 10,
            'status' => 'active',
            'plan_restricted' => false,
        ])->refresh();
    }

    private function deactivateInDatabase(User $user): void
    {
        // Deliberately leave the issuing user object active. The next request
        // must retrieve the changed database record through its real guard.
        $this->assertSame('active', $user->status);
        $this->assertSame(1, DB::table('users')->where('id', $user->id)->update(['status' => 'inactive']));
        $this->assertSame('active', $user->status);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'inactive']);
    }

    private function loginWeb(User $user): string
    {
        $this->resetGuards();
        $this->withCredentials()->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['access_token']);

        $sessionKey = Auth::guard('web')->getName();
        $this->assertSame($user->id, session($sessionKey));
        $this->withCookie(config('session.cookie'), session()->getId());

        return $sessionKey;
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
