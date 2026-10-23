<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class IotPairingRetentionTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase;

    private function challenge(array $attributes = []): string
    {
        $id = (string) Str::uuid();
        DB::table('iot_pairings')->insert(array_replace([
            'id' => $id, 'proof_hash' => hash('sha256', 'proof'), 'exchange_hash' => hash('sha256', 'exchange'),
            'expires_at' => now()->subDays(8), 'consumed_at' => null,
            'created_at' => now()->subDays(9), 'updated_at' => now()->subDays(9),
        ], $attributes));

        return $id;
    }

    public function test_retention_preserves_recent_terminal_and_pending_challenges_and_device_identity(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $device = $this->resources($this->company())['device'];
        $device->forceFill(['paired_at' => now()->subDays(8), 'credential_hash' => hash('sha256', 'permanent')])->save();
        $before = $device->fresh()->getAttributes();
        $expired = $this->challenge();
        $consumed = $this->challenge(['device_id' => $device->id, 'consumed_at' => now()->subDays(8)]);
        $keep = [
            $this->challenge(['expires_at' => now()->addMinutes(10)]),
            $this->challenge(['expires_at' => now()->subDays(6)]),
            $this->challenge(['consumed_at' => now()->subDays(6)]),
            $this->challenge(['expires_at' => now()->subDays(7)]),
            $this->challenge(['consumed_at' => now()->subDays(7)]),
            // Revocation can consume an old expired challenge just now: keep its audit data.
            $this->challenge(['consumed_at' => now(), 'device_id' => $device->id]),
        ];
        $this->artisan('iot:prune-pairings')->expectsOutput('Deleted pairing challenges: 2')->assertSuccessful();
        $this->assertDatabaseMissing('iot_pairings', ['id' => $expired]);
        $this->assertDatabaseMissing('iot_pairings', ['id' => $consumed]);
        foreach ($keep as $id) {
            $this->assertDatabaseHas('iot_pairings', ['id' => $id]);
        }
        $this->assertSame($before, $device->fresh()->getAttributes());
        $this->artisan('iot:prune-pairings')->expectsOutput('Deleted pairing challenges: 0')->assertSuccessful();
    }

    public function test_cleanup_is_bounded_and_eventually_drains_old_challenges(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->challenge();
        }
        $this->artisan('iot:prune-pairings', ['--batch-size' => 2, '--batches' => 2])
            ->expectsOutput('Deleted pairing challenges: 4')->assertSuccessful();
        $this->assertDatabaseCount('iot_pairings', 1);
        $this->artisan('iot:prune-pairings')->assertSuccessful();
        $this->assertDatabaseCount('iot_pairings', 0);
    }

    public function test_pending_approval_can_still_exchange_after_cleanup(): void
    {
        $company = $this->company();
        $device = $this->resources($company)['device'];
        $this->loginApi($this->user($company));
        $challenge = $this->postJson('/api/iot/pairings')->assertCreated()->json();
        $this->postJson('/api/devices/'.$device->id.'/pairing', [
            'pairing_id' => $challenge['pairing_id'], 'operator_proof' => $challenge['operator_proof'],
        ])->assertOk();
        $this->challenge();
        $this->artisan('iot:prune-pairings')->expectsOutput('Deleted pairing challenges: 1')->assertSuccessful();
        $this->postJson('/api/iot/pairings/exchange', $challenge)->assertOk();
        $this->assertNotNull($device->fresh()->paired_at);
    }

    public function test_invalid_retention_and_batch_limits_do_not_delete_anything(): void
    {
        $this->challenge();
        foreach ([['--batch-size' => 0], ['--batch-size' => 1001], ['--batches' => 0], ['--batches' => 101], ['--batch-size' => 'invalid']] as $options) {
            $this->artisan('iot:prune-pairings', $options)->assertFailed();
        }
        config(['iot_pairings.retention_days' => 0]);
        $this->artisan('iot:prune-pairings')->assertFailed();
        $this->assertDatabaseCount('iot_pairings', 1);
    }

    public function test_persistent_database_requires_explicit_approval_without_opening_a_connection(): void
    {
        config(['database.connections.blocked' => ['driver' => 'sqlite', 'database' => '/must-not-be-opened.sqlite'], 'database.default' => 'blocked']);
        $this->artisan('iot:prune-pairings')
            ->expectsOutput('Persistent database deletion requires target approval and --allow-persistent.')->assertFailed();
        $this->assertInstanceOf(\Closure::class, DB::connection()->getRawPdo());
    }

    public function test_cleanup_schedule_is_disabled_by_default_and_hourly_when_enabled(): void
    {
        $this->assertFalse(config('iot_pairings.schedule_enabled'));
        $events = collect(Schedule::events())->filter(fn ($event) => str_contains($event->command ?? '', 'iot:prune-pairings'));
        $this->assertCount(0, $events);
        config(['iot_pairings.schedule_enabled' => true]);
        require base_path('routes/console.php');
        $events = collect(Schedule::events())->filter(fn ($event) => str_contains($event->command ?? '', 'iot:prune-pairings'));
        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }
}
