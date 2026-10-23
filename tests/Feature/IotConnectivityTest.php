<?php

namespace Tests\Feature;

use App\Models\Reading;
use App\Services\IotConnectivity;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class IotConnectivityTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase;

    private function fixture(): array
    {
        $this->travelTo('2026-10-23 12:00:00');
        $r = $this->resources($this->company());
        $r['sensor']->update(['unit' => 'raw', 'model' => 'analog_soil', 'pin' => 34]);
        $r['temperature'] = $r['device']->sensors()->create(['name' => 'Temperature', 'sensor_code' => Str::uuid(), 'sensor_type' => 'temperature', 'model' => 'DHT22', 'unit' => '°C', 'pin' => 4, 'status' => 'active']);
        $r['ambient'] = $r['device']->sensors()->create(['name' => 'Ambient', 'sensor_code' => Str::uuid(), 'sensor_type' => 'ambient_humidity', 'model' => 'DHT22', 'unit' => '%', 'pin' => 4, 'status' => 'active']);
        $r['credential'] = 'connectivity-test-device';
        $r['device']->forceFill(['measurement_interval' => 300, 'credential_hash' => hash('sha256', $r['credential']), 'paired_at' => now()])->save();
        $this->send($r);
        $this->loginApi($this->user($r['greenhouse']->company));

        return $r;
    }

    private function send(array $r): void
    {
        $this->withToken($r['credential'])->postJson('/api/iot/readings', ['batch_id' => (string) Str::uuid(), 'readings' => [
            ['sensor_id' => $r['sensor']->id, 'measurement' => 'soil_humidity', 'unit' => 'raw', 'value' => 3184],
            ['sensor_id' => $r['temperature']->id, 'measurement' => 'temperature', 'unit' => '°C', 'value' => 24],
            ['sensor_id' => $r['ambient']->id, 'measurement' => 'ambient_humidity', 'unit' => '%', 'value' => 75],
        ]])->assertCreated();
    }

    private function assertScreens(array $r, string $status): void
    {
        $metrics = ['soil_humidity' => ['sensor', 'soil-moisture'], 'temperature' => ['temperature', 'temperature'], 'ambient_humidity' => ['ambient', 'ambient-humidity']];
        $dashboard = $this->getJson('/api/dashboard')->assertOk();
        foreach ($metrics as $metric => [$key, $path]) {
            $dashboard->assertJsonPath('data.greenhouses.0.metrics.'.$metric.'.connection_status', $status);
            $this->getJson('/api/greenhouses/'.$r['greenhouse']->id.'/'.$path.'/current')->assertOk()->assertJsonPath('data.connection_status', $status);
            $this->getJson('/api/sensors/'.$r[$key]->id.'/'.$path.'/current')->assertOk()->assertJsonPath('data.connection_status', $status);
        }
        $sensors = $this->getJson('/api/devices/'.$r['device']->id.'/sensors')->assertOk()->json('data');
        foreach ($sensors as $sensor) {
            $this->assertSame($status, $sensor['connection_status']);
        }
        $this->getJson('/api/zones/'.$r['zone']->id.'/devices')->assertOk()->assertJsonPath('data.0.connection_status', $status)->assertJsonPath('data.0.status', 'active');
    }

    public function test_clock_expiry_reconnection_and_revocation_across_screens(): void
    {
        $r = $this->fixture();
        $this->assertScreens($r, 'connected');
        $this->travel(360)->seconds();
        $this->assertScreens($r, 'stale');
        $this->getJson('/api/dashboard')->assertJsonPath('data.greenhouses.0.metrics.soil_humidity.value', 3184)
            ->assertJsonPath('data.greenhouses.0.metrics.soil_humidity.measurement_is_stale', true)
            ->assertJsonPath('data.greenhouses.0.metrics.soil_humidity.recorded_at', '2026-10-23 12:00:00');
        $this->travel(300)->seconds();
        $this->assertScreens($r, 'disconnected');
        $this->send($r);
        $this->loginApi($this->user($r['greenhouse']->company));
        $this->assertScreens($r, 'connected');
        $this->deleteJson('/api/devices/'.$r['device']->id.'/pairing')->assertOk();
        $this->assertScreens($r, 'unpaired');
        $this->withToken($r['credential'])->getJson('/api/iot/config')->assertUnauthorized();
    }

    public function test_interval_limits_are_computed_on_every_access(): void
    {
        $r = $this->fixture();
        foreach ([60 => [120, 180], 300 => [360, 660], 900 => [1080, 1980]] as $interval => [$recent, $disconnect]) {
            $this->travelTo('2026-10-23 12:00:00');
            $device = $r['device']->fresh();
            $device->forceFill(['measurement_interval' => $interval, 'last_connection_at' => now()])->save();
            $this->travel($recent - 1)->seconds();
            $this->assertSame('connected', $device->connection_status);
            $this->travel(1)->seconds();
            $this->assertSame('stale', $device->connection_status);
            $this->travel($disconnect - $recent)->seconds();
            $this->assertSame('disconnected', $device->connection_status);
        }
    }

    public function test_authenticated_config_is_communication_but_manual_and_unverified_data_are_not(): void
    {
        $r = $this->fixture();
        $last = $r['device']->fresh()->last_connection_at->toDateTimeString();
        $this->travel(12)->minutes();
        foreach (['simulation', 'iot'] as $source) {
            Reading::create(['sensor_id' => $r['temperature']->id, 'value' => 20, 'source' => $source, 'recorded_at' => now()]);
        }
        $this->assertSame($last, $r['device']->fresh()->last_connection_at->toDateTimeString());
        $this->assertSame('disconnected', $r['device']->fresh()->connection_status);
        $this->withToken($r['credential'])->getJson('/api/iot/config')->assertOk();
        $this->assertSame('connected', $r['device']->fresh()->connection_status);
        $this->loginApi($this->user($r['greenhouse']->company));
        $this->getJson('/api/sensors/'.$r['sensor']->id.'/soil-moisture/current')->assertOk()->assertJsonPath('data.connection_status', 'connected')->assertJsonPath('data.measurement_is_stale', true);
        $foreign = $this->resources($this->company());
        $this->getJson('/api/devices/'.$foreign['device']->id.'/sensors')->assertForbidden();
        $this->getJson('/api/sensors/'.$foreign['sensor']->id.'/soil-moisture/current')->assertForbidden();
        $r['device']->update(['status' => 'inactive']);
        $this->assertSame('disabled', $r['device']->fresh()->connection_status);
        $this->assertSame(['recent_seconds' => 360, 'disconnect_seconds' => 660], app(IotConnectivity::class)->limits(300));
    }
}
