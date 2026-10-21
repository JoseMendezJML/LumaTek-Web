<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Company;
use App\Models\Greenhouse;
use App\Models\IrrigationEvent;
use App\Models\Plan;
use App\Models\User;
use App\Models\ZoneIrrigationSetting;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class IrrigationAlertPermissionsTest extends TestCase
{
    use UsesInMemoryDatabase;

    public static function permissions(): array
    {
        $cases = [];
        foreach (['employee', 'company_admin', 'superadmin', 'guest'] as $role) {
            foreach (['active', 'inactive'] as $state) {
                foreach ([false, true] as $foreign) {
                    foreach (['list', 'show', 'setting', 'start', 'start-all', 'complete', 'cancel', 'complete-auto', 'cancel-auto', 'alerts', 'acknowledge', 'resolve', 'configure', 'configure-all'] as $operation) {
                        $cases[$role.' '.$state.' '.($foreign ? 'foreign' : 'own').' '.$operation] = [$role, $state, $foreign, $operation];
                    }
                }
            }
        }

        return $cases;
    }

    #[DataProvider('permissions')]
    public function test_permissions(string $role, string $state, bool $foreign, string $operation): void
    {
        $company = $this->company();
        $resources = $this->resources($foreign ? $this->company() : $company);
        extract($resources);
        if (str_ends_with($operation, '-auto')) {
            $event->update(['mode' => 'automatic']);
        }
        if (str_starts_with($operation, 'start')) {
            $event->update(['status' => 'completed']);
        }
        $before = $this->snapshot();
        if ($role !== 'guest') {
            $this->loginApi($this->user($company, $role, $state));
        }
        [$method, $uri, $payload] = $this->requestFor($operation, $resources);
        $response = $this->json($method, $uri, $payload);
        if ($role === 'guest') {
            $response->assertUnauthorized();
        } elseif ($state === 'inactive') {
            $response->assertForbidden();
        } elseif (str_starts_with($operation, 'configure') && $role !== 'company_admin') {
            $response->assertForbidden();
        } elseif ($foreign && ! in_array($operation, ['list', 'alerts'])) {
            $response->assertStatus(in_array($operation, ['acknowledge', 'resolve']) ? 403 : 404);
        } elseif ($role === 'employee' && str_ends_with($operation, '-auto')) {
            $response->assertForbidden();
        } else {
            $response->assertStatus(str_starts_with($operation, 'start') ? 201 : 200);
            if (in_array($operation, ['list', 'alerts'])) {
                $response->assertJsonCount($foreign ? 0 : 1, 'data');
            } elseif (str_starts_with($operation, 'start')) {
                $this->assertDatabaseHas('irrigation_events', ['zone_id' => $zone->id, 'mode' => 'manual', 'status' => 'started']);
            } elseif (str_starts_with($operation, 'complete') || str_starts_with($operation, 'cancel')) {
                $this->assertSame(str_starts_with($operation, 'complete') ? 'completed' : 'cancelled', $event->fresh()->status);
            } elseif (in_array($operation, ['acknowledge', 'resolve'])) {
                $this->assertSame($operation === 'acknowledge' ? 'acknowledged' : 'resolved', $alert->fresh()->status);
                $this->assertNotNull($alert->fresh()->acknowledged_by);
            } elseif (str_starts_with($operation, 'configure')) {
                $this->assertSame(12, $setting->fresh()->duration_minutes);
            }

            return;
        }
        $this->assertSame($before, $this->snapshot());
    }

    public static function resourceStates(): array
    {
        $cases = [];
        foreach (['employee', 'company_admin', 'superadmin'] as $role) {
            foreach (['greenhouse', 'zone'] as $resource) {
                foreach (['inactive', 'restricted'] as $state) {
                    foreach (['start', 'start-all'] as $operation) {
                        $cases[] = [$role, $resource, $state, $operation];
                    }
                }
            }
        }

        return $cases;
    }

    #[DataProvider('resourceStates')]
    public function test_start_rejects_inactive_or_restricted_resources(string $role, string $resource, string $state, string $operation): void
    {
        $company = $this->company();
        $resources = $this->resources($company);
        $resources['event']->update(['status' => 'completed']);
        $this->restrict($resources, $resource, $state);
        $this->loginApi($this->user($company, $role));
        [$method, $uri, $payload] = $this->requestFor($operation, $resources);
        $this->json($method, $uri, $payload)->assertStatus($state === 'restricted' && ! ($resource === 'zone' && $operation === 'start-all') ? 403 : 422);
        $this->assertDatabaseCount('irrigation_events', 1);
    }

    public static function terminalActions(): array
    {
        $cases = [];
        foreach (['employee', 'company_admin', 'superadmin'] as $role) {
            foreach (['manual', 'automatic'] as $mode) {
                foreach (['greenhouse', 'zone'] as $resource) {
                    foreach (['complete', 'cancel'] as $operation) {
                        $cases[] = [$role, $mode, $resource, $operation];
                    }
                }
            }
        }

        return $cases;
    }

    #[DataProvider('terminalActions')]
    public function test_resource_restriction_only_allows_authorized_cancellation(string $role, string $mode, string $resource, string $operation): void
    {
        $company = $this->company();
        $resources = $this->resources($company);
        $resources['event']->update(['mode' => $mode]);
        $this->restrict($resources, $resource, 'restricted');
        $this->loginApi($this->user($company, $role));
        [$method, $uri, $payload] = $this->requestFor($operation, $resources);
        $allowed = $operation === 'cancel' && ! ($role === 'employee' && $mode === 'automatic');
        $this->json($method, $uri, $payload)->assertStatus($allowed ? 200 : 403);
        $this->assertSame($allowed ? 'cancelled' : 'started', $resources['event']->fresh()->status);
    }

    public static function configurationRestrictions(): array
    {
        $cases = [];
        foreach (['greenhouse', 'zone'] as $resource) {
            foreach (['configure', 'configure-all'] as $operation) {
                foreach (['enable', 'disable', 'duration_minutes', 'water_liters', 'cooldown_minutes', 'no-setting'] as $change) {
                    $cases[] = [$resource, $operation, $change];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('configurationRestrictions')]
    public function test_restricted_configuration_only_allows_disabling_without_parameter_changes(string $resource, string $operation, string $change): void
    {
        $company = $this->company();
        $resources = $this->resources($company);
        $this->restrict($resources, $resource, 'restricted');
        $this->loginApi($this->user($company, 'company_admin'));
        [$method, $uri] = $this->requestFor($operation, $resources);
        $payload = $this->configuration(false);
        if ($change === 'enable') {
            $payload['automatic_enabled'] = true;
        } elseif (in_array($change, ['duration_minutes', 'water_liters', 'cooldown_minutes'])) {
            $payload[$change] = 99;
        } elseif ($change === 'no-setting') {
            $resources['setting']->delete();
        }
        $before = DB::table('zone_irrigation_settings')->get()->toJson();
        $response = $this->json($method, $uri, $payload);
        $allowed = $change === 'disable';
        // Bulk activation filters restricted zones instead of configuring them.
        $response->assertStatus($allowed ? 200 : ($resource === 'zone' && $operation === 'configure-all' && $change === 'enable' ? 422 : 403));
        if ($allowed) {
            $this->assertFalse($resources['setting']->fresh()->automatic_enabled);
            $this->assertSame(10, $resources['setting']->fresh()->duration_minutes);
            $this->assertSame('8.00', $resources['setting']->fresh()->water_liters);
            $this->assertSame(60, $resources['setting']->fresh()->cooldown_minutes);
        } else {
            $this->assertSame($before, DB::table('zone_irrigation_settings')->get()->toJson());
        }
    }

    public static function roles(): array
    {
        return [['employee'], ['company_admin'], ['superadmin']];
    }

    #[DataProvider('roles')]
    public function test_user_plan_access_still_blocks_every_operation_including_cancel(string $role): void
    {
        $company = $this->company();
        $resources = $this->resources($company);
        if ($role === 'superadmin') {
            // No plan means access is denied for non-owner roles as well.
            Plan::query()->update(['is_active' => false]);
        } else {
            Plan::where('slug', 'free')->update(['max_users' => 0, 'max_additional_admins' => 0]);
        }
        $this->loginApi($this->user($company, $role, 'active', false));
        $before = $this->snapshot();
        foreach (['list', 'setting', 'start', 'complete', 'cancel', 'alerts', 'acknowledge', 'resolve', 'configure', 'configure-all'] as $operation) {
            [$method, $uri, $payload] = $this->requestFor($operation, $resources);
            $this->json($method, $uri, $payload)->assertForbidden();
        }
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('roles')]
    public function test_web_configuration_is_read_only_for_non_company_administrators(string $role): void
    {
        $user = $this->user($this->company(), $role);
        $response = $this->actingAs($user, 'web')->get('/irrigation')->assertOk();
        foreach (['automatic-greenhouse', 'automatic-zone', 'automatic-duration', 'automatic-threshold', 'irrigation-form'] as $id) {
            $response->assertSee('id="'.$id.'"', false);
        }
        foreach (['id="automatic-irrigation-form"', 'id="automatic-enabled"', 'id="save-automatic-button"', 'id="apply-automatic-all-button"', 'async function saveSetting(', 'async function applySettingToAllZones('] as $control) {
            $role === 'company_admin' ? $response->assertSee($control, false) : $response->assertDontSee($control, false);
        }
        $this->get('/alerts')->assertOk()->assertSee('data-action="acknowledge"', false)->assertSee('data-action="resolve"', false);
    }

    public static function closedEvents(): array
    {
        $cases = [];
        foreach (['employee', 'company_admin', 'superadmin'] as $role) {
            foreach (['completed', 'cancelled', 'failed'] as $state) {
                foreach (['complete', 'cancel'] as $operation) {
                    $cases[] = [$role, $state, $operation];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('closedEvents')]
    public function test_closed_events_cannot_be_changed(string $role, string $state, string $operation): void
    {
        $company = $this->company();
        $resources = $this->resources($company);
        $resources['event']->update(['status' => $state]);
        $this->loginApi($this->user($company, $role));
        $before = $this->snapshot();
        [$method, $uri, $payload] = $this->requestFor($operation, $resources);
        $this->json($method, $uri, $payload)->assertStatus(422);
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('roles')]
    public function test_restricted_resources_can_still_have_their_alerts_attended(string $role): void
    {
        $company = $this->company();
        $resources = $this->resources($company);
        $this->restrict($resources, 'greenhouse', 'restricted');
        $this->loginApi($this->user($company, $role));
        $this->getJson('/api/alerts')->assertOk()->assertJsonCount(1, 'data');
        foreach (['acknowledge', 'resolve'] as $operation) {
            [$method, $uri, $payload] = $this->requestFor($operation, $resources);
            $this->json($method, $uri, $payload)->assertOk();
        }
        $this->assertSame('resolved', $resources['alert']->fresh()->status);
        [$method, $uri, $payload] = $this->requestFor('resolve', $resources);
        $this->json($method, $uri, $payload)->assertStatus(422);
    }

    public function test_bulk_configuration_rolls_back_if_a_restricted_zone_would_change_parameters(): void
    {
        $company = $this->company();
        $resources = $this->resources($company);
        $extra = $resources['greenhouse']->zones()->create(['name' => 'Restricted zone', 'status' => 'active']);
        ZoneIrrigationSetting::create(['zone_id' => $extra->id, ...$this->configuration(true)]);
        Plan::where('slug', 'free')->update(['max_zones_per_greenhouse' => 1]);
        $this->loginApi($this->user($company, 'company_admin'));
        $before = $this->snapshot();
        $this->putJson('/api/greenhouses/'.$resources['greenhouse']->id.'/irrigation-setting', $this->configuration(false, 99))->assertForbidden();
        $this->assertSame($before, $this->snapshot());
    }

    public static function inactiveParents(): array
    {
        return [['greenhouse', 'configure'], ['greenhouse', 'configure-all'], ['zone', 'configure'], ['zone', 'configure-all']];
    }

    #[DataProvider('inactiveParents')]
    public function test_automation_cannot_be_enabled_in_inactive_resources(string $resource, string $operation): void
    {
        $company = $this->company();
        $resources = $this->resources($company);
        $resources[$resource]->update(['status' => 'inactive']);
        $this->loginApi($this->user($company, 'company_admin'));
        $before = $this->snapshot();
        [$method, $uri, $payload] = $this->requestFor($operation, $resources);
        $this->json($method, $uri, $payload)->assertStatus(422);
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('roles')]
    public function test_lists_exclude_other_companies_and_inactive_users_cannot_open_web_pages(string $role): void
    {
        $company = $this->company();
        $own = $this->resources($company);
        $foreign = $this->resources($this->company());
        $user = $this->user($company, $role);
        $this->loginApi($user);
        $this->getJson('/api/irrigation-events')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own['event']->id)->assertJsonMissing(['id' => $foreign['event']->id]);
        $this->getJson('/api/alerts')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own['alert']->id)->assertJsonMissing(['id' => $foreign['alert']->id]);
        $user->update(['status' => 'inactive']);
        foreach (['/irrigation', '/alerts'] as $uri) {
            $this->actingAs($user, 'web')->get($uri)->assertRedirect(route('login'));
        }
    }

    private function requestFor(string $operation, array $resources): array
    {
        extract($resources);

        return match ($operation) {
            'list' => ['GET', '/api/irrigation-events', []],
            'show' => ['GET', '/api/irrigation-events/'.$event->id, []],
            'setting' => ['GET', '/api/zones/'.$zone->id.'/irrigation-setting', []],
            'start' => ['POST', '/api/zones/'.$zone->id.'/irrigation/start', []],
            'start-all' => ['POST', '/api/greenhouses/'.$greenhouse->id.'/irrigation/start', []],
            'complete', 'complete-auto' => ['PATCH', '/api/irrigation-events/'.$event->id.'/complete', []],
            'cancel', 'cancel-auto' => ['PATCH', '/api/irrigation-events/'.$event->id.'/cancel', []],
            'alerts' => ['GET', '/api/alerts', []],
            'acknowledge', 'resolve' => ['PATCH', '/api/alerts/'.$alert->id.'/'.$operation, []],
            'configure' => ['PUT', '/api/zones/'.$zone->id.'/irrigation-setting', $this->configuration(true, 12)],
            'configure-all' => ['PUT', '/api/greenhouses/'.$greenhouse->id.'/irrigation-setting', $this->configuration(true, 12)],
        };
    }

    private function company(): Company
    {
        return Company::create(['name' => 'Test company', 'email' => fake()->unique()->safeEmail(), 'status' => 'active']);
    }

    private function user(Company $company, string $role, string $state = 'active', bool $owner = true): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'role_id' => DB::table('roles')->where('name', $role)->value('id'),
            'status' => $state, 'is_company_owner' => $owner && $role === 'company_admin',
        ]);
    }

    private function loginApi(User $user): void
    {
        $this->withToken(JWTAuth::fromUser($user));
        $this->app['auth']->forgetGuards();
    }

    private function resources(Company $company): array
    {
        $greenhouse = Greenhouse::create(['company_id' => $company->id, 'name' => 'Greenhouse', 'crop_type' => 'Tomato', 'area' => 120, 'location' => 'Test location', 'planting_date' => '2026-01-01', 'nominal_flow' => 15, 'status' => 'active']);
        $greenhouse->thresholds()->create(['variable' => 'soil_humidity', 'min_value' => 30, 'max_value' => 80, 'unit' => '%']);
        $zone = $greenhouse->zones()->create(['name' => 'Zone', 'status' => 'active']);
        $device = $zone->devices()->create(['name' => 'Device', 'device_code' => fake()->unique()->uuid(), 'device_type' => 'ESP32', 'connection_type' => 'wifi', 'status' => 'active']);
        $sensor = $device->sensors()->create(['name' => 'Sensor', 'sensor_code' => fake()->unique()->uuid(), 'sensor_type' => 'soil_humidity', 'unit' => '%', 'status' => 'active']);
        $alert = Alert::create(['sensor_id' => $sensor->id, 'type' => 'low_soil_humidity', 'severity' => 'warning', 'message' => 'Low humidity', 'status' => 'active']);
        $event = IrrigationEvent::create(['zone_id' => $zone->id, 'mode' => 'manual', 'status' => 'started', 'started_at' => now()->subMinutes(2)]);
        $setting = ZoneIrrigationSetting::create(['zone_id' => $zone->id, ...$this->configuration(true)]);

        return compact('greenhouse', 'zone', 'alert', 'event', 'setting');
    }

    private function configuration(bool $enabled, int $duration = 10): array
    {
        return ['automatic_enabled' => $enabled, 'duration_minutes' => $duration, 'water_liters' => 8, 'cooldown_minutes' => 60];
    }

    private function restrict(array $resources, string $resource, string $state): void
    {
        if ($state === 'inactive') {
            $resources[$resource]->update(['status' => 'inactive']);

            return;
        }
        Plan::where('slug', 'free')->update([$resource === 'greenhouse' ? 'max_greenhouses' : 'max_zones_per_greenhouse' => 0]);
        $resources[$resource]->update(['plan_restricted' => true]);
    }

    private function snapshot(): array
    {
        return collect(['irrigation_events', 'zone_irrigation_settings', 'alerts'])->mapWithKeys(
            fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()]
        )->all();
    }
}
