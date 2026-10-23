<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\RawSensorReading;
use App\Models\Reading;
use App\Services\GreenhouseSnapshotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class GreenhouseRawSummaryTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase;

    private function fixture(): array
    {
        $this->travelTo('2026-10-22 12:00:00');
        $company = $this->company();
        $r = $this->resources($company);
        $r['device']->forceFill(['paired_at' => now(), 'credential_hash' => hash('sha256', 'summary-fixture-device')])->save();
        $r['sensor']->update(['unit' => 'raw', 'model' => 'analog_soil', 'pin' => 34]);
        $r['sensor']->readings()->update(['recorded_at' => now()->subMinutes(45)]);
        // The old percentage reading must never be relabelled as RAW.
        $this->loginApi($this->user($company));

        return $r + compact('company');
    }

    private function raw(array $r, int $value = 3185, string $source = 'iot', int $minutesAgo = 0): void
    {
        if ($source === 'iot') $r['device']->forceFill(['last_connection_at' => now()->subMinutes($minutesAgo)])->save();
        $batch = DB::table('iot_reading_batches')->insertGetId([
            'device_id' => $r['device']->id, 'batch_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64),
            'recorded_at' => now()->subMinutes($minutesAgo), 'received_at' => now(), 'reading_count' => 1,
        ]);
        RawSensorReading::create(['sensor_id' => $r['sensor']->id, 'iot_reading_batch_id' => $batch, 'value' => $value, 'recorded_at' => now()->subMinutes($minutesAgo)])
            ->forceFill(['source' => $source])->save();
    }

    private function metric(): array
    {
        return $this->getJson('/api/dashboard')->assertOk()->json('data.greenhouses.0.metrics.soil_humidity');
    }

    public function test_raw_is_shown_without_percentage_thresholds_or_side_effects(): void
    {
        $r = $this->fixture();
        $this->raw($r);
        $before = collect(['alerts', 'irrigation_events', 'readings'])->map(fn ($table) => DB::table($table)->get()->toJson())->all();
        $metric = $this->metric();
        $this->assertSame(3185, $metric['value']);
        $this->assertSame('raw', $metric['unit']);
        $this->assertSame('pending_calibration', $metric['status']);
        $this->assertSame('Pendiente de calibración', $metric['status_label']);
        $this->assertSame('iot', $metric['source']);
        $this->assertSame('connected', $metric['connection_status']);
        $this->getJson('/api/greenhouses/'.$r['greenhouse']->id.'/soil-moisture/current')->assertOk()
            ->assertJsonPath('data.humidity', 3185)->assertJsonPath('data.unit', 'raw')
            ->assertJsonPath('data.level', 'pending_calibration')->assertJsonPath('data.threshold', null)
            ->assertJsonPath('data.level_label', 'Pendiente de calibración')->assertJsonPath('data.source', 'iot');
        $assistant = app(GreenhouseSnapshotService::class)->forAssistant($r['company']);
        $this->assertNull($assistant['greenhouses'][0]['metrics']['soil_humidity']['threshold']);
        $this->assertSame($before, collect(['alerts', 'irrigation_events', 'readings'])->map(fn ($table) => DB::table($table)->get()->toJson())->all());
    }

    public function test_only_the_latest_physical_raw_is_selected_and_staleness_is_visible(): void
    {
        $r = $this->fixture();
        $this->raw($r, 3000, 'iot', 25);
        $this->raw($r, 3185, 'iot', 15);
        $this->raw($r, 7, 'simulation');
        $metric = $this->metric();
        $this->assertSame(3185, $metric['value']);
        $this->assertSame('disconnected', $metric['connection_status']);
        $this->assertSame('pending_calibration', $metric['status']);
    }

    public function test_raw_sensor_without_physical_readings_does_not_use_percentage_or_simulation(): void
    {
        $r = $this->fixture();
        $r['sensor']->readings()->delete();
        $this->raw($r, 10, 'simulation');
        $metric = $this->metric();
        $this->assertNull($metric['value']);
        $this->assertSame('raw', $metric['unit']);
        $this->assertSame('no_data', $metric['connection_status']);
        $this->getJson('/api/greenhouses/'.$r['greenhouse']->id.'/soil-moisture/current')->assertOk()
            ->assertJsonPath('data.humidity', null)->assertJsonPath('data.unit', 'raw')->assertJsonPath('data.threshold', null);
    }

    public function test_calibrated_percentage_preserves_its_level_and_does_not_use_raw_history(): void
    {
        $r = $this->fixture();
        $this->raw($r, 3000, 'iot', 60);
        $r['sensor']->update(['unit' => '%', 'calibration' => ['dry' => 3500, 'wet' => 1200]]);
        $metric = $this->metric();
        $this->assertEquals(25, $metric['value']);
        $this->assertSame('%', $metric['unit']);
        $this->assertSame('low', $metric['status']);
        $this->assertSame('simulation', $metric['source']);
        $this->assertSame('simulated', $metric['connection_status']);
        $this->getJson('/api/greenhouses/'.$r['greenhouse']->id.'/soil-moisture/current')->assertOk()
            ->assertJsonPath('data.unit', '%')->assertJsonPath('data.level', 'low')->assertJsonPath('data.threshold.min', 30);
        $this->travel(1)->minutes();
        $r['device']->forceFill(['last_connection_at' => now()])->save();
        Reading::create(['sensor_id' => $r['sensor']->id, 'value' => 50, 'source' => 'iot', 'recorded_at' => now(),
            'iot_reading_batch_id' => DB::table('iot_reading_batches')->where('device_id', $r['device']->id)->value('id')]);
        $metric = $this->metric();
        $this->assertEquals(50, $metric['value']);
        $this->assertSame('medium', $metric['status']);
        $this->assertSame('iot', $metric['source']);
        $this->assertSame('connected', $metric['connection_status']);
    }

    public function test_client_claimed_iot_source_without_authenticated_batch_is_not_physical(): void
    {
        $r = $this->fixture();
        $r['sensor']->update(['unit' => '%']);
        $r['sensor']->readings()->update(['source' => 'iot']);
        $metric = $this->metric();
        $this->assertSame('unverified', $metric['connection_status']);
        $this->assertSame('IoT no verificado', $metric['source_label']);
        $this->getJson('/api/greenhouses/'.$r['greenhouse']->id.'/soil-moisture/current')->assertOk()
            ->assertJsonPath('data.connection_status', 'unverified')->assertJsonPath('data.source_label', 'IoT no verificado');
    }

    public function test_percentage_sensor_without_readings_keeps_no_data_and_does_not_fall_back_to_raw(): void
    {
        $r = $this->fixture();
        $r['sensor']->update(['unit' => '%', 'calibration' => ['dry' => 3500, 'wet' => 1200]]);
        $r['sensor']->readings()->delete();
        $metric = $this->metric();
        $this->assertNull($metric['value']);
        $this->assertSame('%', $metric['unit']);
        $this->assertSame('no_data', $metric['status']);
        $this->getJson('/api/greenhouses/'.$r['greenhouse']->id.'/soil-moisture/current')->assertOk()
            ->assertJsonPath('data.humidity', null)->assertJsonPath('data.unit', '%')->assertJsonPath('data.connection_status', 'no_data');
    }

    public static function restrictions(): array
    {
        return array_map(fn ($state) => [$state], ['sensor_inactive', 'device_inactive', 'zone_inactive', 'greenhouse_inactive', 'sensor_plan', 'zone_plan', 'greenhouse_plan']);
    }

    #[DataProvider('restrictions')]
    public function test_unavailable_raw_is_not_exposed(string $state): void
    {
        $r = $this->fixture();
        $this->raw($r);
        [$resource, $restriction] = explode('_', $state);
        if ($restriction === 'inactive') {
            $r[$resource]->update(['status' => 'inactive']);
        } else {
            $limit = ['sensor' => 'max_sensors_per_zone', 'zone' => 'max_zones_per_greenhouse', 'greenhouse' => 'max_greenhouses'][$resource];
            Plan::where('slug', 'free')->update([$limit => 0]);
        }
        $response = $this->getJson('/api/dashboard')->assertOk();
        $this->assertStringNotContainsString('3185', $response->getContent());
        $current = $this->getJson('/api/greenhouses/'.$r['greenhouse']->id.'/soil-moisture/current')->assertOk();
        $this->assertNull($current->json('data.humidity'));
    }

    public function test_tenant_boundary_is_preserved_for_raw(): void
    {
        $r = $this->fixture();
        $r['sensor']->readings()->delete();
        $foreign = $this->resources($this->company());
        $foreign['sensor']->update(['unit' => 'raw']);
        $this->raw($foreign, 4095);
        $this->assertNull($this->metric()['value']);
        $this->getJson('/api/greenhouses/'.$foreign['greenhouse']->id.'/soil-moisture/current')->assertForbidden();
    }
}
