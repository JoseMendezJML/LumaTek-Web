<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\RawSensorReading;
use App\Models\Reading;
use App\Services\MeasurementHistory;
use App\Services\SubscriptionContractService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class MeasurementHistoryTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase;

    private function fixture(): array
    {
        $this->travelTo('2026-10-22 12:00:00');
        $company = $this->company();
        $r = $this->resources($company);
        $r['device']->forceFill(['paired_at' => now(), 'credential_hash' => hash('sha256', 'history-device')])->save();
        $this->loginApi($this->user($company));

        return $r + compact('company');
    }

    private function raw(array $r, int $value = 3184): RawSensorReading
    {
        $r['sensor']->update(['unit' => 'raw', 'calibration' => null]);
        $batch = DB::table('iot_reading_batches')->insertGetId(['device_id' => $r['device']->id, 'batch_id' => (string) Str::uuid(),
            'payload_hash' => str_repeat('a', 64), 'recorded_at' => now(), 'received_at' => now(), 'reading_count' => 1]);

        return RawSensorReading::create(['sensor_id' => $r['sensor']->id, 'iot_reading_batch_id' => $batch, 'value' => $value, 'recorded_at' => now()]);
    }

    public function test_history_keeps_raw_percentage_and_provenance_separate(): void
    {
        $r = $this->fixture();
        $this->travel(1)->minutes();
        $raw = $this->raw($r);
        $before = $r['device']->fresh()->only(['last_connection_at', 'last_reading_at']);
        $response = $this->getJson('/api/history?metric=soil_humidity')->assertOk()
            ->assertJsonPath('summary.total_readings', 2)->assertJsonPath('summary.unit', 'mixed')
            ->assertJsonPath('summary.average', null)->assertJsonPath('data.0.unit', '%')->assertJsonPath('data.1.unit', 'raw')
            ->assertJsonPath('data.1.value', 3184)->assertJsonPath('data.1.provenance', 'physical_iot');
        $this->assertNotNull($response->json('data.1.received_at'));
        $this->assertNotSame($response->json('data.0.reading_key'), $response->json('data.1.reading_key'));
        $this->getJson('/api/sensors/'.$r['sensor']->id.'/history?unit=raw')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.value', 3184);
        $this->getJson('/api/sensors/'.$r['sensor']->id.'/soil-moisture/history')->assertOk()->assertJsonPath('data.total_readings', 2)
            ->assertJsonFragment(['unit' => 'raw', 'humidity' => 3184]);
        $this->getJson('/api/greenhouses/'.$r['greenhouse']->id.'/soil-moisture/history')->assertOk()->assertJsonPath('data.total_readings', 2);
        $this->assertEquals($before, $r['device']->fresh()->only(['last_connection_at', 'last_reading_at']));
        $this->assertSame(3184, $raw->fresh()->value);
    }

    public function test_later_calibration_and_unit_changes_do_not_reinterpret_history_or_current_reading(): void
    {
        $r = $this->fixture();
        $this->travel(1)->minutes();
        $raw = $this->raw($r);
        $r['sensor']->update(['unit' => '%', 'calibration' => ['dry' => 3500, 'wet' => 1200]]);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.greenhouses.0.metrics.soil_humidity.unit', 'raw');
        $this->getJson('/api/sensors/'.$r['sensor']->id.'/soil-moisture/current')->assertOk()->assertJsonPath('data.unit', 'raw');
        $this->travel(1)->minutes();
        $percentage = Reading::create(['sensor_id' => $r['sensor']->id, 'value' => 70, 'source' => 'simulation', 'recorded_at' => now()]);
        $r['sensor']->update(['unit' => 'raw', 'calibration' => null]);
        $this->assertSame('raw', $raw->fresh()->unit);
        $this->assertSame('%', $percentage->fresh()->unit);
        $this->assertSame(['dry' => 3500, 'wet' => 1200], $percentage->fresh()->calibration_snapshot);
        $this->getJson('/api/sensors/'.$r['sensor']->id.'/soil-moisture/current')->assertOk()->assertJsonPath('data.unit', '%')->assertJsonPath('data.humidity', 70);
        $this->getJson('/api/history?metric=soil_humidity&unit=%25')->assertOk()->assertJsonPath('summary.total_readings', 2)
            ->assertJsonPath('summary.average', 47.5);
        $this->expectException(\LogicException::class);
        $percentage->forceFill(['unit' => 'raw'])->save();
    }

    public function test_legacy_unverified_source_is_not_upgraded(): void
    {
        $r = $this->fixture();
        $reading = $r['sensor']->readings()->first();
        $reading->update(['source' => 'iot']);
        $r['sensor']->update(['unit' => 'raw']);
        $this->getJson('/api/history?metric=soil_humidity')->assertOk()
            ->assertJsonPath('data.0.unit', '%')->assertJsonPath('data.0.provenance', 'unverified_iot');
        $this->assertNull($reading->fresh()->iot_reading_batch_id);
    }

    public function test_inactive_history_remains_owned_read_only_and_plan_restrictions_are_applied(): void
    {
        $r = $this->fixture();
        $this->raw($r);
        $r['sensor']->update(['status' => 'inactive']);
        $this->getJson('/api/sensors/'.$r['sensor']->id.'/history')->assertOk()->assertJsonPath('summary.total_readings', 2)
            ->assertJsonPath('data.0.sensor.status', 'inactive');
        $r['sensor']->update(['status' => 'active']);
        Plan::where('slug', 'free')->update(['max_sensors_per_zone' => 0]);
        $this->getJson('/api/history?metric=soil_humidity')->assertOk()->assertJsonPath('summary.total_readings', 0);
        $foreign = $this->resources($this->company());
        $this->getJson('/api/sensors/'.$foreign['sensor']->id.'/history')->assertForbidden();
        $this->getJson('/api/history?metric=soil_humidity&sensor_id='.$foreign['sensor']->id)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_reports_and_pdf_separate_mixed_units_and_preserve_no_data(): void
    {
        $r = $this->fixture();
        $this->raw($r);
        $report = $this->getJson('/api/reports?greenhouse_id='.$r['greenhouse']->id)->assertOk()
            ->assertJsonPath('report.metrics.soil_humidity.unit', 'mixed')->assertJsonPath('report.metrics.soil_humidity.average', null)
            ->assertJsonPath('report.metrics.temperature.total_readings', 0)->json('report');
        $this->assertCount(2, $report['metrics']['soil_humidity']['by_unit']);
        $html = view('reports.pdf', compact('report'))->render();
        $this->assertStringContainsString('3184', $html);
        $this->assertStringContainsString('raw', $html);
        $this->get('/api/reports/pdf?greenhouse_id='.$r['greenhouse']->id, ['Accept' => 'application/pdf'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $r['sensor']->readings()->delete();
        $r['sensor']->rawReadings()->delete();
        $this->getJson('/api/history?metric=soil_humidity')->assertOk()->assertJsonPath('summary.total_readings', 0)->assertJsonCount(0, 'data');
    }

    public function test_pro_history_and_reports_respect_company_isolation(): void
    {
        $r = $this->fixture();
        app(SubscriptionContractService::class)->create($r['company'], Plan::where('slug', 'pro')->firstOrFail(), 'monthly', now()->toDateTimeString(), now()->addMonth()->toDateTimeString(), 'history-pro');
        $this->raw($r);
        $foreign = $this->resources($this->company());
        $this->getJson('/api/history?metric=soil_humidity')->assertOk()->assertJsonPath('summary.total_readings', 2);
        $this->getJson('/api/reports?greenhouse_id='.$r['greenhouse']->id)->assertOk()->assertJsonPath('report.metrics.soil_humidity.total_readings', 2);
        $this->getJson('/api/reports?greenhouse_id='.$foreign['greenhouse']->id)->assertStatus(422);
    }

    public function test_large_history_has_bounded_pages_and_full_period_sql_statistics(): void
    {
        $r = $this->fixture();
        $rows = [];
        for ($i = 0; $i < 600; $i++) {
            $rows[] = ['sensor_id' => $r['sensor']->id, 'value' => 50, 'recorded_at' => now()->subSeconds($i),
                'source' => 'simulation', 'measurement_type' => 'soil_humidity', 'unit' => '%', 'metadata_origin' => 'captured'];
        }
        DB::table('readings')->insert($rows);
        $history = app(MeasurementHistory::class);
        $query = $history->query($r['company']->id, 'soil_humidity', now()->subDay(), now());
        DB::enableQueryLog();
        $result = $history->page($query, 2, 200);
        $this->assertCount(200, $result['data']);
        $this->assertSame(601, $result['summary']['total_readings']);
        $this->assertLessThanOrEqual(3, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->getJson('/api/history?start_date=2020-01-01')->assertStatus(422);
    }

    public function test_eighty_two_measurements_have_four_pages_and_unchanged_period_statistics(): void
    {
        $r = $this->fixture();
        for ($i = 1; $i <= 81; $i++) {
            Reading::create(['sensor_id' => $r['sensor']->id, 'value' => $i, 'source' => 'simulation', 'recorded_at' => now()->subSeconds($i)]);
        }
        $filters = http_build_query(['metric' => 'soil_humidity', 'sensor_id' => $r['sensor']->id,
            'greenhouse_id' => $r['greenhouse']->id, 'zone_id' => $r['zone']->id, 'unit' => '%',
            'start_date' => now()->toDateString(), 'end_date' => now()->toDateString()]);
        $keys = [];
        $summary = null;
        $chart = null;
        foreach ([1 => 25, 2 => 25, 3 => 25, 4 => 7] as $page => $count) {
            $response = $this->getJson('/api/history?'.$filters.'&page='.$page)->assertOk()
                ->assertJsonCount($count, 'data')->assertJsonPath('pagination.page', $page)
                ->assertJsonPath('pagination.per_page', 25)->assertJsonPath('pagination.last_page', 4)
                ->assertJsonPath('pagination.total', 82)->assertJsonPath('pagination.from', ($page - 1) * 25 + 1)
                ->assertJsonPath('pagination.to', min(82, $page * 25))->assertJsonPath('chart_scope', 'full_period');
            $chart ??= $response->json('chart');
            $this->assertSame($chart, $response->json('chart'));
            $this->assertCount(82, $chart['data']);
            $summary ??= $response->json('summary');
            $this->assertSame($summary, $response->json('summary'));
            $this->assertSame($r['sensor']->id, $response->json('data.0.sensor_id'));
            array_push($keys, ...array_column($response->json('data'), 'reading_key'));
        }
        $this->assertCount(82, array_unique($keys));
        $this->assertSame(1.0, (float) $summary['minimum']);
        $this->assertSame(81.0, (float) $summary['maximum']);
        $this->assertEqualsWithDelta(40.8, $summary['average'], 0.001);
        $this->getJson('/api/history?metric=temperature')->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('pagination.from', null)->assertJsonPath('pagination.to', null)->assertJsonPath('pagination.last_page', 1);
    }

    public function test_migration_preserves_legacy_percentage_even_when_current_sensor_is_raw(): void
    {
        $r = $this->fixture();
        $r['sensor']->update(['unit' => 'raw']);
        // Reconstruct the pre-migration schema only in SQLite :memory:.
        foreach (['readings', 'raw_sensor_readings'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $columns = ['measurement_type', 'calibration_snapshot', 'interval_snapshot', 'received_at', 'metadata_origin'];
                if ($name === 'readings') {
                    $columns[] = 'unit';
                }
                $table->dropColumn($columns);
            });
        }
        $migration = require database_path('migrations/2026_10_22_040000_preserve_measurement_history.php');
        $migration->up();
        $row = DB::table('readings')->first();
        $this->assertSame('%', $row->unit);
        $this->assertSame('legacy_schema', $row->metadata_origin);
        $this->assertNull($row->calibration_snapshot);
        $this->assertNull($row->received_at);
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_period_chart_is_bounded_preserves_extremes_units_origins_and_does_not_bridge_gaps(): void
    {
        $r = $this->fixture();
        $this->raw($r);
        $rows = [];
        for ($i = 0; $i < 2200; $i++) {
            $rows[] = ['sensor_id' => $r['sensor']->id, 'value' => $i === 1100 ? 99 : ($i === 1101 ? 1 : 50),
                'recorded_at' => now()->subMinutes($i + ($i > 1200 ? 600 : 0)), 'source' => $i < 1000 ? 'simulation' : 'iot',
                'measurement_type' => 'soil_humidity', 'unit' => '%', 'metadata_origin' => 'captured', 'interval_snapshot' => 60];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('readings')->insert($chunk);
        }
        $first = $this->getJson('/api/history?metric=soil_humidity')->assertOk()->assertJsonCount(25, 'data')
            ->assertJsonPath('summary.total_readings', 2202)->assertJsonPath('chart.scope', 'full_period')
            ->assertJsonPath('chart.reduced', true)->json();
        $second = $this->getJson('/api/history?metric=soil_humidity&page=2')->assertOk()->json();
        $this->assertSame($first['chart'], $second['chart']);
        $this->assertSame($first['summary'], $second['summary']);
        $points = collect($first['chart']['data']);
        $this->assertLessThanOrEqual(2000, $points->count());
        $this->assertSame(1.0, (float) $points->where('unit', '%')->min('value'));
        $this->assertSame(99.0, (float) $points->where('unit', '%')->max('value'));
        $this->assertSame(3184, $points->where('unit', 'raw')->first()['value']);
        $this->assertCount(3, $points->pluck('provenance')->unique());
        $this->assertTrue($points->every(fn ($point) => $point['break_before'] === true));
    }
}
