<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Greenhouse;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class GreenhousePermissionsTest extends TestCase
{
    use UsesInMemoryDatabase {
        setUp as private setUpInMemoryDatabase;
    }

    private Company $company;

    protected function setUp(): void
    {
        $this->setUpInMemoryDatabase();
        $this->company = $this->createCompany();
    }

    public static function roles(): array
    {
        return [
            'employee' => ['employee'],
            'company administrator' => ['company_admin'],
        ];
    }

    public static function mutations(): array
    {
        return [
            'create' => ['POST', '/api/greenhouses', null],
            'edit with PUT' => ['PUT', '/api/greenhouses/%d', null],
            'edit with PATCH' => ['PATCH', '/api/greenhouses/%d', null],
            'activate' => ['PATCH', '/api/greenhouses/%d/status', 'active'],
            'deactivate' => ['PATCH', '/api/greenhouses/%d/status', 'inactive'],
        ];
    }

    public static function webUserRoles(): array
    {
        $cases = [];
        foreach (['employee', 'company_admin', 'superadmin'] as $role) {
            foreach (['active', 'inactive'] as $status) {
                $cases[$role.' '.$status] = [
                    $role,
                    $status,
                    $role === 'company_admin' && $status === 'active',
                ];
            }
        }

        return $cases;
    }

    public static function unauthorizedMutations(): array
    {
        $cases = [];
        foreach (self::webUserRoles() as $userName => [$role, $status, $canManage]) {
            if ($canManage) {
                continue;
            }

            foreach (self::mutations() as $mutationName => $request) {
                $cases[$userName.' '.$mutationName] = [$role, $status, ...$request];
            }
        }

        return $cases;
    }

    public static function foreignRequests(): array
    {
        $cases = [];
        foreach (['employee', 'company_admin'] as $role) {
            foreach ([
                'read' => ['GET', '/api/greenhouses/%d', null],
                'edit PUT' => ['PUT', '/api/greenhouses/%d', null],
                'edit PATCH' => ['PATCH', '/api/greenhouses/%d', null],
                'activate' => ['PATCH', '/api/greenhouses/%d/status', 'active'],
                'deactivate' => ['PATCH', '/api/greenhouses/%d/status', 'inactive'],
            ] as $name => $request) {
                $cases[$role.' '.$name] = [$role, ...$request];
            }
        }

        return $cases;
    }

    public static function updates(): array
    {
        $cases = self::mutations();
        unset($cases['create']);

        return $cases;
    }

    public static function protectedRequests(): array
    {
        return [
            'list' => ['GET', '/api/greenhouses', null],
            'show' => ['GET', '/api/greenhouses/%d', null],
            ...self::mutations(),
        ];
    }

    #[DataProvider('roles')]
    public function test_user_can_list_only_their_company_greenhouses(string $role): void
    {
        $own = $this->createGreenhouse($this->company);
        $foreign = $this->createGreenhouse($this->createCompany());
        $this->authenticate($role);

        $this->getJson('/api/greenhouses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.company_id', $this->company->id)
            ->assertJsonMissing(['id' => $foreign->id]);
    }

    #[DataProvider('roles')]
    public function test_user_can_read_their_company_greenhouse(string $role): void
    {
        $greenhouse = $this->createGreenhouse($this->company);
        $this->authenticate($role);

        $this->getJson('/api/greenhouses/'.$greenhouse->id)
            ->assertOk()
            ->assertJsonPath('data.id', $greenhouse->id);
    }

    #[DataProvider('unauthorizedMutations')]
    public function test_unauthorized_user_cannot_mutate_greenhouses(
        string $role,
        string $userStatus,
        string $method,
        string $uri,
        ?string $status,
    ): void {
        $greenhouse = $this->createGreenhouse($this->company, $status === 'active' ? 'inactive' : 'active');
        $before = $greenhouse->getRawOriginal();
        $this->authenticate($role, $userStatus);

        $response = $this->json($method, sprintf($uri, $greenhouse->id), $this->payload($status))
            ->assertForbidden();

        if ($userStatus === 'inactive') {
            $response->assertExactJson(['message' => 'La cuenta se encuentra inactiva.']);
        }

        $this->assertDatabaseCount('greenhouses', 1);
        $this->assertSame($before, $greenhouse->fresh()->getRawOriginal());
        $this->assertDatabaseCount('greenhouse_thresholds', 0);
    }

    public function test_active_administrator_can_create_a_greenhouse_with_default_thresholds(): void
    {
        $this->authenticate('company_admin');

        $response = $this->postJson('/api/greenhouses', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.company_id', $this->company->id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonCount(3, 'data.thresholds');

        $this->assertDatabaseHas('greenhouses', [
            'id' => $response->json('data.id'),
            'company_id' => $this->company->id,
            'name' => 'Updated greenhouse',
        ]);
        $this->assertDatabaseCount('greenhouse_thresholds', 3);
    }

    #[DataProvider('updates')]
    public function test_active_administrator_can_edit_or_change_status(string $method, string $uri, ?string $status): void
    {
        $greenhouse = $this->createGreenhouse($this->company, $status === 'active' ? 'inactive' : 'active');
        $this->authenticate('company_admin');

        $response = $this->json($method, sprintf($uri, $greenhouse->id), $this->payload($status))
            ->assertOk()
            ->assertJsonPath('data.id', $greenhouse->id);

        if ($status !== null) {
            $response->assertJsonPath('data.status', $status);
            $this->assertSame($status, $greenhouse->fresh()->status);
        } else {
            $response->assertJsonPath('data.name', 'Updated greenhouse');
            $this->assertDatabaseHas('greenhouses', [
                'id' => $greenhouse->id,
                'company_id' => $this->company->id,
                ...$this->payload(),
                'planting_date' => '2026-01-01 00:00:00',
            ]);
        }
        $this->assertDatabaseCount('greenhouses', 1);
    }

    #[DataProvider('foreignRequests')]
    public function test_user_cannot_read_or_modify_another_company_greenhouse(
        string $role,
        string $method,
        string $uri,
        ?string $status,
    ): void {
        $foreign = $this->createGreenhouse($this->createCompany(), $status === 'active' ? 'inactive' : 'active');
        $before = $foreign->getRawOriginal();
        $this->authenticate($role);

        $this->json($method, sprintf($uri, $foreign->id), $this->payload($status))
            ->assertForbidden()
            ->assertJsonMissing(['name' => $foreign->name]);

        $this->assertSame($before, $foreign->fresh()->getRawOriginal());
        $this->assertDatabaseCount('greenhouses', 1);
    }

    #[DataProvider('protectedRequests')]
    public function test_guest_cannot_access_protected_greenhouse_routes(string $method, string $uri, ?string $status): void
    {
        $greenhouse = $this->createGreenhouse($this->company);
        $before = $greenhouse->getRawOriginal();

        $this->json($method, sprintf($uri, $greenhouse->id), $this->payload($status))
            ->assertUnauthorized();

        $this->assertDatabaseCount('greenhouses', 1);
        $this->assertSame($before, $greenhouse->fresh()->getRawOriginal());
    }

    #[DataProvider('webUserRoles')]
    public function test_web_interface_exposes_management_controls_only_to_active_company_administrators(
        string $role,
        string $status,
        bool $canManage,
    ): void {
        // Establish a real session before deactivation instead of bypassing login.
        $user = $this->createUser($role);
        $this->withCredentials()->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertAuthenticatedAs($user, 'web');
        $sessionKey = Auth::guard('web')->getName();
        $this->assertSame([
            'user_id' => $user->id,
            'version' => $user->authenticationVersion(),
        ], session(User::AUTH_VERSION_SESSION_KEY));
        session()->put('protected_marker', 'must be removed');
        session()->save();
        $sessionId = session()->getId();
        $csrfToken = session()->token();

        if ($status === 'inactive') {
            $user->update(['status' => 'inactive']);
        }

        $this->withCookie(config('session.cookie'), $sessionId);
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $response = $this->get('/greenhouses');

        if ($status === 'inactive') {
            $response->assertRedirect(route('login'))
                ->assertSessionHas('error', 'La cuenta se encuentra inactiva.')
                ->assertSessionMissing($sessionKey)
                ->assertSessionMissing(User::AUTH_VERSION_SESSION_KEY)
                ->assertSessionMissing('protected_marker');
            $this->assertGuest('web');
            $this->assertNotSame($sessionId, session()->getId());
            $this->assertNotSame($csrfToken, session()->token());
            $this->assertSame('', session()->getHandler()->read($sessionId));

            return;
        }

        $this->assertAuthenticatedAs($user, 'web');
        $response->assertOk()
            ->assertSee('id="greenhouses-list"', false)
            ->assertSee('id="greenhouse-message"', false)
            ->assertSee('function loadGreenhouses()', false)
            ->assertSee('loadGreenhouses();', false);

        foreach ([
            'id="greenhouse-form"',
            'id="save-button"',
            'class="btn-secondary edit-greenhouse"',
            'class="btn-secondary toggle-greenhouse-status"',
            '.edit-greenhouse',
            '.toggle-greenhouse-status',
            'function startEdit(',
            'function changeGreenhouseStatus(',
        ] as $control) {
            if ($canManage) {
                $response->assertSee($control, false);
            } else {
                $response->assertDontSee($control, false);
            }
        }
    }

    public function test_guest_is_redirected_to_login_from_the_greenhouses_web_page(): void
    {
        $this->get('/greenhouses')->assertRedirect(route('login'));
    }

    private function authenticate(string $role, string $status = 'active'): void
    {
        $user = $this->createUser($role, $status);
        $token = JWTAuth::fromUser($user);
        // Force the next request to resolve the Bearer token through the guard.
        $this->app['auth']->forgetGuards();
        $this->withToken($token);
    }

    private function createUser(string $role, string $status = 'active'): User
    {
        return User::factory()->create([
            'company_id' => $this->company->id,
            'role_id' => DB::table('roles')->where('name', $role)->value('id'),
            'status' => $status,
            'is_company_owner' => $role === 'company_admin',
            'plan_restricted' => false,
        ]);
    }

    private function createCompany(): Company
    {
        return Company::create([
            'name' => 'Test company',
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
        ]);
    }

    private function createGreenhouse(Company $company, string $status = 'active'): Greenhouse
    {
        return Greenhouse::create([
            ...$this->payload(),
            'name' => 'Original greenhouse',
            'company_id' => $company->id,
            'status' => $status,
        ])->refresh();
    }

    private function payload(?string $status = null): array
    {
        return $status !== null ? ['status' => $status] : [
            'name' => 'Updated greenhouse',
            'crop_type' => 'Tomato',
            'area' => 120,
            'location' => 'Test location',
            'planting_date' => '2026-01-01',
            'nominal_flow' => 15,
        ];
    }
}
