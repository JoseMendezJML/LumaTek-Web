<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IrrigationEvent extends Model
{
    use HasFactory;

    /**
     * Campos permitidos para asignación masiva.
     */
    protected $fillable = [
        'zone_id',
        'user_id',
        'trigger_reading_id',

        'mode',
        'status',

        'duration_minutes',
        'water_liters',

        'soil_humidity_before',
        'soil_humidity_after',

        'started_at',
        'ended_at',

        'notes',
    ];


    /**
     * Conversión automática de tipos.
     */
    protected $casts = [
        'duration_minutes' => 'integer',

        'water_liters' => 'decimal:2',

        'soil_humidity_before' => 'decimal:2',
        'soil_humidity_after' => 'decimal:2',

        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];


    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */


    /**
     * Zona donde ocurrió el riego.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(
            Zone::class
        );
    }


    /**
     * Usuario que inició el riego.
     *
     * Puede ser NULL si fue automático.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }


    /**
     * Lectura que originó el riego automático.
     */
    public function triggerReading(): BelongsTo
    {
        return $this->belongsTo(
            Reading::class,
            'trigger_reading_id'
        );
    }
}