<?php

namespace Tests\Feature;

use App\Services\GreenhouseSnapshotService;
use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class GreenhouseSnapshotServiceTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase;

    private function snapshot($company): array
    {
        return app(GreenhouseSnapshotService::class)->forAssistant($company);
    }

    public function test_only_includes_own_company(): void
    {
        $own = $this->company(name: 'Agro Norte');
        $foreign = $this->company(name: 'Agro Sur');
        $this->resources($own, 'Invernadero Propio');
        $this->resources($foreign, 'Invernadero Ajeno');

        $json = json_encode($this->snapshot($own), JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('Invernadero Propio', $json);
        $this->assertStringNotContainsString('Invernadero Ajeno', $json);
        $this->assertSame('Agro Norte', $this->snapshot($own)['company']);
    }

    public function test_excludes_plan_restricted_and_inactive_zones(): void
    {
        $company = $this->company();
        $r = $this->resources($company);
        $r['greenhouse']->zones()->create(['name' => 'Zona Restringida', 'status' => 'active', 'plan_restricted' => true]);
        $r['greenhouse']->zones()->create(['name' => 'Zona Inactiva', 'status' => 'inactive']);

        $zones = $this->snapshot($company)['greenhouses'][0]['zones'];

        $this->assertSame(['Zone'], array_column($zones, 'name'));
    }

    public function test_includes_alerts_irrigation_and_metrics(): void
    {
        $company = $this->company();
        $this->resources($company);

        $greenhouse = $this->snapshot($company)['greenhouses'][0];

        $this->assertSame('Tomato', $greenhouse['crop_type']);
        $this->assertSame('Low humidity', $greenhouse['active_alerts'][0]['message']);
        $this->assertSame('Zone', $greenhouse['active_alerts'][0]['zone']);
        $this->assertSame('manual', $greenhouse['last_irrigation']['mode']);
        $this->assertSame('Zone', $greenhouse['last_irrigation']['zone']);
        $this->assertTrue($greenhouse['zones'][0]['automatic_irrigation']['enabled']);
        $this->assertSame(25.0, $greenhouse['metrics']['soil_humidity']['value']);
        $this->assertSame('Baja', $greenhouse['metrics']['soil_humidity']['status_label']);
    }

    public function test_limits_alerts_to_ten_most_recent_with_total(): void
    {
        $company = $this->company();
        $r = $this->resources($company);
        for ($i = 1; $i <= 14; $i++) {
            $this->travel(1)->minutes();
            \App\Models\Alert::create(['sensor_id' => $r['sensor']->id, 'type' => 'low_soil_humidity', 'severity' => 'warning', 'message' => 'Alerta '.$i, 'status' => 'active']);
        }

        $greenhouse = $this->snapshot($company)['greenhouses'][0];

        $this->assertCount(10, $greenhouse['active_alerts']);
        $this->assertSame(15, $greenhouse['active_alerts_total']);
        $this->assertSame('Alerta 14', $greenhouse['active_alerts'][0]['message']);
    }

    public function test_metrics_include_configured_threshold(): void
    {
        $company = $this->company();
        $this->resources($company);

        $metrics = $this->snapshot($company)['greenhouses'][0]['metrics'];

        $this->assertSame(['min' => 30.0, 'max' => 80.0], $metrics['soil_humidity']['threshold']);
        $this->assertNull($metrics['temperature']['threshold']);
    }

    public function test_contains_no_ids(): void
    {
        $company = $this->company();
        $this->resources($company);

        $json = json_encode($this->snapshot($company));

        $this->assertStringNotContainsString('"id"', $json);
        $this->assertStringNotContainsString('"sensor_id"', $json);
    }

    public function test_company_without_greenhouses(): void
    {
        $this->assertSame([], $this->snapshot($this->company())['greenhouses']);
    }
}
