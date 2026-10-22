<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Greenhouse;
use App\Models\IrrigationEvent;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Sensor;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionContractService;
use App\Services\SubscriptionRestrictionSyncService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class SubscriptionRestrictionSyncTest extends TestCase
{
    use UsesInMemoryDatabase;

    private function company(): Company
    {
        return Company::create(['name' => 'Restrictions', 'email' => fake()->unique()->safeEmail(), 'status' => 'active']);
    }

    private function user(Company $company, bool $owner = false, string $role = 'employee'): User
    {
        return User::factory()->create(['company_id' => $company->id, 'status' => 'active',
            'role_id' => Role::where('name', $owner ? 'company_admin' : $role)->value('id'),
            'is_company_owner' => $owner, 'plan_restricted' => false]);
    }

    private function resources(Company $company): void
    {
        for ($g = 0; $g < 3; $g++) {
            $greenhouse = Greenhouse::create(['company_id' => $company->id, 'name' => 'House '.$g,
                'crop_type' => 'Tomato', 'area' => 120, 'location' => 'Test', 'planting_date' => '2026-01-01',
                'nominal_flow' => 15, 'status' => 'active']);
            for ($z = 0; $z < 3; $z++) {
                $zone = $greenhouse->zones()->create(['name' => 'Zone '.$z, 'status' => 'active']);
                for ($d = 0; $d < 2; $d++) {
                    $device = $zone->devices()->create(['name' => 'Device', 'device_code' => fake()->unique()->uuid(),
                        'device_type' => 'ESP32', 'connection_type' => 'wifi', 'status' => $d === 0 ? 'active' : 'inactive']);
                    for ($s = 0; $s < 2; $s++) {
                        $device->sensors()->create(['name' => 'Sensor', 'sensor_code' => fake()->unique()->uuid(),
                            'sensor_type' => 'temperature', 'unit' => 'C', 'status' => 'active']);
                    }
                }
            }
        }
    }

    private function pro(Company $company, string $start, string $end, string $key = 'initial'): Subscription
    {
        $plan = Plan::where('slug', 'pro')->firstOrFail();
        $plan->update(['price_monthly' => '199.00', 'max_greenhouses' => 3, 'max_zones_per_greenhouse' => 3,
            'max_sensors_per_zone' => 4, 'max_users' => 8, 'max_additional_admins' => 2]);

        return app(SubscriptionContractService::class)->create($company, $plan, 'monthly', $start, $end, $key);
    }

    private function sync(Company $company, bool $force = false): bool
    {
        return app(SubscriptionRestrictionSyncService::class)->sync($company, $force);
    }

    private function snapshot(array $tables): array
    {
        return collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy($table === 'subscription_restriction_states' ? 'company_id' : 'id')->get()->toJson()])->all();
    }

    public function test_expiration_returns_to_free_and_preserves_history_and_resources(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 12:00:00'));
        $company = $this->company();
        $this->resources($company);
        $owner = $this->user($company, true);
        $employee = $this->user($company);
        $subscription = $this->pro($company, now()->toDateTimeString(), now()->addMonth()->toDateTimeString());
        $history = $this->snapshot(['subscriptions', 'subscription_contracts']);
        $counts = collect(['greenhouses', 'zones', 'devices', 'sensors', 'users'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);
        $this->assertTrue($this->sync($company));
        $this->travelTo($subscription->ends_at->copy()->subSecond());
        $this->assertFalse($this->sync($company));
        $this->travelTo($subscription->ends_at);
        $this->assertTrue($this->sync($company));
        $this->assertSame('free', $company->effectivePlan()->slug);
        $this->assertSame(1, $company->greenhouses()->where('plan_restricted', true)->count());
        $this->assertSame(5, DB::table('zones')->where('plan_restricted', true)->count());
        $this->assertSame(24, DB::table('sensors')->where('plan_restricted', true)->count());
        $this->assertFalse($owner->fresh()->plan_restricted);
        $this->assertFalse($employee->fresh()->plan_restricted);
        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertSame($history, $this->snapshot(['subscriptions', 'subscription_contracts']));
    }

    public function test_future_renewal_starts_at_boundary_and_uses_frozen_limits(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01'));
        $company = $this->company();
        $this->resources($company);
        $first = $this->pro($company, now()->toDateTimeString(), now()->addMonth()->toDateTimeString());
        $next = $this->pro($company, $first->ends_at->toDateTimeString(), $first->ends_at->copy()->addMonth()->toDateTimeString(), 'next');
        $this->sync($company);
        $fingerprint = DB::table('subscription_restriction_states')->value('fingerprint');
        Plan::where('slug', 'pro')->firstOrFail()->update(['max_greenhouses' => 1, 'max_zones_per_greenhouse' => 1, 'max_sensors_per_zone' => 1]);
        $this->assertFalse($this->sync($company));
        $this->travelTo($next->starts_at);
        $this->assertTrue($this->sync($company));
        $this->assertNotSame($fingerprint, DB::table('subscription_restriction_states')->value('fingerprint'));
        $this->assertSame($next->id, $company->activeSubscription()->first()->id);
        $this->assertSame(0, DB::table('greenhouses')->where('plan_restricted', true)->count());
        $this->assertSame(0, DB::table('sensors')->where('plan_restricted', true)->count());
    }

    public function test_delayed_renewal_recovers_allowed_resources_without_changing_inactive_devices(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01'));
        $company = $this->company();
        $this->resources($company);
        $this->sync($company);
        $this->assertSame(24, DB::table('sensors')->where('plan_restricted', true)->count());
        $future = $this->pro($company, now()->addDay()->toDateTimeString(), now()->addMonth()->toDateTimeString());
        $this->assertFalse($this->sync($company));
        $this->travelTo($future->starts_at);
        $this->assertTrue($this->sync($company));
        foreach (['greenhouses', 'zones', 'sensors'] as $table) {
            $this->assertSame(0, DB::table($table)->where('plan_restricted', true)->count());
        }
        $this->assertSame(9, DB::table('devices')->where('status', 'inactive')->count());
    }

    public function test_all_owners_keep_access_and_employee_slots_match_dynamic_checks(): void
    {
        $company = $this->company();
        $owners = [$this->user($company, true), $this->user($company, true)];
        $admin = $this->user($company, false, 'company_admin');
        $employees = [$this->user($company), $this->user($company), $this->user($company)];
        $this->sync($company);
        foreach ($owners as $owner) {
            $this->assertFalse($owner->fresh()->plan_restricted);
            $this->assertFalse($company->isUserRestrictedByPlan($owner));
        }
        $this->assertTrue($admin->fresh()->plan_restricted);
        foreach ($employees as $index => $employee) {
            $this->assertSame($index > 0, $employee->fresh()->plan_restricted);
            $this->assertSame($index > 0, $company->isUserRestrictedByPlan($employee));
        }
    }

    public function test_sensors_share_zone_limit_across_active_and_inactive_devices(): void
    {
        $company = $this->company();
        $this->resources($company);
        $this->sync($company);
        $zone = $company->greenhouses()->orderBy('id')->first()->zones()->orderBy('id')->first();
        $sensors = Sensor::whereHas('device', fn ($q) => $q->where('zone_id', $zone->id))->orderBy('id')->get();
        $this->assertSame([false, false, false, true], $sensors->pluck('plan_restricted')->all());
        $this->assertSame('inactive', $sensors->last()->device->status);
    }

    public function test_unchanged_fingerprint_performs_no_writes_and_force_repairs_flags(): void
    {
        $company = $this->company();
        $this->resources($company);
        $this->sync($company);
        DB::enableQueryLog();
        $this->assertFalse($this->sync($company));
        $writes = collect(DB::getQueryLog())->filter(fn ($query) => preg_match('/^\s*(update|insert|delete|replace)/i', $query['query']));
        DB::disableQueryLog();
        $this->assertCount(0, $writes);
        DB::table('greenhouses')->update(['plan_restricted' => false]);
        $this->assertTrue($this->sync($company, true));
        $this->assertSame(1, $company->greenhouses()->where('plan_restricted', true)->count());
    }

    public function test_isolation_and_command_batching(): void
    {
        $first = $this->company();
        $second = $this->company();
        $this->resources($first);
        $this->resources($second);
        $this->sync($first);
        $this->assertSame(0, $second->greenhouses()->where('plan_restricted', true)->count());
        $this->assertDatabaseCount('subscription_restriction_states', 1);
        $this->artisan('subscriptions:sync-restrictions', ['--batch' => 1])->expectsOutput('Synchronized: 1; unchanged: 1; failed: 0.')->assertExitCode(0);
        $this->assertSame(1, $second->greenhouses()->where('plan_restricted', true)->count());
    }

    public static function failureTargets(): array
    {
        return [['users'], ['subscription_restriction_states']];
    }

    #[DataProvider('failureTargets')]
    public function test_partial_failure_preserves_previous_flags_and_fingerprint(string $table): void
    {
        $this->travelTo(Carbon::parse('2026-10-01'));
        $company = $this->company();
        $this->resources($company);
        $this->user($company, true);
        $subscription = $this->pro($company, now()->toDateTimeString(), now()->addMonth()->toDateTimeString());
        $this->sync($company);
        $before = $this->snapshot(['greenhouses', 'zones', 'sensors', 'users', 'subscription_restriction_states']);
        $this->travelTo($subscription->ends_at);
        DB::unprepared("CREATE TRIGGER reject_sync BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, 'Simulated synchronization failure'); END");
        try {
            $this->sync($company);
            $this->fail('Expected synchronization failure.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Simulated synchronization failure', $exception->getMessage());
        }
        $this->assertSame($before, $this->snapshot(array_keys($before)));
        DB::unprepared('DROP TRIGGER reject_sync');
        $this->assertTrue($this->sync($company));
    }

    public function test_command_continues_after_company_failure_and_does_not_mark_it_successful(): void
    {
        $bad = $this->company();
        $good = $this->company();
        $this->user($bad, true);
        $this->user($good, true);
        DB::unprepared("CREATE TRIGGER reject_one_company BEFORE UPDATE ON users WHEN NEW.company_id = {$bad->id} BEGIN SELECT RAISE(ABORT, 'Company failure'); END");
        $this->artisan('subscriptions:sync-restrictions', ['--batch' => 1])->expectsOutputToContain('failed: 1')->assertExitCode(1);
        $this->assertDatabaseMissing('subscription_restriction_states', ['company_id' => $bad->id]);
        $this->assertDatabaseHas('subscription_restriction_states', ['company_id' => $good->id]);
    }

    public function test_existing_controller_entry_points_share_transaction_and_resolve_plan_once(): void
    {
        $company = $this->company();
        $this->resources($company);
        DB::enableQueryLog();
        $company->withPlanRestrictionLock(function (?Plan $plan) use ($company) {
            $this->assertSame(1, DB::transactionLevel());
            $this->assertSame('free', $plan->slug);
            $company->syncSensorPlanRestrictions();
            $company->syncUserPlanRestrictions();
            $this->assertSame(1, DB::transactionLevel());
        });
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();
        $this->assertCount(1, $queries->filter(fn ($sql) => str_starts_with($sql, 'select * from "plans"')));
        // One clear and one restriction update; eager reads of parents are expected.
        $this->assertCount(2, $queries->filter(fn ($sql) => str_starts_with($sql, 'update "greenhouses"')));
        $this->assertDatabaseCount('subscription_restriction_states', 0);
        $company->syncUserPlanRestrictions();
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_dynamic_user_access_does_not_depend_on_scheduler(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01'));
        $company = $this->company();
        $owner = $this->user($company, true);
        $this->user($company);
        $this->user($company);
        $employee = $this->user($company);
        $subscription = $this->pro($company, now()->toDateTimeString(), now()->addMonth()->toDateTimeString());
        $this->sync($company);
        $this->travelTo($subscription->ends_at);
        $this->assertFalse($employee->fresh()->plan_restricted);
        $this->assertTrue($company->isUserRestrictedByPlan($employee));
        $this->assertFalse($company->isUserRestrictedByPlan($owner));
    }

    public function test_started_automatic_irrigation_still_completes_after_restriction(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01'));
        $company = $this->company();
        $this->resources($company);
        $zone = $company->greenhouses()->orderByDesc('id')->first()->zones()->first();
        $owner = $this->user($company, true);
        $event = IrrigationEvent::create(['zone_id' => $zone->id, 'user_id' => $owner->id, 'mode' => 'automatic',
            'status' => 'started', 'duration_minutes' => 5, 'started_at' => now()->subMinutes(10)]);
        $this->sync($company);
        $this->assertTrue($zone->fresh()->plan_restricted);
        $this->artisan('irrigation:process-automatic')->assertExitCode(0);
        $this->assertSame('completed', $event->fresh()->status);
        $this->assertTrue($event->fresh()->ended_at->equalTo($event->started_at->copy()->addMinutes(5)));
    }

    public function test_schedule_has_separate_minutely_events_and_overlap_locks(): void
    {
        $events = collect(app(Schedule::class)->events());
        $sync = $events->first(fn ($event) => str_contains($event->command ?? '', 'subscriptions:sync-restrictions'));
        $irrigation = $events->first(fn ($event) => str_contains($event->command ?? '', 'irrigation:process-automatic'));
        $this->assertNotNull($sync);
        $this->assertNotNull($irrigation);
        $this->assertSame('* * * * *', $sync->expression);
        $this->assertTrue($sync->withoutOverlapping);
        $this->assertTrue($sync->runInBackground);
        $this->assertSame('* * * * *', $irrigation->expression);
        $this->assertTrue($irrigation->withoutOverlapping);
        $this->assertNotSame($sync->mutexName(), $irrigation->mutexName());
    }

    public function test_missing_effective_plan_fails_without_recording_success(): void
    {
        $company = $this->company();
        Plan::where('slug', 'free')->firstOrFail()->update(['is_active' => false]);
        $this->artisan('subscriptions:sync-restrictions')->expectsOutputToContain('No effective plan')->assertExitCode(1);
        $this->assertDatabaseCount('subscription_restriction_states', 0);
    }
}
