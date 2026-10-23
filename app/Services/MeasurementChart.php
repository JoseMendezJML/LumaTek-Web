<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Detailed readings adapter; a future summary adapter can retain this response contract. */
class MeasurementChart
{
    public const MAX_POINTS = 2000;

    public function period(Builder $query, Carbon $start, Carbon $end, int $total): array
    {
        $history = app(MeasurementHistory::class);
        $reduced = $total > self::MAX_POINTS;
        $bucketSeconds = null;
        $selected = clone $query;
        if ($reduced) {
            $origin = "CASE WHEN source = 'iot' AND iot_reading_batch_id IS NOT NULL THEN 'physical_iot' WHEN source = 'simulation' THEN 'manual_or_simulated' ELSE 'unverified_iot' END";
            $keys = "sensor_id, unit, measurement_type, $origin, CAST(calibration_snapshot AS CHAR), interval_snapshot";
            $series = (clone $query)->selectRaw($keys)->distinct()->limit(501)->get()->count();
            if ($series > 500) {
                throw ValidationException::withMessages(['sensor_id' => 'Selecciona menos sensores o un periodo menor: hay demasiadas series historicas para graficar sin omitirlas.']);
            }
            $buckets = max(1, (int) floor(self::MAX_POINTS / (4 * max(1, $series))));
            $bucketSeconds = max(1, (int) ceil(($end->getTimestamp() - $start->getTimestamp() + 1) / $buckets));
            $epoch = DB::connection()->getDriverName() === 'sqlite'
                ? "CAST(strftime('%s', recorded_at) AS INTEGER)" : 'UNIX_TIMESTAMP(recorded_at)';
            $division = '('.$epoch.' - '.$start->getTimestamp().') / '.$bucketSeconds;
            $bucket = DB::connection()->getDriverName() === 'sqlite' ? 'CAST(('.$division.') AS INTEGER)' : 'FLOOR('.$division.')';
            $partition = $keys.', '.$bucket;
            $ranked = (clone $query)->selectRaw('measurements.*');
            foreach (['first' => 'recorded_at ASC, stream ASC, id ASC', 'last' => 'recorded_at DESC, stream DESC, id DESC',
                'minimum' => 'value ASC, recorded_at ASC, stream ASC, id ASC', 'maximum' => 'value DESC, recorded_at ASC, stream ASC, id ASC'] as $name => $order) {
                $ranked->selectRaw("ROW_NUMBER() OVER (PARTITION BY $partition ORDER BY $order) as chart_$name");
            }
            $selected = DB::query()->fromSub($ranked, 'ranked')->where(function ($q) {
                foreach (['first', 'last', 'minimum', 'maximum'] as $name) {
                    $q->orWhere('chart_'.$name, 1);
                }
            });
        }
        $points = $selected->orderBy('recorded_at')->orderBy('stream')->orderBy('id')->get()
            ->map(fn ($row) => $history->format($row) + ['break_before' => $reduced])->all();

        return ['scope' => 'full_period', 'start' => $start->toIso8601String(), 'end' => $end->toIso8601String(),
            'max_points' => self::MAX_POINTS, 'reduced' => $reduced, 'bucket_seconds' => $bucketSeconds,
            'method' => $reduced ? 'first_last_min_max' : 'detailed', 'data' => $points];
    }
}
