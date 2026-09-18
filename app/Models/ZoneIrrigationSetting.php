<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneIrrigationSetting extends Model
{
    use HasFactory;

    /**
     * Campos permitidos para asignación masiva.
     */
    protected $fillable = [
        'zone_id',
        'automatic_enabled',
        'duration_minutes',
        'water_liters',
        'cooldown_minutes',
    ];


    /**
     * Conversión automática de tipos.
     */
    protected $casts = [
        'automatic_enabled' => 'boolean',
        'duration_minutes' => 'integer',
        'water_liters' => 'decimal:2',
        'cooldown_minutes' => 'integer',
    ];


    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    /**
     * Zona a la que pertenece esta configuración.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(
            Zone::class
        );
    }
}