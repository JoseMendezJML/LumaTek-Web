<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Reading;
use App\Models\Sensor;
use Illuminate\Database\Eloquent\Builder;

class ReadingAlertEvaluator
{
    // Ingestion owns the company/device/sensor locks. Episodes are identified
    // from their first reading and subsequent recoveries, without rewriting history.
    public function evaluate(Sensor $sensor, Reading $reading): ?Alert
    {
        if ($reading->unit === 'raw' || ! in_array($reading->provenance, ['physical_iot', 'manual_or_simulated'])) {
            return null;
        }
        $threshold = $sensor->device->zone->greenhouse->thresholds()->where('variable', $sensor->sensor_type)->first();
        if (! $threshold || $threshold->unit !== $reading->unit) {
            return null;
        }
        $scope = fn () => $this->readings($sensor, $reading);
        if ($scope()->where(function ($q) use ($reading) {
            $q->where('recorded_at', '>', $reading->recorded_at)
                ->orWhere(fn ($q) => $q->where('recorded_at', $reading->recorded_at)->where('id', '>', $reading->id));
        })->exists()) {
            return null; // Late history must not change a newer episode.
        }
        $types = match ($sensor->sensor_type) {
            'temperature' => ['high_temperature', 'low_temperature'],
            'ambient_humidity' => ['high_ambient_humidity'],
            'soil_humidity' => ['low_soil_humidity'],
            default => [],
        };
        $high = $threshold->max_value !== null && (float) $reading->value > (float) $threshold->max_value;
        $low = $threshold->min_value !== null && (float) $reading->value < (float) $threshold->min_value;
        $target = match ($sensor->sensor_type) {
            'temperature' => $high ? 'high_temperature' : ($low ? 'low_temperature' : null),
            'ambient_humidity' => $high ? 'high_ambient_humidity' : null,
            'soil_humidity' => $low ? 'low_soil_humidity' : null,
            default => null,
        };
        $alerts = fn (string $type) => $sensor->alerts()->where('type', $type)
            ->whereHas('reading', fn ($q) => $this->provenance($q, $reading));
        foreach ($types as $type) {
            if ($type !== $target) {
                $alerts($type)->whereIn('status', ['active', 'acknowledged'])->update(['status' => 'resolved', 'resolved_at' => now()]);
            }
        }
        if ($target === null) {
            return null;
        }
        $type = $target;
        $last = $alerts($type)->with('reading')->orderByDesc('id')->first();
        if ($last) {
            $recovered = $scope()->where(function ($q) use ($last) {
                $q->where('recorded_at', '>', $last->reading->recorded_at)
                    ->orWhere(fn ($q) => $q->where('recorded_at', $last->reading->recorded_at)->where('id', '>', $last->reading_id));
            })->where('recorded_at', '<=', $reading->recorded_at);
            $recovered = str_starts_with($type, 'high_')
                ? $recovered->where('value', '<=', $threshold->max_value)
                : $recovered->where('value', '>=', $threshold->min_value);
            if (! $recovered->exists()) {
                return $last; // Includes a manually resolved, still abnormal episode.
            }
            $alerts($type)->whereIn('status', ['active', 'acknowledged'])->update(['status' => 'resolved', 'resolved_at' => now()]);
        }

        return $sensor->alerts()->create([
            'reading_id' => $reading->id, 'type' => $type, 'severity' => 'warning', 'status' => 'active',
            'message' => match ($type) {
                'high_temperature' => 'La temperatura superó el límite máximo configurado.',
                'low_temperature' => 'La temperatura está por debajo del límite mínimo configurado.',
                'high_ambient_humidity' => 'La humedad ambiental superó el umbral máximo configurado.',
                default => 'La humedad del suelo está por debajo del umbral mínimo configurado.',
            },
        ]);

    }

    private function readings(Sensor $sensor, Reading $reading): Builder
    {
        $query = Reading::where('sensor_id', $sensor->id)->where('measurement_type', $reading->measurement_type)->where('unit', $reading->unit);
        $this->provenance($query, $reading);

        return $query;
    }

    private function provenance(Builder $query, Reading $reading): void
    {
        if ($reading->is_physical) {
            $query->where('source', 'iot')->whereNotNull('iot_reading_batch_id');
        } else {
            $query->where('source', 'simulation')->whereNull('iot_reading_batch_id');
        }
    }
}
