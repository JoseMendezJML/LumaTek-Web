<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'zone_id',
        'name',
        'device_code',
        'device_type',
        'connection_type',
        'status',
        'last_connection_at',
    ];

    protected $casts = [
        'last_connection_at' => 'datetime',
    ];

    /**
     * Zona donde está instalado el dispositivo.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Sensores conectados al dispositivo.
     */
    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class);
    }
}