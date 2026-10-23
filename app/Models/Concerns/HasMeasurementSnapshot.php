<?php

namespace App\Models\Concerns;

use App\Models\RawSensorReading;
use App\Models\Sensor;
use LogicException;

trait HasMeasurementSnapshot
{
    protected static function bootHasMeasurementSnapshot(): void
    {
        static::creating(function ($reading) {
            $sensor = Sensor::with('device')->findOrFail($reading->sensor_id);
            $raw = $reading instanceof RawSensorReading;
            $reading->measurement_type = $raw ? 'soil_humidity' : $sensor->sensor_type;
            $reading->unit = $raw ? 'raw' : $sensor->unit;
            $reading->calibration_snapshot = $raw ? null : $sensor->calibration;
            $reading->interval_snapshot = $sensor->device->measurement_interval;
            $reading->received_at = now();
            $reading->metadata_origin = 'captured';
        });
        static::updating(function ($reading) {
            if ($reading->isDirty(['measurement_type', 'unit', 'calibration_snapshot', 'interval_snapshot', 'received_at', 'metadata_origin'])) {
                throw new LogicException('Historical measurement metadata is immutable.');
            }
        });
    }
}
