<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Reading;
use App\Services\SubscriptionContractService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class ReadingSecurityAndEpisodesTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase;

    private array $r;

    private string $credential;

    private function fixture(): void
    {
        $this->travelTo('2026-10-22 12:00:00');
        $company = $this->company();
        $this->r = $this->resources($company) + compact('company');
        $this->r['soil'] = $this->r['sensor'];
        $this->r['soil']->update(['model' => 'analog_soil', 'pin' => 34, 'calibration' => ['dry' => 3500, 'wet' => 1000]]);
        foreach (['temperature' => ['°C', 10, 30], 'ambient' => ['%', 20, 70]] as $key => [$unit, $min, $max]) {
            $type = $key === 'ambient' ? 'ambient_humidity' : $key;
            $this->r[$key] = $this->r['device']->sensors()->create(['name' => $key, 'sensor_code' => (string) Str::uuid(),
                'sensor_type' => $type, 'model' => 'DHT22', 'pin' => 4, 'unit' => $unit, 'status' => 'active']);
            $this->r['greenhouse']->thresholds()->create(['variable' => $type, 'unit' => $unit, 'min_value' => $min, 'max_value' => $max]);
        }
        $this->credential = bin2hex(random_bytes(32));
        $this->r['device']->forceFill(['credential_hash' => hash('sha256', $this->credential), 'paired_at' => now()])->save();
        $this->r['operator'] = $this->user($company);
    }

    private function operator(): void
    {
        app('tymon.jwt')->unsetToken();
        app('tymon.jwt.auth')->unsetToken();
        $this->loginApi($this->r['operator']);
    }

    private function uri(string $key): string
    {
        return '/api/sensors/'.$this->r[$key]->id.'/'.['temperature' => 'temperature', 'ambient' => 'ambient-humidity', 'soil' => 'soil-moisture'][$key];
    }

    private function payload(array $values): array
    {
        $items = [];
        foreach ($values as $key => $value) {
            $sensor = $this->r[$key]->fresh();
            $items[] = ['sensor_id' => $sensor->id, 'measurement' => $sensor->sensor_type, 'unit' => $sensor->unit, 'value' => $value];
        }

        return ['batch_id' => (string) Str::uuid(), 'readings' => $items];
    }

    private function physical(array $values): void
    {
        $this->travel(61)->seconds();
        $this->withToken($this->credential)->postJson('/api/iot/readings', $this->payload($values))->assertCreated();
    }

    public static function inactiveCases(): array
    {
        $cases = [];
        foreach (['temperature', 'ambient', 'soil'] as $metric) {
            foreach (['sensor', 'device', 'zone', 'greenhouse'] as $resource) {
                $cases[$metric.' '.$resource] = [$metric, $resource];
            }
        }

        return $cases;
    }

    #[DataProvider('inactiveCases')]
    public function test_legacy_receivers_reject_inactive_hierarchy_without_writes(string $key, string $resource): void
    {
        $this->fixture();
        ($resource === 'sensor' ? $this->r[$key] : $this->r[$resource])->update(['status' => 'inactive']);
        $this->operator();
        $before = $this->snapshot();
        $this->postJson($this->uri($key), ['value' => 25])->assertStatus(422);
        $this->assertSame($before, $this->snapshot());
    }

    private function snapshot(): array
    {
        return collect(['readings', 'alerts', 'irrigation_events', 'devices'])->mapWithKeys(
            fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]
        )->all();
    }

    public static function metrics(): array
    {
        return [['temperature'], ['ambient'], ['soil']];
    }

    #[DataProvider('metrics')]
    public function test_manual_capture_is_legitimate_but_cannot_claim_physical_provenance(string $key): void
    {
        $this->fixture();
        $this->operator();
        $before = $this->snapshot();
        $this->postJson($this->uri($key), ['value' => 25, 'source' => 'iot'])->assertStatus(422);
        $this->postJson($this->uri($key), ['value' => 25, 'iot_reading_batch_id' => 1])->assertStatus(422);
        $this->assertSame($before, $this->snapshot());
        $this->postJson($this->uri($key), ['value' => 50, 'source' => 'simulation'])->assertCreated();
        $reading = $this->r[$key]->readings()->latest('id')->first();
        $this->assertSame('simulation', $reading->source);
        $this->assertSame('manual_or_simulated', $reading->provenance);
        $this->assertFalse($reading->is_physical);
        $this->assertNull($this->r['device']->fresh()->last_connection_at);
        $this->assertNull($this->r['device']->fresh()->last_reading_at);
        $this->getJson($this->uri($key).'/current')->assertOk()->assertJsonPath('data.connection_status', 'simulated');
    }

    #[DataProvider('metrics')]
    public function test_one_episode_survives_acknowledgement_and_closes_on_normalization(string $key): void
    {
        $this->fixture();
        $bad = $key === 'soil' ? 10 : 85;
        if ($key === 'temperature') {
            $bad = 35;
        }
        $normal = $key === 'temperature' ? 20 : 50;
        $alerts = fn () => $this->r[$key]->alerts()->whereHas('reading', fn ($q) => $q->whereNotNull('iot_reading_batch_id'));
        $this->physical([$key => $bad]);
        $first = $alerts()->firstOrFail();
        $this->physical([$key => $bad]);
        $this->assertSame(1, $alerts()->count());
        $this->operator();
        $this->patchJson('/api/alerts/'.$first->id.'/acknowledge')->assertOk();
        $this->physical([$key => $bad]);
        $this->assertSame(1, $alerts()->count());
        $this->assertSame('acknowledged', $first->fresh()->status);
        $this->physical([$key => $normal]);
        $this->assertSame('resolved', $first->fresh()->status);
        $this->assertNotNull($first->fresh()->acknowledged_by);
        $this->physical([$key => $bad]);
        $this->assertSame(2, $alerts()->count());
        $second = $alerts()->latest('id')->first();
        $this->operator();
        $this->patchJson('/api/alerts/'.$second->id.'/resolve')->assertOk();
        $this->physical([$key => $bad]);
        $this->assertSame(2, $alerts()->count());
        $this->assertSame('resolved', $second->fresh()->status);
        $this->physical([$key => $normal]);
        $this->physical([$key => $bad]);
        $this->assertSame(3, $alerts()->count());
    }

    public function test_opposite_temperature_episode_closes_and_simulation_cannot_close_physical_alerts(): void
    {
        $this->fixture();
        $this->physical(['temperature' => 5]);
        $this->operator();
        $this->postJson($this->uri('temperature'), ['value' => 20])->assertCreated();
        $low = $this->r['temperature']->alerts()->where('type', 'low_temperature')->firstOrFail();
        $this->assertSame('active', $low->fresh()->status);
        $this->physical(['temperature' => 35]);
        $this->assertSame('resolved', $low->fresh()->status);
        $this->assertSame(1, $this->r['temperature']->alerts()->where('status', 'active')->count());
    }

    #[DataProvider('metrics')]
    public function test_manual_episodes_are_also_deduplicated(string $key): void
    {
        $this->fixture();
        $this->operator();
        $bad = $key === 'soil' ? 10 : ($key === 'temperature' ? 35 : 85);
        $normal = $key === 'temperature' ? 20 : 50;
        $alerts = fn () => $this->r[$key]->alerts()->whereHas('reading', fn ($q) => $q->where('source', 'simulation'));
        $this->postJson($this->uri($key), ['value' => $bad])->assertCreated();
        $this->postJson($this->uri($key), ['value' => $bad])->assertCreated();
        $this->assertSame(1, $alerts()->count());
        $first = $alerts()->first();
        $this->patchJson('/api/alerts/'.$first->id.'/acknowledge')->assertOk();
        $this->postJson($this->uri($key), ['value' => $bad])->assertCreated();
        $this->assertSame(1, $alerts()->count());
        $this->postJson($this->uri($key), ['value' => $normal])->assertCreated();
        $this->assertSame('resolved', $first->fresh()->status);
        $this->postJson($this->uri($key), ['value' => $bad])->assertCreated();
        $this->assertSame(2, $alerts()->count());
    }

    public function test_manual_and_historical_records_never_refresh_physical_connection(): void
    {
        $this->fixture();
        $old = Reading::create(['sensor_id' => $this->r['temperature']->id, 'value' => 24.1, 'source' => 'iot', 'recorded_at' => now()]);
        $this->operator();
        $this->getJson($this->uri('temperature').'/current')->assertOk()->assertJsonPath('data.connection_status', 'unverified');
        $this->getJson('/api/history?metric=temperature')->assertOk()->assertJsonFragment(['source_label' => 'IoT no verificado']);
        $this->assertSame('iot', $old->fresh()->source);
        $this->assertNull($old->fresh()->iot_reading_batch_id);
        $this->physical(['temperature' => 24.1]);
        $lastConnection = $this->r['device']->fresh()->last_connection_at;
        $lastPhysical = $this->r['device']->fresh()->last_reading_at;
        $this->travel(6)->minutes();
        $this->operator();
        $this->postJson($this->uri('temperature'), ['value' => 25])->assertCreated();
        $this->assertEquals($lastConnection, $this->r['device']->fresh()->last_connection_at);
        $this->assertEquals($lastPhysical, $this->r['device']->fresh()->last_reading_at);
        $this->assertSame('stale', $this->r['device']->fresh()->connection_state);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.greenhouses.0.metrics.temperature.connection_status', 'simulated');
        $this->getJson('/api/greenhouses/'.$this->r['greenhouse']->id.'/temperature/current')->assertOk()->assertJsonPath('data.connection_status', 'simulated');
        $devices = $this->getJson('/api/zones/'.$this->r['zone']->id.'/devices')->assertOk();
        $this->assertNotNull($devices->json('data.0.last_manual_reading_at'));
    }

    public function test_manual_foreign_sensor_and_free_pro_restrictions(): void
    {
        $this->fixture();
        $foreign = $this->resources($this->company());
        $this->operator();
        $this->postJson('/api/sensors/'.$foreign['sensor']->id.'/soil-moisture', ['value' => 50])->assertForbidden();
        Plan::where('slug', 'free')->update(['max_sensors_per_zone' => 0]);
        $this->postJson($this->uri('temperature'), ['value' => 25])->assertForbidden();
        app(SubscriptionContractService::class)->create($this->r['company'], Plan::where('slug', 'pro')->firstOrFail(), 'monthly', now()->toDateTimeString(), now()->addMonth()->toDateTimeString(), 'reading-security-pro');
        $this->postJson($this->uri('temperature'), ['value' => 25])->assertCreated();
        $this->physical(['temperature' => 25]);
        $this->travel(32)->days();
        $this->operator();
        $this->postJson($this->uri('temperature'), ['value' => 25])->assertForbidden();
        $this->withToken($this->credential)->postJson('/api/iot/readings', $this->payload(['temperature' => 25]))->assertStatus(422);
    }

    public function test_revocation_preserves_physical_provenance_without_claiming_live_connection(): void
    {
        $this->fixture();
        $this->physical(['temperature' => 24.1]);
        $this->operator();
        $this->deleteJson('/api/devices/'.$this->r['device']->id.'/pairing')->assertOk();
        $this->getJson($this->uri('temperature').'/current')->assertOk()
            ->assertJsonPath('data.provenance', 'physical_iot')->assertJsonPath('data.connection_status', 'unpaired');
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.greenhouses.0.metrics.temperature.connection_status', 'unpaired');
    }
}
