<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Reading;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class IotReadingsTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase;

    private string $credential;

    private function fixture(): array
    {
        $company = $this->company();
        $resources = $this->resources($company);
        $device = $resources['device'];
        $resources['sensor']->update(['model' => 'analog_soil', 'pin' => 34, 'unit' => 'raw']);
        $temperature = $device->sensors()->create(['name' => 'Temperature', 'sensor_code' => Str::uuid(), 'sensor_type' => 'temperature', 'model' => 'DHT22', 'pin' => 4, 'unit' => '°C', 'status' => 'active']);
        $humidity = $device->sensors()->create(['name' => 'Humidity', 'sensor_code' => Str::uuid(), 'sensor_type' => 'ambient_humidity', 'model' => 'DHT22', 'pin' => 4, 'unit' => '%', 'status' => 'active']);
        foreach (['temperature' => ['°C', 10, 30], 'ambient_humidity' => ['%', 20, 70]] as $type => [$unit, $min, $max]) {
            $resources['greenhouse']->thresholds()->create(['variable' => $type, 'min_value' => $min, 'max_value' => $max, 'unit' => $unit]);
        }
        $this->credential = bin2hex(random_bytes(32));
        $device->forceFill(['credential_hash' => hash('sha256', $this->credential), 'paired_at' => now()])->save();
        $this->withToken($this->credential);

        return $resources + compact('company', 'temperature', 'humidity');
    }

    private function payload(array $resources): array
    {
        return ['batch_id' => (string) Str::uuid(), 'readings' => [
            ['sensor_id' => $resources['temperature']->id, 'measurement' => 'temperature', 'unit' => '°C', 'value' => 35.25],
            ['sensor_id' => $resources['humidity']->id, 'measurement' => 'ambient_humidity', 'unit' => '%', 'value' => 85],
            ['sensor_id' => $resources['sensor']->id, 'measurement' => 'soil_humidity', 'unit' => 'raw', 'value' => 2800],
        ]];
    }

    private function snapshot(): array
    {
        return collect(['readings', 'raw_sensor_readings', 'iot_reading_batches', 'alerts', 'irrigation_events', 'devices'])->mapWithKeys(
            fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]
        )->all();
    }

    public function test_authenticated_batch_persists_physical_values_raw_and_alerts_without_irrigation(): void
    {
        $r = $this->fixture();
        $payload = $this->payload($r);
        $payload['source'] = 'simulation';
        $payload['readings'][0]['source'] = 'simulation';
        $events = DB::table('irrigation_events')->get()->toJson();
        $soilAlerts = $r['sensor']->alerts()->get()->toJson();
        $this->postJson('/api/iot/readings', $payload)->assertCreated()->assertJsonPath('accepted', 3)->assertJsonPath('duplicate', false);
        $this->assertDatabaseHas('readings', ['sensor_id' => $r['temperature']->id, 'value' => 35.25, 'source' => 'iot']);
        $this->assertDatabaseHas('readings', ['sensor_id' => $r['humidity']->id, 'value' => 85, 'source' => 'iot']);
        $this->assertDatabaseHas('raw_sensor_readings', ['sensor_id' => $r['sensor']->id, 'value' => 2800, 'unit' => 'raw', 'source' => 'iot']);
        $this->assertSame(1, $r['sensor']->readings()->count()); // Only the pre-existing fixture reading.
        $this->assertSame($soilAlerts, $r['sensor']->alerts()->get()->toJson());
        $this->assertSame($events, DB::table('irrigation_events')->get()->toJson());
        $this->assertDatabaseHas('alerts', ['sensor_id' => $r['temperature']->id, 'type' => 'high_temperature', 'status' => 'active']);
        $this->assertDatabaseHas('alerts', ['sensor_id' => $r['humidity']->id, 'type' => 'high_ambient_humidity', 'status' => 'active']);
        $this->assertSame('receiving_readings', $r['device']->fresh()->connection_state);
        $this->assertSame('35.25', $r['temperature']->fresh()->latestReading->value);
        $this->loginApi($this->user($r['company']));
        $this->getJson('/api/devices/'.$r['device']->id.'/sensors')->assertOk()->assertJsonFragment(['value' => 2800, 'unit' => 'raw']);
    }

    public function test_retry_is_idempotent_and_changed_content_conflicts(): void
    {
        $this->travelTo(now()->startOfSecond());
        $r = $this->fixture();
        $payload = $this->payload($r);
        $this->postJson('/api/iot/readings', $payload)->assertCreated();
        $before = $this->snapshot();
        $payload['readings'] = array_reverse($payload['readings']);
        $this->postJson('/api/iot/readings', $payload)->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame($before, $this->snapshot());
        $payload['readings'][0]['value']++;
        $this->postJson('/api/iot/readings', $payload)->assertStatus(409);
        $this->assertSame($before, $this->snapshot());
    }

    public static function invalidMeasurements(): array
    {
        return ['wrong unit' => [0, 'unit', '%'], 'wrong measurement' => [0, 'measurement', 'soil_humidity'],
            'not numeric' => [0, 'value', 'NaN'], 'boolean' => [0, 'value', true], 'overflow' => [0, 'value', 1e100],
            'too hot' => [0, 'value', 81], 'negative humidity' => [1, 'value', -1], 'high humidity' => [1, 'value', 101],
            'raw overflow' => [2, 'value', 4096], 'raw fractional' => [2, 'value', 1.5], 'raw disguised as percent' => [2, 'unit', '%']];
    }

    #[DataProvider('invalidMeasurements')]
    public function test_invalid_batch_is_atomic(int $index, string $key, mixed $value): void
    {
        $r = $this->fixture();
        $payload = $this->payload($r);
        $payload['readings'][$index][$key] = $value;
        $before = $this->snapshot();
        $this->postJson('/api/iot/readings', $payload)->assertStatus(422);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_sensor_ownership_and_status_are_enforced(): void
    {
        $r = $this->fixture();
        $foreign = $this->resources($this->company());
        $sameCompany = $this->resources($r['company'], 'Other greenhouse');
        foreach ([$foreign['sensor']->id, $sameCompany['sensor']->id, 999999] as $id) {
            $payload = $this->payload($r);
            $payload['readings'][2]['sensor_id'] = $id;
            $before = $this->snapshot();
            $this->postJson('/api/iot/readings', $payload)->assertStatus(422);
            $this->assertSame($before, $this->snapshot());
        }
        $r['humidity']->update(['status' => 'inactive']);
        $before = $this->snapshot();
        $this->postJson('/api/iot/readings', $this->payload($r))->assertStatus(422);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_credentials_and_parent_states_are_required(): void
    {
        $r = $this->fixture();
        $payload = $this->payload($r);
        $before = $this->snapshot();
        $this->withToken('invalid')->postJson('/api/iot/readings', $payload)->assertUnauthorized();
        $this->loginApi($this->user($r['company']));
        $this->postJson('/api/iot/readings', $payload)->assertUnauthorized();
        $this->assertSame($before, $this->snapshot());
        $this->withToken($this->credential);
        foreach ([$r['device'], $r['zone'], $r['greenhouse'], $r['company']] as $model) {
            $model->update(['status' => 'inactive']);
            $before = $this->snapshot();
            $this->postJson('/api/iot/readings', $payload)->assertForbidden();
            $this->assertSame($before, $this->snapshot());
            $model->update(['status' => 'active']);
        }
        $r['device']->forceFill(['credential_hash' => null])->save();
        $this->postJson('/api/iot/readings', $payload)->assertUnauthorized();
    }

    public function test_plan_restrictions_and_incompatible_hardware_are_rejected(): void
    {
        $r = $this->fixture();
        Plan::where('slug', 'free')->update(['max_sensors_per_zone' => 1]);
        $this->postJson('/api/iot/readings', $this->payload($r))->assertStatus(422);
        $this->assertDatabaseCount('iot_reading_batches', 0);
        Plan::where('slug', 'free')->update(['max_sensors_per_zone' => 3]);
        $r['sensor']->update(['pin' => 2]);
        $this->postJson('/api/iot/readings', $this->payload($r))->assertStatus(422);
        $this->assertDatabaseCount('iot_reading_batches', 0);
    }

    public function test_timestamp_order_and_alert_resolution(): void
    {
        $r = $this->fixture();
        $payload = $this->payload($r);
        $this->postJson('/api/iot/readings', $payload)->assertCreated();
        $last = $r['device']->fresh()->last_reading_at;
        $payload['batch_id'] = (string) Str::uuid();
        $payload['recorded_at'] = now()->subHour()->utc()->format('Y-m-d\TH:i:s\Z');
        $payload['readings'][0]['value'] = 20;
        $payload['readings'][1]['value'] = 40;
        $this->postJson('/api/iot/readings', $payload)->assertCreated();
        $this->assertEquals($last, $r['device']->fresh()->last_reading_at);
        $this->assertDatabaseHas('alerts', ['sensor_id' => $r['temperature']->id, 'status' => 'active']);
        $this->travel(60)->seconds();
        unset($payload['recorded_at']);
        $payload['batch_id'] = (string) Str::uuid();
        $this->postJson('/api/iot/readings', $payload)->assertCreated();
        $this->assertSame(0, $r['temperature']->alerts()->where('status', 'active')->count());
        $this->assertSame(0, $r['humidity']->alerts()->where('status', 'active')->count());
    }

    public function test_wire_utc_timestamp_is_not_shifted_in_database_or_communication_state(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $r = $this->fixture();
        $payload = $this->payload($r);
        $payload['recorded_at'] = now()->subMinute()->utc()->format('Y-m-d\TH:i:s\Z');
        $this->postJson('/api/iot/readings', $payload)->assertCreated();
        $this->assertTrue($r['temperature']->fresh()->latestReading->recorded_at->eq(now()->subMinute()));
        $this->assertTrue($r['sensor']->fresh()->latestRawReading->recorded_at->eq(now()->subMinute()));
        $this->assertTrue($r['device']->fresh()->last_reading_at->eq(now()->subMinute()));
        $this->assertSame('receiving_readings', $r['device']->fresh()->connection_state);
    }

    public function test_expired_future_and_malformed_batches_are_rejected(): void
    {
        $r = $this->fixture();
        $before = $this->snapshot();
        foreach ([now()->subDays(2), now()->addMinutes(6)] as $timestamp) {
            $payload = $this->payload($r);
            $payload['recorded_at'] = $timestamp->utc()->format('Y-m-d\TH:i:s\Z');
            $this->postJson('/api/iot/readings', $payload)->assertStatus(422);
            $this->assertSame($before, $this->snapshot());
        }
        $payload = $this->payload($r);
        $payload['readings'][] = $payload['readings'][0];
        $this->postJson('/api/iot/readings', $payload)->assertStatus(422);
        $payload['batch_id'] = 'not-a-uuid';
        $this->postJson('/api/iot/readings', $payload)->assertStatus(422);
        $payload = $this->payload($r);
        $payload['readings'] = [];
        $this->postJson('/api/iot/readings', $payload)->assertStatus(422);
        $payload['readings'] = array_fill(0, 33, $this->payload($r)['readings'][0]);
        $this->postJson('/api/iot/readings', $payload)->assertStatus(422);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_raw_only_batch_updates_physical_state_without_percentage_alerts_or_readings(): void
    {
        $r = $this->fixture();
        $before = collect(['readings', 'alerts', 'irrigation_events'])->map(fn ($table) => DB::table($table)->get()->toJson())->all();
        $payload = $this->payload($r);
        $payload['readings'] = [$payload['readings'][2]];
        $payload['readings'][0]['value'] = 10; // Would be low if wrongly treated as a percentage.
        $this->postJson('/api/iot/readings', $payload)->assertCreated();
        $this->assertSame($before, collect(['readings', 'alerts', 'irrigation_events'])->map(fn ($table) => DB::table($table)->get()->toJson())->all());
        $this->assertSame('receiving_readings', $r['device']->fresh()->connection_state);
        $this->assertDatabaseCount('raw_sensor_readings', 1);
        $this->travel(7)->minutes();
        $lastReading = $r['device']->fresh()->last_reading_at;
        $this->postJson('/api/iot/readings', $payload)->assertOk()->assertJsonPath('duplicate', true);
        $this->assertEquals($lastReading, $r['device']->fresh()->last_reading_at);
        $this->assertSame('stale', $r['device']->fresh()->connection_state);
    }

    public function test_calibrated_soil_is_percentage_and_plan_parent_restrictions_apply(): void
    {
        $r = $this->fixture();
        $r['sensor']->update(['unit' => '%', 'calibration' => ['dry' => 3000, 'wet' => 1000]]);
        $payload = $this->payload($r);
        $payload['readings'][2]['unit'] = '%';
        $payload['readings'][2]['value'] = 50;
        $this->postJson('/api/iot/readings', $payload)->assertCreated();
        $this->assertDatabaseHas('readings', ['sensor_id' => $r['sensor']->id, 'value' => 50, 'source' => 'iot']);
        $this->assertDatabaseCount('raw_sensor_readings', 0);
        Plan::where('slug', 'free')->update(['max_greenhouses' => 0]);
        $before = $this->snapshot();
        $this->postJson('/api/iot/readings', $this->payload($r))->assertForbidden();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_existing_history_and_current_endpoints_show_iot_temperature_and_humidity(): void
    {
        $r = $this->fixture();
        $this->postJson('/api/iot/readings', $this->payload($r))->assertCreated();
        $this->loginApi($this->user($r['company']));
        $this->getJson('/api/sensors/'.$r['temperature']->id.'/temperature/current')->assertOk()->assertJsonFragment(['temperature' => 35.3]);
        $this->getJson('/api/sensors/'.$r['humidity']->id.'/ambient-humidity/current')->assertOk()->assertJsonFragment(['humidity' => 85]);
        $this->getJson('/api/history?metric=temperature')->assertOk()->assertJsonFragment(['source' => 'iot']);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonFragment(['sensor_id' => $r['temperature']->id]);
    }

    public function test_batch_ids_are_scoped_to_the_authenticated_device(): void
    {
        $first = $this->fixture();
        $payload = $this->payload($first);
        $this->postJson('/api/iot/readings', $payload)->assertCreated();
        $second = $this->fixture();
        $other = $this->payload($second);
        $other['batch_id'] = $payload['batch_id'];
        $this->postJson('/api/iot/readings', $other)->assertCreated()->assertJsonPath('duplicate', false);
        $this->assertDatabaseCount('iot_reading_batches', 2);
    }

    public function test_snapshot_migration_refuses_destructive_rollback_and_preserves_ingestion(): void
    {
        $r = $this->fixture();
        $migration = require database_path('migrations/2026_10_22_040000_preserve_measurement_history.php');
        $legacy = $r['sensor']->readings()->first()->only(['id', 'sensor_id', 'value', 'source', 'unit']);
        try {
            $migration->down();
            $this->fail('Snapshot history must not be discarded by rollback.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Cannot discard', $e->getMessage());
        }
        $this->assertDatabaseHas('readings', $legacy);
        $this->postJson('/api/iot/readings', $this->payload($r))->assertCreated();
    }
}
