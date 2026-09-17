<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = [
        'greenhouse_id',
        'name',
        'description',
        'position_x',
        'position_y',
        'status',
    ];

    protected $casts = [
        'position_x' => 'decimal:2',
        'position_y' => 'decimal:2',
    ];

    /**
     * Invernadero al que pertenece la zona.
     */
    public function greenhouse(): BelongsTo
    {
        return $this->belongsTo(Greenhouse::class);
    }

    /**
     * Dispositivos instalados en esta zona.
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}