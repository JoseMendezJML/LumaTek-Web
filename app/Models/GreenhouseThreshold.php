<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GreenhouseThreshold extends Model
{
    use HasFactory;

    protected $fillable = [
        'greenhouse_id',
        'variable',
        'min_value',
        'max_value',
        'unit',
        'is_default',
    ];

    protected $casts = [
        'min_value' => 'decimal:2',
        'max_value' => 'decimal:2',
        'is_default' => 'boolean',
    ];

    /**
     * Invernadero al que pertenece el umbral.
     */
    public function greenhouse(): BelongsTo
    {
        return $this->belongsTo(Greenhouse::class);
    }
}