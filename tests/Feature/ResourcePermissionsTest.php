<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Greenhouse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ResourcePermissionsTest extends TestCase
{
    use UsesInMemoryDatabase;

    public static function accessCases(): array
    {
        $cases = [];
        foreach (['employee', 'company_admin', 'superadmin', 'guest'] as $role) {
            foreach (['active', 'inactive'] as $state) {
                foreach (['own', 'foreign'] as $company) {
                    foreach (['zone', 'device', 'sensor'] as $resource) {
                        foreach (['read', 'create', 'edit', 'activate', 'deactivate'] as $operation) {
                            $cases[implode(' ', [$role, $state, $company, $resource, $operation])] = [$role, $state, $company, $resource, $operation];
                        }
                    }
                }
            }
        }

        return $cases;
    }

    #[DataProvider('accessCases')]
    public function test_resource_access(string $role, string $state, string $companyScope, string $resource, string $operation): void
    {
        $company = $this->company();
        $targetCompany = $companyScope === 'own' ? $company : $this->company();
        [$greenhouse, $zone, $device, $sensor] = $this->resources($targetCompany);
        if ($operation === 'activate') {
            ${$resource}->update(['status' => 'inactive']);
        }
        if ($resource === 'sensor' && $operation === 'create') {
            $sensor->update(['pin' => null]);
        }
        $before = $this->snapshot();
        if ($role !== 'guest') {
            $user = $this->user($company, $role, $state);
            $this->withToken(JWTAuth::fromUser($user));
            $this->app['auth']->forgetGuards();
        }
        $collection = match ($resource) {
            'zone' => '/api/greenhouses/'.$greenhouse->id.'/zones',
            'device' => '/api/zones/'.$zone->id.'/devices',
            'sensor' => '/api/devices/'.$device->id.'/sensors',
        };
        $uri = match ($operation) {
            'read', 'create' => $collection,
            'edit' => '/api/'.$resource.'s/'.${$resource}->id,
            default => '/api/'.$resource.'s/'.${$resource}->id.'/status',
        };
        $method = match ($operation) {
            'read' => 'GET', 'create' => 'POST', 'edit' => 'PUT', default => 'PATCH',
        };
        $payload = match ($operation) {
            'activate' => ['status' => 'active'],
            'deactivate' => ['status' => 'inactive'],
            default => $this->payload($resource),
        };
        $response = $this->json($method, $uri, $payload);
        $allowed = $role !== 'guest' && $state === 'active' && $companyScope === 'own'
            && ($operation === 'read' || $role === 'company_admin');
        if (! $allowed) {
            $response->assertStatus($role === 'guest' ? 401 : 403);
            $this->assertSame($before, $this->snapshot());

            return;
        }
        $response->assertStatus($operation === 'create' ? 201 : 200);
        if ($operation === 'read') {
            $response->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', ${$resource}->id);
        } else {
            $this->assertDatabaseHas($resource.'s', [
                'id' => $response->json('data.id'),
                ...($operation === 'create' || $operation === 'edit' ? ['name' => 'Updated resource'] : $payload),
            ]);
        }
    }

    public static function readers(): array
    {
        return [['employee'], ['company_admin']];
    }

    #[DataProvider('readers')]
    public function test_lists_exclude_other_company_resources(string $role): void
    {
        $company = $this->company();
        [$greenhouse, $zone, $device, $sensor] = $this->resources($company);
        [, $foreignZone, $foreignDevice, $foreignSensor] = $this->resources($this->company());
        $this->withToken(JWTAuth::fromUser($this->user($company, $role, 'active')));
        $this->app['auth']->forgetGuards();
        foreach ([
            ['/api/greenhouses/'.$greenhouse->id.'/zones', $zone, $foreignZone],
            ['/api/zones/'.$zone->id.'/devices', $device, $foreignDevice],
            ['/api/devices/'.$device->id.'/sensors', $sensor, $foreignSensor],
        ] as [$uri, $own, $foreign]) {
            $this->getJson($uri)->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $own->id)->assertJsonMissing(['id' => $foreign->id]);
        }
    }

    public static function webRoles(): array
    {
        return [
            ['employee', 'active', false], ['company_admin', 'active', true],
            ['superadmin', 'active', false], ['employee', 'inactive', false],
            ['company_admin', 'inactive', false],
        ];
    }

    #[DataProvider('webRoles')]
    public function test_web_controls(string $role, string $state, bool $canManage): void
    {
        $user = $this->user($this->company(), $role, $state);
        $this->actingAs($user, 'web');
        $response = $this->get('/zones');
        if ($state === 'inactive') {
            $response->assertRedirect(route('login'));

            return;
        }
        $response->assertOk();
        foreach (['zones-list', 'devices-list', 'sensors-list', 'zone-message'] as $id) {
            $response->assertSee('id="'.$id.'"', false);
        }
        foreach (['zone', 'device', 'sensor'] as $kind) {
            foreach (['id="'.$kind.'-form"', 'class="action-button edit-'.$kind.'"', 'class="action-button action-warning status-'.$kind.'"'] as $control) {
                $canManage ? $response->assertSee($control, false) : $response->assertDontSee($control, false);
            }
        }
    }

    public function test_employee_can_read_sensor_monitoring(): void
    {
        $company = $this->company();
        [, , , $sensor] = $this->resources($company);
        $sensor->readings()->create(['value' => 23, 'recorded_at' => now()]);
        $this->withToken(JWTAuth::fromUser($this->user($company, 'employee', 'active')));
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/devices/'.$sensor->device_id.'/sensors')->assertOk()
            ->assertJsonPath('data.0.latest_reading.sensor_id', $sensor->id);
    }

    public static function planLimits(): array
    {
        return [['zone', 'create'], ['zone', 'activate'], ['sensor', 'create'], ['sensor', 'activate']];
    }

    #[DataProvider('planLimits')]
    public function test_administrator_cannot_exceed_plan_limits(string $resource, string $operation): void
    {
        $company = $this->company();
        [$greenhouse, $zone, $device, $sensor] = $this->resources($company);
        if ($resource === 'zone') {
            $greenhouse->zones()->create(['name' => 'Second zone', 'status' => 'active']);
            $target = $greenhouse->zones()->create(['name' => 'Inactive zone', 'status' => 'inactive']);
            $uri = $operation === 'create' ? '/api/greenhouses/'.$greenhouse->id.'/zones' : '/api/zones/'.$target->id.'/status';
        } else {
            for ($i = 0; $i < 2; $i++) {
                $device->sensors()->create([...$this->payload('sensor'), 'sensor_code' => 'EXTRA-'.$i, 'unit' => 'C', 'status' => 'active']);
            }
            $target = $device->sensors()->create([...$this->payload('sensor'), 'sensor_code' => 'INACTIVE', 'unit' => 'C', 'status' => 'inactive']);
            $uri = $operation === 'create' ? '/api/devices/'.$device->id.'/sensors' : '/api/sensors/'.$target->id.'/status';
        }
        $this->withToken(JWTAuth::fromUser($this->user($company, 'company_admin', 'active')));
        $this->app['auth']->forgetGuards();
        $count = DB::table($resource.'s')->count();
        $this->json($operation === 'create' ? 'POST' : 'PATCH', $uri,
            $operation === 'create' ? $this->payload($resource) : ['status' => 'active']
        )->assertStatus(422);
        $this->assertDatabaseCount($resource.'s', $count);
        $this->assertSame('inactive', $target->fresh()->status);
    }

    private function company(): Company
    {
        return Company::create(['name' => 'Test company', 'email' => fake()->unique()->safeEmail(), 'status' => 'active']);
    }

    private function user(Company $company, string $role, string $state): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'role_id' => DB::table('roles')->where('name', $role)->value('id'),
            'status' => $state, 'is_company_owner' => $role === 'company_admin', 'plan_restricted' => false,
        ]);
    }

    private function resources(Company $company): array
    {
        $greenhouse = Greenhouse::create(['company_id' => $company->id, 'name' => 'Greenhouse', 'crop_type' => 'Tomato', 'area' => 120, 'location' => 'Test location', 'planting_date' => '2026-01-01', 'nominal_flow' => 15, 'status' => 'active']);
        $zone = $greenhouse->zones()->create(['name' => 'Zone', 'status' => 'active']);
        $device = $zone->devices()->create([...$this->payload('device'), 'device_code' => fake()->unique()->uuid(), 'status' => 'active']);
        $sensor = $device->sensors()->create([...$this->payload('sensor'), 'sensor_code' => fake()->unique()->uuid(), 'unit' => 'C', 'status' => 'active']);

        return [$greenhouse, $zone, $device, $sensor];
    }

    private function payload(string $resource): array
    {
        return match ($resource) {
            'zone' => ['name' => 'Updated resource', 'position_x' => 20, 'position_y' => 30],
            'device' => ['name' => 'Updated resource', 'device_code' => 'NEW-DEVICE', 'device_type' => 'ESP32', 'connection_type' => 'wifi'],
            'sensor' => ['name' => 'Updated resource', 'sensor_code' => 'NEW-SENSOR', 'sensor_type' => 'temperature', 'model' => 'DHT22', 'pin' => 4, 'position_x' => 20, 'position_y' => 30],
        };
    }

    private function snapshot(): array
    {
        return collect(['greenhouses', 'zones', 'devices', 'sensors'])->mapWithKeys(
            fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()]
        )->all();
    }
}
