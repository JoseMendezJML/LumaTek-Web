<?php

namespace App\Models;

use App\Models\Concerns\HasReadingProvenance;
use Illuminate\Database\Eloquent\Model;

class RawSensorReading extends Model
{
    use HasReadingProvenance;
    use \App\Models\Concerns\HasMeasurementSnapshot;

    protected $appends = ['provenance', 'source_label', 'is_physical'];

    protected $fillable = ['sensor_id', 'iot_reading_batch_id', 'value', 'recorded_at'];

    protected $casts = ['calibration_snapshot' => 'array', 'received_at' => 'datetime', 'interval_snapshot' => 'integer', 'value' => 'integer', 'recorded_at' => 'datetime'];
}
