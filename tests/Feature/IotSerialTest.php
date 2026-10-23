<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class IotSerialTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase;

    public function test_wizard_lists_only_own_unpaired_devices_without_secret_fields(): void
    {
        $company = $this->company();
        $own = $this->resources($company)['device'];
        $foreign = $this->resources($this->company())['device'];
        $paired = $own->replicate();
        $paired->device_code = 'paired-device';
        $paired->forceFill(['credential_hash' => hash('sha256', 'private-fixture'), 'paired_at' => now()])->save();
        $this->actingAs($this->user($company))->get('/iot/installation')->assertOk()
            ->assertSee('id="iot-serial"', false)->assertSee('js/iot-serial-ui.js')
            ->assertSee($own->device_code)->assertDontSee($foreign->device_code)->assertDontSee('paired-device')
            ->assertDontSee('private-fixture')->assertDontSee('credential_hash')->assertDontSee('exchange_secret')
            ->assertDontSee('esp-web-install-button')->assertHeader('Permissions-Policy', 'serial=(self)');
    }

    public function test_simulated_esp32_exchanges_privately_after_operator_approval_and_cannot_reuse(): void
    {
        $company = $this->company();
        $device = $this->resources($company)['device'];
        // Device-only HTTPS request; its response is never relayed to the browser.
        $esp32 = $this->postJson('/api/iot/pairings')->assertCreated()->json();
        $operator = array_intersect_key($esp32, array_flip(['pairing_id', 'operator_proof']));
        $this->loginApi($this->user($company));
        $approval = $this->postJson('/api/devices/'.$device->id.'/pairing', $operator)->assertOk();
        $approval->assertDontSee('exchange_secret')->assertDontSee('credential');
        $deviceResponse = $this->postJson('/api/iot/pairings/exchange', [
            'pairing_id' => $esp32['pairing_id'], 'exchange_secret' => $esp32['exchange_secret'],
        ])->assertOk();
        $this->assertSame(hash('sha256', $deviceResponse->json('credential')), $device->fresh()->credential_hash);
        $record = DB::table('iot_pairings')->where('id', $esp32['pairing_id'])->first();
        $this->assertSame(hash('sha256', $esp32['exchange_secret']), $record->exchange_hash);
        $this->assertNotNull($record->consumed_at);
        $this->postJson('/api/iot/pairings/exchange', $esp32)->assertStatus(422);
    }

    public function test_operator_only_proof_preserves_role_tenant_and_expiry_boundaries(): void
    {
        $company = $this->company();
        $device = $this->resources($company)['device'];
        $esp32 = $this->postJson('/api/iot/pairings')->assertCreated()->json();
        $operator = array_intersect_key($esp32, array_flip(['pairing_id', 'operator_proof']));
        $this->loginApi($this->user($this->company()));
        $this->postJson('/api/devices/'.$device->id.'/pairing', $operator)->assertForbidden();
        $this->app['tymon.jwt.auth']->unsetToken(); $this->app['tymon.jwt']->unsetToken();
        $this->loginApi($this->user($company, 'employee'));
        $this->postJson('/api/devices/'.$device->id.'/pairing', $operator)->assertForbidden();
        $this->app['tymon.jwt.auth']->unsetToken(); $this->app['tymon.jwt']->unsetToken();
        $this->loginApi($this->user($company));
        $this->travel(601)->seconds();
        $this->postJson('/api/devices/'.$device->id.'/pairing', $operator)->assertStatus(422);
        $this->assertNull($device->fresh()->paired_at);
    }
}
