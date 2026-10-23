<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class SoilMoistureUnitValidationTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase;

    private function fixture(): array
    {
        $company = $this->company();
        $resources = $this->resources($company);
        $resources['alert']->update(['reading_id' => $resources['sensor']->readings()->first()->id]);
        $this->loginApi($this->user($company));

        return $resources;
    }

    private function sideEffects(): array
    {
        return collect(['readings', 'alerts', 'irrigation_events'])->mapWithKeys(
            fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]
        )->all();
    }

    public static function rejectedUnits(): array
    {
        return ['raw low' => ['raw', 10], 'raw high' => ['raw', 90], 'legacy null low' => [null, 10], 'legacy null high' => [null, 90]];
    }

    #[DataProvider('rejectedUnits')]
    public function test_non_percentage_units_are_rejected_without_side_effects(?string $unit, int $value): void
    {
        $resources = $this->fixture();
        $sensor = $resources['sensor'];
        if ($unit === null) {
            // The current schema requires a unit. Emulate a legacy schema only
            // on this test's isolated SQLite :memory: connection.
            Schema::table('sensors', fn (Blueprint $table) => $table->string('unit', 20)->nullable()->change());
        }
        $sensor->update(['unit' => $unit]);
        $before = $this->sideEffects();
        $lastConnection = $resources['device']->fresh()->last_connection_at;

        $this->postJson('/api/sensors/'.$sensor->id.'/soil-moisture', ['value' => $value])->assertStatus(422);

        $this->assertSame($before, $this->sideEffects());
        $this->assertSame($unit, $sensor->fresh()->unit);
        $this->assertEquals($lastConnection, $resources['device']->fresh()->last_connection_at);
    }

    public function test_percentage_readings_preserve_thresholds_alerts_irrigation_and_history(): void
    {
        $resources = $this->fixture();
        $sensor = $resources['sensor'];
        $uri = '/api/sensors/'.$sensor->id.'/soil-moisture';
        $counts = array_map(fn ($table) => DB::table($table)->count(), ['readings', 'alerts', 'irrigation_events']);

        $low = $this->postJson($uri, ['value' => 10, 'source' => 'simulation'])->assertCreated()
            ->assertJsonPath('data.humidity', 10)->assertJsonPath('data.unit', '%')
            ->assertJsonPath('data.level', 'low')->assertJsonPath('data.threshold.min', 30)
            ->assertJsonPath('data.threshold.max', 80)->assertJsonPath('data.automatic_irrigation.started', true);
        $this->assertDatabaseCount('readings', $counts[0] + 1);
        // The active low-humidity alert is reused rather than duplicated.
        $this->assertDatabaseCount('alerts', $counts[1]);
        $this->assertSame($resources['alert']->id, $low->json('data.alert.id'));
        $this->assertDatabaseCount('irrigation_events', $counts[2] + 1);
        $this->assertDatabaseHas('irrigation_events', ['id' => $low->json('data.automatic_irrigation.id'), 'mode' => 'automatic', 'status' => 'started', 'soil_humidity_before' => 10]);

        $this->postJson($uri, ['value' => 50])->assertCreated()->assertJsonPath('data.level', 'medium')
            ->assertJsonPath('data.automatic_irrigation.started', false);
        $this->assertSame('resolved', $resources['alert']->fresh()->status);
        $this->postJson($uri, ['value' => 90])->assertCreated()->assertJsonPath('data.level', 'high');
        $this->assertDatabaseCount('readings', $counts[0] + 3);
        $this->assertDatabaseCount('irrigation_events', $counts[2] + 1);
        $this->getJson($uri.'/history')->assertOk()->assertJsonPath('data.total_readings', $counts[0] + 3);
        $this->assertSame('%', $sensor->fresh()->unit);
    }

    public function test_percentage_low_reading_creates_alert_when_none_is_active(): void
    {
        $resources = $this->fixture();
        $resources['alert']->update(['status' => 'resolved', 'resolved_at' => now()]);
        $this->postJson('/api/sensors/'.$resources['sensor']->id.'/soil-moisture', ['value' => 50])->assertCreated();
        $count = DB::table('alerts')->count();
        $this->postJson('/api/sensors/'.$resources['sensor']->id.'/soil-moisture', ['value' => 10])->assertCreated()
            ->assertJsonPath('data.alert.type', 'low_soil_humidity');
        $this->assertDatabaseCount('alerts', $count + 1);
        $this->assertDatabaseHas('alerts', ['sensor_id' => $resources['sensor']->id, 'status' => 'active', 'type' => 'low_soil_humidity']);
    }
}
