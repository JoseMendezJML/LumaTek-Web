<?php

namespace Tests\Feature;

use App\Models\RawSensorReading;
use App\Models\Reading;
use App\Services\MeasurementSummaries;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class MeasurementSummariesTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase;

    public function test_hourly_daily_extremes_late_readings_and_idempotence(): void
    {
        $this->travelTo('2026-10-23 12:10:00');
        $company = $this->company();
        $r = $this->resources($company);
        Reading::create(['sensor_id' => $r['sensor']->id, 'value' => 95, 'recorded_at' => now()->subMinutes(5)]);
        Reading::create(['sensor_id' => $r['sensor']->id, 'value' => 5, 'recorded_at' => now()->subHour()]);
        $service = app(MeasurementSummaries::class);
        $this->assertSame(3, $service->process($company->id)['processed_readings']);
        $this->assertDatabaseCount('sensor_measurement_summaries', 3);
        $daily = DB::table('sensor_measurement_summaries')->where('granularity', 'day')->first();
        $this->assertEquals(3, $daily->measurement_count);
        $this->assertEquals(5, $daily->minimum);
        $this->assertEquals(95, $daily->maximum);
        $this->assertEqualsWithDelta(125 / 3, $daily->average, 0.000001);
        $this->assertSame(0, $service->process($company->id)['processed_readings']);
        Reading::create(['sensor_id' => $r['sensor']->id, 'value' => 99, 'recorded_at' => '2026-10-23 11:30:00']);
        $this->assertSame(1, $service->process($company->id)['processed_readings']);
        $hour = DB::table('sensor_measurement_summaries')->where('granularity', 'hour')->where('period_start', '2026-10-23 11:00:00')->first();
        $this->assertEquals(2, $hour->measurement_count);
        $this->assertEquals(99, $hour->maximum);
        $this->assertDatabaseCount('readings', 4);
        $this->assertDatabaseCount('measurement_summary_contributions', 4);
    }

    public function test_raw_calibrations_origins_sensors_and_tenants_are_separate(): void
    {
        $this->travelTo('2026-10-23 12:10:00');
        $company = $this->company();
        $r = $this->resources($company);
        $other = $this->resources($this->company());
        $r['sensor']->update(['calibration' => ['dry' => 3500, 'wet' => 1000]]);
        Reading::create(['sensor_id' => $r['sensor']->id, 'value' => 60, 'source' => 'simulation', 'recorded_at' => now()]);
        $r['sensor']->update(['calibration' => ['wet' => 1000, 'dry' => 3500]]);
        Reading::create(['sensor_id' => $r['sensor']->id, 'value' => 80, 'source' => 'simulation', 'recorded_at' => now()]);
        $r['sensor']->update(['calibration' => ['dry' => 3300, 'wet' => 1100]]);
        Reading::create(['sensor_id' => $r['sensor']->id, 'value' => 90, 'source' => 'simulation', 'recorded_at' => now()]);
        Reading::create(['sensor_id' => $r['sensor']->id, 'value' => 85, 'source' => 'iot', 'recorded_at' => now()]);
        $batch = DB::table('iot_reading_batches')->insertGetId(['device_id' => $r['device']->id, 'batch_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'recorded_at' => now(), 'received_at' => now(), 'reading_count' => 1]);
        RawSensorReading::create(['sensor_id' => $r['sensor']->id, 'iot_reading_batch_id' => $batch, 'value' => 3184, 'recorded_at' => now()]);
        $secondSensor = $r['device']->sensors()->create(['name' => 'Second', 'sensor_code' => 'second', 'sensor_type' => 'soil_humidity', 'unit' => '%', 'status' => 'active']);
        Reading::create(['sensor_id' => $secondSensor->id, 'value' => 20, 'recorded_at' => now()]);
        $service = app(MeasurementSummaries::class);
        $service->process($company->id);
        $daily = DB::table('sensor_measurement_summaries')->where('granularity', 'day')->get();
        $this->assertCount(6, $daily);
        $this->assertEquals(7, $daily->sum('measurement_count'));
        $this->assertCount(1, $daily->where('unit', 'raw'));
        $this->assertEquals(3184, $daily->where('unit', 'raw')->first()->average);
        $this->assertSame('physical_iot', $daily->where('unit', 'raw')->first()->provenance);
        $this->assertCount(1, $daily->where('provenance', 'unverified_iot'));
        $canonical = $daily->first(fn ($row) => (int) $row->measurement_count === 2);
        $this->assertEquals(70, $canonical->average);
        $this->assertDatabaseMissing('sensor_measurement_summaries', ['sensor_id' => $other['sensor']->id]);
        $service->process($other['greenhouse']->company_id);
        $this->assertDatabaseHas('sensor_measurement_summaries', ['sensor_id' => $other['sensor']->id, 'measurement_count' => 1]);
        $this->assertDatabaseCount('iot_reading_batches', 1);
    }

    public function test_incremental_batches_empty_periods_and_interruption_rollback(): void
    {
        $this->travelTo('2026-10-23 12:10:00');
        $r = $this->resources($this->company());
        $service = app(MeasurementSummaries::class);
        Reading::create(['sensor_id' => $r['sensor']->id, 'value' => 50, 'recorded_at' => now()->subDays(2)]);
        $companyId = $r['greenhouse']->company_id;
        $this->assertSame(1, $service->process($companyId, 1, 1)['processed_readings']);
        $this->assertDatabaseCount('measurement_summary_contributions', 1);
        DB::statement("CREATE TRIGGER fail_summary_contribution BEFORE INSERT ON measurement_summary_contributions BEGIN SELECT RAISE(ABORT, 'interrupted'); END");
        try {
            $service->process($companyId);
            $this->fail('Expected interrupted transaction');
        } catch (QueryException $e) {
            $this->assertStringContainsString('interrupted', $e->getMessage());
        }
        $this->assertDatabaseCount('sensor_measurement_summaries', 2);
        DB::statement('DROP TRIGGER fail_summary_contribution');
        $this->assertSame(1, $service->process($companyId)['processed_readings']);
        $this->assertDatabaseCount('sensor_measurement_summaries', 4);
        $this->assertDatabaseMissing('sensor_measurement_summaries', ['period_start' => '2026-10-22 00:00:00']);
        $this->assertSame(0, $service->process($this->company()->id)['processed_readings']);
        $this->assertFalse(config('measurement_summaries.schedule_enabled'));
        $this->artisan('history:summarize', ['--company' => $companyId])->expectsOutput('Processed readings: 0')->assertExitCode(0);
    }

    public function test_summary_reader_scopes_company_and_plan_without_switching_history(): void
    {
        $this->travelTo('2026-10-23 12:10:00');
        $r = $this->resources($this->company());
        $service = app(MeasurementSummaries::class);
        $service->process($r['greenhouse']->company_id);
        $query = fn ($company) => $service->authorizedQuery($company, 'day', now()->startOfDay(), now()->addDay()->startOfDay());
        $this->assertSame(1, $query($r['greenhouse']->company_id)->count());
        $this->assertSame(0, $query($this->company()->id)->count());
        $r['sensor']->update(['plan_restricted' => true]);
        $this->assertSame(0, $query($r['greenhouse']->company_id)->count());
    }

    public function test_bounded_incremental_processing_weighted_statistics_and_local_periods(): void
    {
        $this->travelTo('2026-10-23 00:10:00');
        $r = $this->resources($this->company());
        $rows = [];
        $interval = $r['device']->fresh()->measurement_interval;
        for ($i = 0; $i < 600; $i++) {
            $rows[] = ['sensor_id' => $r['sensor']->id, 'value' => 50, 'recorded_at' => '2026-10-22 23:50:00',
                'source' => 'simulation', 'measurement_type' => 'soil_humidity', 'unit' => '%', 'metadata_origin' => 'captured', 'interval_snapshot' => $interval];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('readings')->insert($chunk);
        }
        $service = app(MeasurementSummaries::class);
        $companyId = $r['greenhouse']->company_id;
        $this->assertSame(200, $service->process($companyId, 100, 2)['processed_readings']);
        $this->assertDatabaseCount('measurement_summary_contributions', 200);
        DB::enableQueryLog();
        $this->assertSame(401, $service->process($companyId)['processed_readings']);
        $this->assertLessThan(30, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->assertSame(0, $service->process($companyId)['processed_readings']);
        $this->assertDatabaseHas('sensor_measurement_summaries', ['granularity' => 'day', 'period_start' => '2026-10-22 00:00:00', 'measurement_count' => 600]);
        $query = $service->authorizedQuery($companyId, 'day', now()->subDay()->startOfDay(), now()->addDay()->startOfDay());
        $this->assertEquals(1, (clone $query)->where('m.period_start', '2026-10-22 00:00:00')->first()->period_closed);
        $this->assertEquals(0, (clone $query)->where('m.period_start', '2026-10-23 00:00:00')->first()->period_closed);
        $stats = $service->statistics($companyId, 'day', now()->subDay()->startOfDay(), now()->addDay()->startOfDay());
        $this->assertEquals(601, array_sum(array_column($stats, 'measurement_count')));
        $this->assertCount(1, $stats, json_encode($stats));
        $this->assertEqualsWithDelta(30025 / 601, $stats[0]['average'], 0.00001);
        $this->assertSame(config('app.timezone'), $stats[0]['timezone']);
        $this->assertSame('aggregated_statistics', $stats[0]['data_kind']);
    }

    public function test_later_committed_lower_id_is_not_skipped_and_command_validates_limits(): void
    {
        $this->travelTo('2026-10-23 12:10:00');
        $r = $this->resources($this->company());
        $companyId = $r['greenhouse']->company_id;
        $row = ['sensor_id' => $r['sensor']->id, 'value' => 50, 'recorded_at' => '2026-10-20 12:00:00',
            'source' => 'simulation', 'measurement_type' => 'soil_humidity', 'unit' => '%', 'metadata_origin' => 'captured'];
        DB::table('readings')->insert($row + ['id' => 100]);
        $service = app(MeasurementSummaries::class);
        $this->assertSame(2, $service->process($companyId)['processed_readings']);
        DB::table('readings')->insert($row + ['id' => 50]);
        $this->assertSame(1, $service->process($companyId)['processed_readings']);
        $this->assertSame(0, $service->process($companyId)['processed_readings']);
        $this->assertDatabaseHas('sensor_measurement_summaries', ['granularity' => 'day', 'period_start' => '2026-10-20 00:00:00', 'measurement_count' => 2]);
        $this->artisan('history:summarize', ['--chunk' => 0])->assertExitCode(1);
        $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();
        $this->assertFalse(collect($events)->contains(fn ($event) => str_contains($event->command ?? '', 'history:summarize')));
    }
}
