<?php

namespace Tests\Concerns;

use App\Models\Alert;
use App\Models\Company;
use App\Models\Greenhouse;
use App\Models\IrrigationEvent;
use App\Models\Reading;
use App\Models\User;
use App\Models\ZoneIrrigationSetting;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

trait CreatesGreenhouseFixtures
{
    private function company(string $status = 'active', string $name = 'Test company'): Company
    {
        return Company::create(['name' => $name, 'email' => fake()->unique()->safeEmail(), 'status' => $status]);
    }

    private function user(?Company $company, string $role = 'company_admin'): User
    {
        return User::factory()->create([
            'company_id' => $company?->id,
            'role_id' => DB::table('roles')->where('name', $role)->value('id'),
            'status' => 'active',
            'is_company_owner' => $company !== null && $role === 'company_admin',
        ]);
    }

    private function loginApi(User $user): void
    {
        $this->withToken(JWTAuth::fromUser($user));
        $this->app['auth']->forgetGuards();
    }

    private function resources(Company $company, string $name = 'Greenhouse'): array
    {
        $greenhouse = Greenhouse::create(['company_id' => $company->id, 'name' => $name, 'crop_type' => 'Tomato', 'area' => 120, 'location' => 'Test location', 'planting_date' => '2026-01-01', 'nominal_flow' => 15, 'status' => 'active']);
        $greenhouse->thresholds()->create(['variable' => 'soil_humidity', 'min_value' => 30, 'max_value' => 80, 'unit' => '%']);
        $zone = $greenhouse->zones()->create(['name' => 'Zone', 'status' => 'active']);
        $device = $zone->devices()->create(['name' => 'Device', 'device_code' => fake()->unique()->uuid(), 'device_type' => 'ESP32', 'connection_type' => 'wifi', 'status' => 'active']);
        $sensor = $device->sensors()->create(['name' => 'Sensor', 'sensor_code' => fake()->unique()->uuid(), 'sensor_type' => 'soil_humidity', 'unit' => '%', 'status' => 'active']);
        Reading::create(['sensor_id' => $sensor->id, 'value' => 25, 'recorded_at' => now()]);
        $alert = Alert::create(['sensor_id' => $sensor->id, 'type' => 'low_soil_humidity', 'severity' => 'warning', 'message' => 'Low humidity', 'status' => 'active']);
        $event = IrrigationEvent::create(['zone_id' => $zone->id, 'mode' => 'manual', 'status' => 'completed', 'water_liters' => 8, 'started_at' => now()->subMinutes(30)]);
        $setting = ZoneIrrigationSetting::create(['zone_id' => $zone->id, 'automatic_enabled' => true, 'duration_minutes' => 10, 'water_liters' => 8, 'cooldown_minutes' => 60]);

        return compact('greenhouse', 'zone', 'device', 'sensor', 'alert', 'event', 'setting');
    }
}
