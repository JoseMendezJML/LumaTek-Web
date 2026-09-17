<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    use HasFactory;

    protected $fillable = [
        'sensor_id',
        'value',
        'recorded_at',
        'source',
    ];

    protected $casts = [
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