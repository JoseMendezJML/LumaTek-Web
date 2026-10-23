<?php

namespace App\Services;

use App\Models\Device;
use App\Models\RawSensorReading;
use App\Models\Reading;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IotReadings
{
    // Caller owns company/device locks and the surrounding transaction.
    public function store(Device $device, array $data): array
    {
        $items = collect($data['readings'])->sortBy('sensor_id')->values()->all();
        $timestamp = isset($data['recorded_at']) ? CarbonImmutable::parse($data['recorded_at'])->utc() : null;
        $canonical = ['recorded_at' => $timestamp?->toIso8601String(), 'readings' => array_map(fn ($item) => [
            'sensor_id' => (int) $item['sensor_id'], 'measurement' => $item['measurement'], 'unit' => $item['unit'], 'value' => (float) $item['value'],
        ], $items)];
        $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        $batchId = strtolower($data['batch_id']);
        $existing = DB::table('iot_reading_batches')->where('device_id', $device->id)->where('batch_id', $batchId)->first();
        if ($existing) {
            abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'El identificador del lote ya tiene otro contenido.');
            $device->forceFill(['last_connection_at' => now()])->save();

            return ['batch_id' => $batchId, 'accepted' => (int) $existing->reading_count, 'duplicate' => true];
        }
        // Existing Eloquent timestamps use the application's timezone in SQL.
        // Normalize the UTC wire timestamp before persisting to avoid shifting
        // dates when Eloquent hydrates the timezone-less DATETIME/TIMESTAMP.
        $recordedAt = ($timestamp ?? now()->toImmutable())->setTimezone(config('app.timezone'))->startOfSecond();
        if ($recordedAt->gt(now()->addMinutes(5)) || $recordedAt->lt(now()->subDay())) {
            throw ValidationException::withMessages(['recorded_at' => 'La fecha debe estar dentro de las últimas 24 horas y no más de cinco minutos en el futuro.']);
        }
        $sensors = $device->sensors()->whereIn('id', array_column($items, 'sensor_id'))->lockForUpdate()->get()->keyBy('id');
        foreach ($items as $index => $item) {
            $sensor = $sensors->get($item['sensor_id']);
            if (! $sensor || $sensor->status !== 'active' || $sensor->plan_restricted) {
                throw ValidationException::withMessages(["readings.$index.sensor_id" => 'Sensor no autorizado o no disponible.']);
            }
            $settings = app(IotConfiguration::class)->validateSensor($device, $sensor->toArray(), $sensor);
            $value = (float) $item['value'];
            $validValue = match ($sensor->sensor_type) {
                'temperature' => $value >= -40 && $value <= 80,
                'ambient_humidity' => $value >= 0 && $value <= 100,
                'soil_humidity' => $sensor->unit === 'raw' ? $value >= 0 && $value <= 4095 && floor($value) === $value : $value >= 0 && $value <= 100,
                default => false,
            };
            if (! is_finite($value) || ! $validValue || $sensor->unit !== $settings['unit']
                || $item['unit'] !== $sensor->unit || $item['measurement'] !== $sensor->sensor_type) {
                throw ValidationException::withMessages(["readings.$index" => 'Tipo, unidad o valor incompatible con el sensor configurado.']);
            }
        }
        $id = DB::table('iot_reading_batches')->insertGetId([
            'device_id' => $device->id, 'batch_id' => $batchId, 'payload_hash' => $hash,
            'recorded_at' => $recordedAt, 'received_at' => now(), 'reading_count' => count($items),
        ]);
        foreach ($items as $item) {
            $sensor = $sensors->get($item['sensor_id']);
            $values = ['sensor_id' => $sensor->id, 'iot_reading_batch_id' => $id, 'value' => $item['value'], 'recorded_at' => $recordedAt];
            if ($sensor->unit === 'raw') {
                RawSensorReading::create($values);
            } else {
                $reading = Reading::create($values + ['source' => 'iot']);
                app(ReadingAlertEvaluator::class)->evaluate($sensor, $reading);
            }
        }
        // A future clock within tolerance must not extend the online window.
        $communicationAt = $recordedAt->min(now()->toImmutable());
        $device->forceFill([
            'last_connection_at' => now(),
            'last_reading_at' => $device->last_reading_at && $device->last_reading_at->gt($communicationAt) ? $device->last_reading_at : $communicationAt,
        ])->save();

        return ['batch_id' => $batchId, 'accepted' => count($items), 'duplicate' => false];
    }
}
