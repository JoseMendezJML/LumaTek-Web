<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sensor extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'name',
        'sensor_code',
        'sensor_type',
        'unit',
        'model',
        'position_x',
        'position_y',
        'status',
    ];

    protected $casts = [
        'position_x' => 'decimal:2',
        'position_y' => 'decimal:2',
    ];

    /**
     * Dispositivo al que pertenece el sensor.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Todas las lecturas registradas por el sensor.
     */
    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    /**
     * Última lectura registrada.
     *
     * Nos servirá para mostrar la temperatura actual
     * sin tener que consultar manualmente todas las lecturas.
     */
    public function latestReading(): HasOne
    {
        return $this->hasOne(Reading::class)
            ->latestOfMany('recorded_at');
    }

    /**
     * Alertas relacionadas con el sensor.
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}