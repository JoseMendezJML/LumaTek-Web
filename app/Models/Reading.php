<?php

namespace App\Models;

use App\Models\Concerns\HasReadingProvenance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    use HasFactory;
    use HasReadingProvenance;
    use \App\Models\Concerns\HasMeasurementSnapshot;

    protected $appends = ['provenance', 'source_label', 'is_physical'];

    protected $fillable = [
        'iot_reading_batch_id',
        'sensor_id',
        'value',
        'recorded_at',
        'source',
    ];

    protected $casts = ['calibration_snapshot' => 'array', 'received_at' => 'datetime', 'interval_snapshot' => 'integer',
        'value' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    /**
     * Sensor que generó esta lectura.
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }
}
