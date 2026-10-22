<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class DashboardSnapshotRegressionTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase;

    public function test_dashboard_payload_is_unchanged_after_extraction(): void
    {
        $this->travelTo('2026-10-22 12:00:00');
        $company = $this->company();
        $r = $this->resources($company);
        $this->loginApi($this->user($company));

        $noData = fn (string $unit) => [
            'value' => null, 'unit' => $unit, 'status' => 'no_data', 'status_label' => 'Sin datos',
            'connection_status' => 'no_data', 'connection_label' => 'Sin datos',
            'sensor_id' => null, 'sensor_name' => null, 'recorded_at' => null,
        ];

        $this->getJson('/api/dashboard')->assertOk()->assertExactJson([
            'data' => [
                'summary' => ['greenhouses' => 1, 'zones' => 1, 'devices' => 1, 'sensors' => 1, 'active_alerts' => 1],
                'greenhouses' => [[
                    'id' => $r['greenhouse']->id,
                    'name' => 'Greenhouse',
                    'crop_type' => 'Tomato',
                    'location' => 'Test location',
                    'zones_count' => 1,
                    'devices_count' => 1,
                    'sensors_count' => 1,
                    'metrics' => [
                        'temperature' => $noData('°C'),
                        'soil_humidity' => [
                            'value' => 25.0, 'unit' => '%', 'status' => 'low', 'status_label' => 'Baja',
                            'connection_status' => 'connected', 'connection_label' => 'Conectado',
                            'sensor_id' => $r['sensor']->id, 'sensor_name' => 'Sensor',
                            'recorded_at' => '2026-10-22 12:00:00',
                        ],
                        'ambient_humidity' => $noData('%'),
                    ],
                    'active_alerts' => 1,
                ]],
                'recent_alerts' => [[
                    'id' => $r['alert']->id,
                    'type' => 'low_soil_humidity',
                    'type_label' => 'Humedad del suelo baja',
                    'message' => 'Low humidity',
                    'severity' => 'warning',
                    'severity_label' => 'Advertencia',
                    'status' => 'active',
                    'status_label' => 'Activa',
                    'created_at' => '2026-10-22 12:00:00',
                    'reading' => null,
                    'sensor' => ['name' => 'Sensor', 'unit' => '%'],
                    'zone' => ['name' => 'Zone'],
                    'greenhouse' => ['name' => 'Greenhouse'],
                ]],
            ],
        ]);
    }
}
