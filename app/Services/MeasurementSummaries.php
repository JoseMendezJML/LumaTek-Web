<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MeasurementSummaries
{
    /** Each bounded transaction commits both granularities and the contribution ledger. */
    public function process(int $companyId, int $chunk = 500, int $maxBatches = 10): array
    {
        if ($chunk < 1 || $chunk > 500 || $maxBatches < 1 || $maxBatches > 10000) {
            throw new InvalidArgumentException('Invalid summary processing limits.');
        }
        $processed = 0;
        foreach (['readings', 'raw_sensor_readings'] as $stream) {
            $scanAfter = 0;
            for ($batch = 0; $batch < $maxBatches; $batch++) {
                $count = DB::transaction(function () use ($companyId, $stream, $chunk, $scanAfter) {
                    // Serialize workers for this tenant; other tenants may run independently.
                    DB::table('companies')->where('id', $companyId)->lockForUpdate()->firstOrFail();
                    $rows = DB::table($stream.' as r')->join('sensors as s', 's.id', '=', 'r.sensor_id')
                        ->join('devices as d', 'd.id', '=', 's.device_id')->join('zones as z', 'z.id', '=', 'd.zone_id')
                        ->join('greenhouses as g', 'g.id', '=', 'z.greenhouse_id')->where('g.company_id', $companyId)->where('r.id', '>', $scanAfter)
                        ->whereNotExists(function ($q) use ($stream) {
                            $q->selectRaw('1')->from('measurement_summary_contributions as c')
                                ->where('c.stream', $stream)->whereColumn('c.reading_id', 'r.id');
                        })->select('r.*', 'g.id as greenhouse_id')->orderBy('r.id')->limit($chunk)->get();
                    $groups = [];
                    $ledger = [];
                    foreach ($rows as $row) {
                        $calibration = $this->canonical(json_decode($row->calibration_snapshot ?? 'null', true));
                        $calibrationJson = $calibration === null ? null : json_encode($calibration, JSON_THROW_ON_ERROR);
                        $calibrationKey = hash('sha256', $calibrationJson ?? 'null');
                        $provenance = $row->source === 'iot' && $row->iot_reading_batch_id !== null ? 'physical_iot'
                            : ($row->source === 'simulation' ? 'manual_or_simulated' : 'unverified_iot');
                        $series = hash('sha256', json_encode([$companyId, $row->greenhouse_id, $row->sensor_id,
                            $row->measurement_type, $row->unit, $provenance, $calibrationKey, $row->metadata_origin, $row->interval_snapshot, config('app.timezone')], JSON_THROW_ON_ERROR));
                        foreach (['hour', 'day'] as $granularity) {
                            // Follow the existing SQL timestamp convention: application-local, half-open [start,end).
                            $start = Carbon::parse($row->recorded_at, config('app.timezone'));
                            $granularity === 'hour' ? $start->startOfHour() : $start->startOfDay();
                            $end = $granularity === 'hour' ? $start->copy()->addHour() : $start->copy()->addDay();
                            $key = $granularity.':'.$start->toDateTimeString().':'.$series;
                            if (! isset($groups[$key])) {
                                $groups[$key] = ['company_id' => $companyId, 'greenhouse_id' => $row->greenhouse_id, 'sensor_id' => $row->sensor_id,
                                    'granularity' => $granularity, 'timezone' => config('app.timezone'), 'period_start' => $start->toDateTimeString(), 'period_end' => $end->toDateTimeString(),
                                    'measurement_type' => $row->measurement_type, 'unit' => $row->unit, 'provenance' => $provenance,
                                    'calibration_key' => $calibrationKey, 'calibration_snapshot' => $calibrationJson, 'series_key' => $series,
                                    'metadata_origin' => $row->metadata_origin, 'interval_snapshot' => $row->interval_snapshot,
                                    'minimum' => (float) $row->value, 'maximum' => (float) $row->value, 'value_sum' => 0, 'average' => 0,
                                    'measurement_count' => 0, 'first_measured_at' => $row->recorded_at, 'last_measured_at' => $row->recorded_at,
                                    'created_at' => now(), 'updated_at' => now()];
                            }
                            $g = &$groups[$key];
                            $g['minimum'] = min($g['minimum'], (float) $row->value);
                            $g['maximum'] = max($g['maximum'], (float) $row->value);
                            $g['value_sum'] += (float) $row->value;
                            $g['measurement_count']++;
                            $g['first_measured_at'] = min($g['first_measured_at'], $row->recorded_at);
                            $g['last_measured_at'] = max($g['last_measured_at'], $row->recorded_at);
                            unset($g);
                        }
                        $ledger[] = ['company_id' => $companyId, 'stream' => $stream, 'reading_id' => $row->id, 'processed_at' => now()];
                    }
                    $existingGroups = [];
                    // Bounded OR batches avoid per-summary queries and SQLite parameter limits.
                    foreach (array_chunk(array_values($groups), 100) as $batchGroups) {
                        $existingRows = DB::table('sensor_measurement_summaries')->where('company_id', $companyId)
                            ->where(function ($q) use ($batchGroups) {
                                foreach ($batchGroups as $g) {
                                    $q->orWhere(function ($match) use ($g) {
                                        $match->where('granularity', $g['granularity'])->where('period_start', $g['period_start'])->where('series_key', $g['series_key']);
                                    });
                                }
                            })->get();
                        foreach ($existingRows as $existing) {
                            $existingGroups[$existing->granularity.':'.$existing->period_start.':'.$existing->series_key] = $existing;
                        }
                    }
                    foreach ($groups as &$group) {
                        $key = $group['granularity'].':'.$group['period_start'].':'.$group['series_key'];
                        $existing = $existingGroups[$key] ?? null;
                        if ($existing) {
                            $group['minimum'] = min($group['minimum'], (float) $existing->minimum);
                            $group['maximum'] = max($group['maximum'], (float) $existing->maximum);
                            $group['value_sum'] += (float) $existing->value_sum;
                            $group['measurement_count'] += $existing->measurement_count;
                            $group['first_measured_at'] = min($group['first_measured_at'], $existing->first_measured_at);
                            $group['last_measured_at'] = max($group['last_measured_at'], $existing->last_measured_at);
                            $group['created_at'] = $existing->created_at;
                        }
                        $group['average'] = $group['value_sum'] / $group['measurement_count'];

                    }
                    unset($group);
                    foreach (array_chunk(array_values($groups), 100) as $batchGroups) {
                        DB::table('sensor_measurement_summaries')->upsert($batchGroups,
                            ['company_id', 'granularity', 'period_start', 'series_key'],
                            array_values(array_diff(array_keys($batchGroups[0]), ['created_at', 'company_id', 'granularity', 'period_start', 'series_key'])));
                    }
                    if ($ledger) {
                        DB::table('measurement_summary_contributions')->insert($ledger);
                    }

                    return ['count' => $rows->count(), 'last_id' => $rows->last()?->id ?? $scanAfter];
                }, 3);
                $scanAfter = $count['last_id'];
                $processed += $count['count'];
                if ($count['count'] < $chunk) {
                    break;
                }
            }
        }

        return ['processed_readings' => $processed];
    }

    /** Explicit opt-in future read adapter. No existing history/report switches to it yet. */
    public function authorizedQuery(int $companyId, string $granularity, Carbon $start, Carbon $end): Builder
    {
        $start = $start->copy()->setTimezone(config('app.timezone'));
        $end = $end->copy()->setTimezone(config('app.timezone'));
        if (! in_array($granularity, ['hour', 'day']) || $start->gte($end)
            || ! $start->equalTo($granularity === 'hour' ? $start->copy()->startOfHour() : $start->copy()->startOfDay())
            || ! $end->equalTo($granularity === 'hour' ? $end->copy()->startOfHour() : $end->copy()->startOfDay())) {
            throw new InvalidArgumentException('Summary ranges must be aligned application-timezone half-open periods.');
        }

        return DB::table('sensor_measurement_summaries as m')->join('sensors as s', 's.id', '=', 'm.sensor_id')
            ->join('devices as d', 'd.id', '=', 's.device_id')->join('zones as z', 'z.id', '=', 'd.zone_id')
            ->join('greenhouses as g', 'g.id', '=', 'm.greenhouse_id')->where('m.company_id', $companyId)->where('g.company_id', $companyId)
            ->whereColumn('z.greenhouse_id', 'g.id')->where('g.plan_restricted', false)->where('z.plan_restricted', false)->where('s.plan_restricted', false)
            ->where('m.granularity', $granularity)->where('m.period_start', '>=', $start)->where('m.period_end', '<=', $end)
            ->select('m.*')->selectRaw('? as data_kind', ['aggregated_statistics'])
            ->selectRaw('CASE WHEN m.period_end <= ? THEN 1 ELSE 0 END as period_closed', [now()->setTimezone(config('app.timezone'))->toDateTimeString()]);
    }

    /** Weighted statistics for future history/report consumers; never average averages. */
    public function statistics(int $companyId, string $granularity, Carbon $start, Carbon $end): array
    {
        $keys = ['m.series_key', 'm.greenhouse_id', 'm.sensor_id', 'm.measurement_type', 'm.unit', 'm.provenance', 'm.calibration_key', 'm.timezone'];

        return $this->authorizedQuery($companyId, $granularity, $start, $end)->select($keys)
            ->selectRaw('MIN(m.minimum) as minimum, MAX(m.maximum) as maximum, SUM(m.measurement_count) as measurement_count, SUM(m.value_sum) as value_sum')
            ->selectRaw('1.0 * SUM(m.value_sum) / SUM(m.measurement_count) as average')
            ->groupBy($keys)->orderBy('m.series_key')->get()->map(fn ($row) => (array) $row + ['data_kind' => 'aggregated_statistics'])->all();
    }

    private function canonical(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as &$item) {
                $item = $this->canonical($item);
            }
        }

        return $value;
    }
}
