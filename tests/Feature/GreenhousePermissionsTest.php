<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Greenhouse;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class GreenhousePermissionsTest extends TestCase
{
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Fail before any connection or migration if PHPUnit isolation is absent.
        $this->assertTrue($this->app->environment('testing'));
        $this->assertFalse($this->app->configurationIsCached());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));

        // Replace the connection definition so inherited read/write settings cannot
        // redirect any query. All overrides live only in this test application.
        config([
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'url' => null,
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array',
            'session.driver' => 'array',
            'mail.default' => 'array',
            'queue.default' => 'sync',
            'logging.default' => 'test_null',
            'logging.channels.test_null' => [
                'driver' => 'monolog',
                'handler' => \Monolog\Handler\NullHandler::class,
            ],
            'app.key' => 'base64:'.base64_encode(str_repeat('t', 32)),
            'jwt.secret' => str_repeat('test-only-jwt-secret-', 4),
            'jwt.algo' => 'HS256',
        ]);

        DB::purge('sqlite');
        $connection = DB::connection('sqlite');
        $this->assertSame('sqlite', $connection->getDriverName());
        $this->assertSame(':memory:', $connection->getDatabaseName());
        $databases = $connection->select('PRAGMA database_list');
        $this->assertSame('main', $databases[0]->name);
        $this->assertSame('', $databases[0]->file);

        // A new in-memory database per test; no RefreshDatabase/migrate:fresh.
        $this->artisan('migrate', ['--database' => 'sqlite'])->assertExitCode(0);
        $this->seed(DatabaseSeeder::class);
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

    #[DataProvider('mutations')]
    public function test_employee_cannot_mutate_greenhouses(string $method, string $uri, ?string $status): void
    {
        $greenhouse = $this->createGreenhouse($this->company, $status === 'active' ? 'inactive' : 'active');
        $before = $greenhouse->getRawOriginal();
        $this->authenticate('employee');

        $this->json($method, sprintf($uri, $greenhouse->id), $this->payload($status))
            ->assertForbidden();

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

    private function authenticate(string $role): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'role_id' => DB::table('roles')->where('name', $role)->value('id'),
            'status' => 'active',
            'is_company_owner' => $role === 'company_admin',
            'plan_restricted' => false,
        ]);

        $token = JWTAuth::fromUser($user);
        // Force the next request to resolve the Bearer token through the guard.
        $this->app['auth']->forgetGuards();
        $this->withToken($token);
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
