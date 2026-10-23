<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Validation\ValidationException;

class IotConfiguration
{
    public function validateSensor(Device $device, array $data, ?Sensor $sensor = null): array
    {
        $fail = fn () => throw ValidationException::withMessages(['pin' => 'Modelo, medición, pin o calibración incompatible.']);
        $model = $data['model'] ?? null;
        $pin = $data['pin'] ?? null;
        $type = $data['sensor_type'];
        if ($device->board_model !== 'esp32_devkit_v1' || $pin === null) {
            $fail();
        }
        $pin = (int) $pin;
        if (! (($model === 'DHT22' && in_array($type, ['temperature', 'ambient_humidity']) && $pin === 4)
            || ($model === 'analog_soil' && $type === 'soil_humidity' && in_array($pin, [32, 33, 34, 35, 36, 39])))) {
            $fail();
        }
        foreach ($device->sensors()->where('pin', $pin)->when($sensor, fn ($q) => $q->where('id', '!=', $sensor->id))->get() as $other) {
            if ($model !== 'DHT22' || $other->model !== 'DHT22' || $other->sensor_type === $type) {
                $fail();
            }
        }
        $calibration = $data['calibration'] ?? null;
        if ($calibration !== null && ($model !== 'analog_soil' || ! is_array($calibration)
            || array_diff(array_keys($calibration), ['dry', 'wet'])
            || ! isset($calibration['dry'], $calibration['wet'])
            || ! is_numeric($calibration['dry']) || ! is_numeric($calibration['wet'])
            || $calibration['dry'] < 0 || $calibration['dry'] > 4095
            || $calibration['wet'] < 0 || $calibration['wet'] > 4095
            || $calibration['dry'] == $calibration['wet'])) {
            $fail();
        }

        return ['pin' => $pin, 'calibration' => $calibration, 'unit' => $type === 'temperature' ? '°C' : ($type === 'soil_humidity' && $calibration === null ? 'raw' : '%')];
    }

    public function payload(Device $device): array
    {
        $sensors = $device->sensors()->where('status', 'active')->where('plan_restricted', false)->orderBy('id')->get();
        $items = [];
        foreach ($sensors as $sensor) {
            $settings = $this->validateSensor($device, $sensor->toArray(), $sensor);
            if ($sensor->unit !== $settings['unit']) {
                throw ValidationException::withMessages(['unit' => 'Unidad incompatible con la calibración.']);
            }
            $items[] = ['id' => $sensor->id, 'driver' => $sensor->model, 'pin' => $sensor->pin, 'measurement' => $sensor->sensor_type, 'unit' => $sensor->unit, 'calibration' => $sensor->calibration];
        }
        $payload = ['device_id' => $device->device_code, 'board_model' => $device->board_model, 'measurement_interval' => $device->measurement_interval, 'sensors' => $items];
        $payload['configuration_version'] = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return $payload;
    }
}
