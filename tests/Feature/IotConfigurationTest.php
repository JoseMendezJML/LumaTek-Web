<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Plan;
use App\Services\IotConfiguration;
use App\Services\SubscriptionContractService;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class IotConfigurationTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase;

    private function authenticateIotOperator($user): void
    {
        $this->app['tymon.jwt.auth']->unsetToken();
        $this->app['tymon.jwt']->unsetToken();
        $this->loginApi($user);
    }

    private function setupDevice(): array
    {
        $company = $this->company();
        $user = $this->user($company);
        $resources = $this->resources($company);
        $resources['sensor']->update(['status' => 'inactive']);
        $this->authenticateIotOperator($user);

        return [$user, $resources['device'], $resources['zone']];
    }

    private function pair(Device $device): string
    {
        $challenge = $this->postJson('/api/iot/pairings')->assertCreated()->json();
        $this->postJson('/api/devices/'.$device->id.'/pairing', ['pairing_id' => $challenge['pairing_id'], 'operator_proof' => $challenge['operator_proof']])->assertOk();
        $credential = $this->postJson('/api/iot/pairings/exchange', $challenge)->assertOk()->json('credential');
        $this->postJson('/api/iot/pairings/exchange', $challenge)->assertStatus(422);

        return $credential;
    }

    public function test_registration_generates_immutable_unique_codes_without_connection(): void
    {
        [, , $zone] = $this->setupDevice();
        $payload = ['name' => 'ESP32', 'device_type' => 'ESP32', 'connection_type' => 'wifi', 'device_code' => 'PUBLIC'];
        $a = $this->postJson('/api/zones/'.$zone->id.'/devices', $payload)->assertCreated()->json('data');
        $b = $this->postJson('/api/zones/'.$zone->id.'/devices', $payload)->assertCreated()->json('data');
        $this->assertNotSame($a['device_code'], $b['device_code']);
        $this->assertSame('pending_pairing', $a['connection_state']);
        $this->assertNull($a['last_connection_at']);
        $this->putJson('/api/devices/'.$a['id'], $payload)->assertOk()->assertJsonPath('data.device_code', $a['device_code']);
    }

    public function test_pairing_config_revocation_and_renewal(): void
    {
        [$user, $device] = $this->setupDevice();
        $credential = $this->pair($device);
        $this->assertSame(hash('sha256', $credential), $device->fresh()->credential_hash);
        $this->assertArrayNotHasKey('credential_hash', $device->fresh()->toArray());
        $this->withToken($credential)->getJson('/api/iot/config')->assertOk()->assertJsonPath('device_id', $device->device_code)->assertJsonPath('sensors', []);
        $this->withToken('invalid')->getJson('/api/iot/config')->assertUnauthorized();
        $this->authenticateIotOperator($user);
        $this->deleteJson('/api/devices/'.$device->id.'/pairing')->assertOk();
        $this->withToken($credential)->getJson('/api/iot/config')->assertUnauthorized();
        $this->authenticateIotOperator($user);
        $this->travel(61)->seconds();
        $this->assertNotSame($credential, $this->pair($device));
    }

    public function test_tenant_and_role_boundaries_and_public_code_are_insufficient(): void
    {
        [, $device] = $this->setupDevice();
        $challenge = $this->postJson('/api/iot/pairings')->assertCreated()->json();
        $this->postJson('/api/devices/'.$device->id.'/pairing', ['pairing_id' => $challenge['pairing_id'], 'operator_proof' => $device->device_code])->assertStatus(422);
        $this->authenticateIotOperator($this->user($this->company()));
        $this->postJson('/api/devices/'.$device->id.'/pairing', $challenge)->assertForbidden();
        $this->deleteJson('/api/devices/'.$device->id.'/pairing')->assertForbidden();
        $this->authenticateIotOperator($this->user($device->zone->greenhouse->company, 'employee'));
        $this->postJson('/api/devices/'.$device->id.'/pairing', $challenge)->assertForbidden();
    }

    public function test_expired_challenge_cannot_be_approved(): void
    {
        [, $device] = $this->setupDevice();
        $challenge = $this->postJson('/api/iot/pairings')->assertCreated()->json();
        $this->travel(11)->minutes();
        $this->postJson('/api/devices/'.$device->id.'/pairing', $challenge)->assertStatus(422);
    }

    public function test_supported_sensors_shared_dht_pin_and_raw_soil(): void
    {
        [, $device] = $this->setupDevice();
        $url = '/api/devices/'.$device->id.'/sensors';
        $base = ['name' => 'Temperature', 'sensor_code' => 'T', 'sensor_type' => 'temperature', 'model' => 'DHT22', 'pin' => 4];
        $this->postJson($url, $base)->assertCreated();
        $this->postJson($url, array_replace($base, ['sensor_code' => 'H', 'sensor_type' => 'ambient_humidity']))->assertCreated();
        $this->postJson($url, array_replace($base, ['sensor_code' => 'DUP']))->assertStatus(422);
        $this->postJson($url, array_replace($base, ['sensor_code' => 'BAD', 'pin' => 5]))->assertStatus(422);
        $this->postJson($url, array_replace($base, ['sensor_code' => 'BAD', 'model' => 'DS18B20']))->assertStatus(422);
        $service = app(IotConfiguration::class);
        $raw = $service->validateSensor($device->fresh(), ['sensor_type' => 'soil_humidity', 'model' => 'analog_soil', 'pin' => 32]);
        $this->assertSame('raw', $raw['unit']);
        $device->sensors()->create(['name' => 'Soil', 'sensor_code' => 'S', 'sensor_type' => 'soil_humidity', 'model' => 'analog_soil', 'pin' => 32, 'unit' => 'raw', 'status' => 'active']);
        $this->expectException(ValidationException::class);
        $service->validateSensor($device->fresh(), ['sensor_type' => 'soil_humidity', 'model' => 'analog_soil', 'pin' => 32]);
    }

    public function test_config_filters_plan_limits_and_rejects_inactive_device_and_legacy_sensor(): void
    {
        [, $device] = $this->setupDevice();
        $credential = $this->pair($device);
        $sensor = $device->sensors()->first();
        $sensor->update(['status' => 'active']);
        $this->withToken($credential)->getJson('/api/iot/config')->assertStatus(422);
        $this->assertTrue($device->fresh()->configuration_error);
        $sensor->update(['model' => 'DHT22', 'pin' => 4, 'sensor_type' => 'temperature', 'unit' => '°C']);
        $response = $this->getJson('/api/iot/config')->assertOk();
        $this->assertFalse($device->fresh()->configuration_error);
        $this->assertCount(1, $response->json('sensors'));
        $version = $response->json('configuration_version');
        $device->update(['measurement_interval' => 120]);
        $this->assertNotSame($version, $this->getJson('/api/iot/config')->assertOk()->json('configuration_version'));
        $device->update(['status' => 'inactive']);
        $this->getJson('/api/iot/config')->assertForbidden();
    }

    public function test_plan_limits_apply_to_registration_and_download(): void
    {
        [, $device] = $this->setupDevice();
        $credential = $this->pair($device);
        foreach ([32, 33, 34, 35] as $pin) {
            $device->sensors()->create(['name' => 'Soil', 'sensor_code' => 'SOIL-'.$pin, 'sensor_type' => 'soil_humidity', 'model' => 'analog_soil', 'pin' => $pin, 'unit' => 'raw', 'status' => 'active']);
        }
        $this->withToken($credential)->getJson('/api/iot/config')->assertOk()->assertJsonCount(3, 'sensors');
        $this->assertSame(1, $device->sensors()->where('plan_restricted', true)->count());
        $company = $device->zone->greenhouse->company;
        app(SubscriptionContractService::class)->create($company, Plan::where('slug', 'pro')->firstOrFail(), 'monthly', now()->toDateTimeString(), now()->addMonth()->toDateTimeString(), 'iot-pro');
        $this->getJson('/api/iot/config')->assertOk()->assertJsonCount(4, 'sensors');
        $this->travel(32)->days();
        // Existing contracted plan logic is exercised by the subscription suite;
        // reducing the free catalog limit must affect this endpoint immediately.
        Plan::where('slug', 'free')->update(['max_sensors_per_zone' => 1]);
        $this->getJson('/api/iot/config')->assertOk()->assertJsonCount(1, 'sensors');
        $company = $device->zone->greenhouse->company;
        $this->authenticateIotOperator($this->user($company));
        $this->postJson('/api/devices/'.$device->id.'/sensors', ['name' => 'Extra', 'sensor_code' => 'EXTRA', 'sensor_type' => 'soil_humidity', 'model' => 'analog_soil', 'pin' => 36])->assertStatus(422);
    }

    public function test_calibration_and_raw_ingestion_guard(): void
    {
        [, $device] = $this->setupDevice();
        $data = ['name' => 'Soil', 'sensor_code' => 'RAW', 'sensor_type' => 'soil_humidity', 'model' => 'analog_soil', 'pin' => 32];
        $url = '/api/devices/'.$device->id.'/sensors';
        $this->postJson($url, $data + ['calibration' => ['dry' => 100, 'wet' => 100]])->assertStatus(422);
        $sensor = $this->postJson($url, $data)->assertCreated()->assertJsonPath('data.unit', 'raw')->json('data');
        $this->postJson('/api/sensors/'.$sensor['id'].'/soil-moisture', ['value' => 42])->assertStatus(422);
        $this->assertDatabaseMissing('readings', ['sensor_id' => $sensor['id']]);
        $this->putJson('/api/sensors/'.$sensor['id'], $data + ['calibration' => ['dry' => 3000, 'wet' => 1000]])->assertOk()->assertJsonPath('data.unit', '%');
    }

    public function test_rate_limit_and_revoked_pending_challenge(): void
    {
        [, $device] = $this->setupDevice();
        $challenge = $this->postJson('/api/iot/pairings')->assertCreated()->json();
        $this->postJson('/api/devices/'.$device->id.'/pairing', $challenge)->assertOk();
        $this->deleteJson('/api/devices/'.$device->id.'/pairing')->assertOk();
        $this->postJson('/api/iot/pairings/exchange', $challenge)->assertStatus(422);
    }

    public function test_challenge_attempts_are_limited(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/api/iot/pairings')->assertCreated();
        }
        $this->postJson('/api/iot/pairings')->assertStatus(429);
    }

    public function test_parent_states_and_foreign_sensors_are_enforced(): void
    {
        [, $device] = $this->setupDevice();
        $credential = $this->pair($device);
        $foreign = $this->resources($this->company());
        $foreign['sensor']->update(['model' => 'DHT22', 'pin' => 4, 'sensor_type' => 'temperature', 'unit' => '°C']);
        $this->withToken($credential)->getJson('/api/iot/config')->assertOk()->assertJsonCount(0, 'sensors');
        foreach ([$device->zone, $device->zone->greenhouse, $device->zone->greenhouse->company] as $parent) {
            $parent->update(['status' => 'inactive']);
            $this->getJson('/api/iot/config')->assertForbidden();
            $parent->update(['status' => 'active']);
        }
        $this->assertSame('paired_without_readings', $device->fresh()->connection_state);
        $device->forceFill(['last_reading_at' => now()])->save();
        $this->assertSame('receiving_readings', $device->fresh()->connection_state);
        $this->travel(6)->minutes();
        $this->assertSame('stale', $device->fresh()->connection_state);
    }
}
