<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class LegacyDeviceConnectionTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase;

    public static function legacyConnections(): array
    {
        return [['ethernet'], ['lora'], ['simulation']];
    }

    #[DataProvider('legacyConnections')]
    public function test_existing_connections_can_be_edited_but_not_registered_or_paired_as_iot(string $connection): void
    {
        $company = $this->company();
        $device = $this->resources($company)['device'];
        $device->update(['connection_type' => $connection]);
        $this->loginApi($this->user($company));
        $payload = ['name' => 'Renamed device', 'device_type' => 'ESP32', 'connection_type' => $connection, 'measurement_interval' => 300];
        $this->putJson('/api/devices/'.$device->id, $payload)->assertOk()
            ->assertJsonPath('data.name', 'Renamed device')->assertJsonPath('data.connection_type', $connection)
            ->assertJsonPath('data.measurement_interval', 300)->assertJsonPath('data.device_code', $device->device_code);
        $this->postJson('/api/zones/'.$device->zone_id.'/devices', $payload)->assertUnprocessable()->assertJsonValidationErrors('connection_type');
        $challenge = $this->postJson('/api/iot/pairings')->assertCreated()->json();
        $this->postJson('/api/devices/'.$device->id.'/pairing', [
            'pairing_id' => $challenge['pairing_id'], 'operator_proof' => $challenge['operator_proof'],
        ])->assertStatus(422);
        foreach ([['connection_type' => 'bluetooth'], ['board_model' => 'other'], ['measurement_interval' => 1]] as $invalid) {
            $this->putJson('/api/devices/'.$device->id, array_replace($payload, $invalid))->assertUnprocessable();
        }
        $this->assertSame($connection, $device->fresh()->connection_type);
        $this->assertNull($device->fresh()->paired_at);
    }

    public function test_wifi_iot_cannot_be_downgraded_and_foreign_devices_stay_protected(): void
    {
        $company = $this->company();
        $device = $this->resources($company)['device'];
        $this->loginApi($this->user($company));
        $payload = ['name' => 'Edited', 'device_type' => 'ESP32', 'connection_type' => 'wifi'];
        foreach (['ethernet', 'lora', 'simulation'] as $connection) {
            $this->putJson('/api/devices/'.$device->id, array_replace($payload, ['connection_type' => $connection]))
                ->assertUnprocessable()->assertJsonValidationErrors('connection_type');
        }
        $this->putJson('/api/devices/'.$device->id, $payload)->assertOk();
        $foreign = $this->resources($this->company())['device'];
        $foreign->update(['connection_type' => 'ethernet']);
        $this->putJson('/api/devices/'.$foreign->id, array_replace($payload, ['connection_type' => 'ethernet']))->assertForbidden();
    }
}
