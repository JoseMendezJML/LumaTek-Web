<?php

namespace App\Services;

use App\Models\RawSensorReading;
use App\Models\Sensor;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeasurementHistory
{
    public function query(int $companyId, string $metric, Carbon $start, Carbon $end, array $filters = [], ?int $maxDays = 93): Builder
    {
        if ($start->gt($end)) {
            throw ValidationException::withMessages(['end_date' => 'La fecha final debe ser posterior o igual a la inicial.']);
        }
        if ($maxDays !== null && $start->diffInDays($end) > $maxDays) {
            throw ValidationException::withMessages(['end_date' => 'Selecciona un período válido de hasta 93 días.']);
        }
        $streams = [];
        foreach (['readings', 'raw_sensor_readings'] as $table) {
            $q = DB::table($table.' as r')->join('sensors as s', 's.id', '=', 'r.sensor_id')
                ->join('devices as d', 'd.id', '=', 's.device_id')->join('zones as z', 'z.id', '=', 'd.zone_id')
                ->join('greenhouses as g', 'g.id', '=', 'z.greenhouse_id')
                ->where('g.company_id', $companyId)->where('s.plan_restricted', false)
                ->where('z.plan_restricted', false)->where('g.plan_restricted', false)
                ->where('r.measurement_type', $metric)->whereBetween('r.recorded_at', [$start, $end]);
            foreach (['greenhouse_id' => 'g.id', 'zone_id' => 'z.id', 'sensor_id' => 's.id'] as $key => $column) {
                if (! empty($filters[$key])) {
                    $q->where($column, $filters[$key]);
                }
            }
            if (! empty($filters['unit'])) {
                $q->whereRaw("COALESCE(r.unit, 'unknown') = ?", [$filters['unit']]);
            }
            $q->select(['r.id', 'r.sensor_id', 'r.value', 'r.unit', 'r.measurement_type', 'r.recorded_at', 'r.received_at',
                'r.source', 'r.iot_reading_batch_id', 'r.calibration_snapshot', 'r.interval_snapshot', 'r.metadata_origin',
                's.name as sensor_name', 's.sensor_code', 's.status as sensor_status', 'd.id as device_id', 'd.name as device_name', 'd.device_code',
                'z.id as zone_id', 'z.name as zone_name', 'g.id as greenhouse_id', 'g.name as greenhouse_name'])
                ->selectRaw('? as stream', [$table]);
            $streams[] = $q;
        }

        return DB::query()->fromSub($streams[0]->unionAll($streams[1]), 'measurements');
    }

    public function summary(Builder $query): array
    {
        $units = (clone $query)->selectRaw("COALESCE(unit, 'unknown') as unit, COUNT(*) as total_readings, MIN(value) as minimum, MAX(value) as maximum, AVG(value) as average")
            ->selectRaw("SUM(CASE WHEN source = 'iot' AND iot_reading_batch_id IS NOT NULL THEN 1 ELSE 0 END) as physical_iot")
            ->selectRaw("SUM(CASE WHEN source = 'simulation' THEN 1 ELSE 0 END) as manual_or_simulated")
            ->groupBy('unit')->orderBy('unit')->get()->map(function ($row) {
                return ['unit' => $row->unit, 'total_readings' => (int) $row->total_readings,
                    'minimum' => round((float) $row->minimum, 2), 'maximum' => round((float) $row->maximum, 2), 'average' => round((float) $row->average, 2),
                    'provenance_counts' => ['physical_iot' => (int) $row->physical_iot, 'manual_or_simulated' => (int) $row->manual_or_simulated,
                        'unverified_iot' => (int) $row->total_readings - (int) $row->physical_iot - (int) $row->manual_or_simulated]];
            })->all();
        $single = count($units) === 1 ? $units[0] : [];

        return ['total_readings' => array_sum(array_column($units, 'total_readings')), 'unit' => $single['unit'] ?? (count($units) ? 'mixed' : null),
            'minimum' => $single['minimum'] ?? null, 'maximum' => $single['maximum'] ?? null, 'average' => $single['average'] ?? null, 'by_unit' => $units];
    }

    public function page(Builder $query, int $page = 1, int $perPage = 25): array
    {
        $summary = $this->summary($query);
        $total = $summary['total_readings'];
        $rows = (clone $query)->orderBy('recorded_at')->orderBy('stream')->orderBy('id')
            ->offset(($page - 1) * $perPage)->limit($perPage)->get()->map(fn ($row) => $this->format($row))->all();

        return ['summary' => $summary, 'data' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total,
            'from' => count($rows) ? ($page - 1) * $perPage + 1 : null,
            'to' => count($rows) ? ($page - 1) * $perPage + count($rows) : null,
            'last_page' => max(1, (int) ceil($total / $perPage))], 'chart_scope' => 'current_page'];
    }

    public function format(object $row): array
    {
        $physical = $row->source === 'iot' && $row->iot_reading_batch_id !== null;
        $provenance = $physical ? 'physical_iot' : ($row->source === 'simulation' ? 'manual_or_simulated' : 'unverified_iot');

        return ['id' => (int) $row->id, 'reading_key' => $row->stream.':'.$row->id, 'sensor_id' => (int) $row->sensor_id,
            'value' => $row->unit === 'raw' ? (int) $row->value : (float) $row->value, 'unit' => $row->unit ?? 'unknown',
            'measurement_type' => $row->measurement_type, 'recorded_at' => $row->recorded_at,
            'recorded_at_iso' => Carbon::parse($row->recorded_at)->utc()->toIso8601String(), 'received_at' => $row->received_at,
            'source' => $row->source, 'provenance' => $provenance, 'source_label' => match ($provenance) {
                'physical_iot' => 'IoT físico', 'manual_or_simulated' => 'Simulación / manual', default => 'IoT no verificado',
            }, 'calibration_snapshot' => $row->calibration_snapshot ? json_decode($row->calibration_snapshot, true) : null,
            'interval_snapshot' => $row->interval_snapshot ? (int) $row->interval_snapshot : null, 'metadata_origin' => $row->metadata_origin,
            'sensor' => ['id' => (int) $row->sensor_id, 'name' => $row->sensor_name, 'code' => $row->sensor_code,
                'type' => $row->measurement_type, 'unit' => $row->unit ?? 'unknown', 'status' => $row->sensor_status],
            'device' => ['id' => (int) $row->device_id, 'name' => $row->device_name, 'code' => $row->device_code],
            'zone' => ['id' => (int) $row->zone_id, 'name' => $row->zone_name],
            'greenhouse' => ['id' => (int) $row->greenhouse_id, 'name' => $row->greenhouse_name]];
    }

    public function latest(Sensor $sensor)
    {
        $sensor->loadMissing(['latestReading', 'latestRawReading']);

        return collect([$sensor->latestReading, $sensor->latestRawReading])->filter(fn ($reading) => $reading
            && $reading->measurement_type === $sensor->sensor_type)->sort(function ($a, $b) {
                $key = fn ($r) => [$r->recorded_at->getTimestamp(), $r->received_at?->getTimestamp() ?? 0,
                    $r instanceof RawSensorReading ? 1 : 0];

                return $key($b) <=> $key($a);
            })->first();
    }
}
