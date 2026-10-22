<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'sensor_id',
        'reading_id',
        'type',
        'severity',
        'message',
        'status',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_at',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Etiquetas legibles. Se usan en todas las respuestas de la API
     * para que la web y la app móvil muestren el mismo texto.
     */
    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'high_temperature' => 'Temperatura alta',
            'low_temperature' => 'Temperatura baja',
            'low_soil_humidity' => 'Humedad del suelo baja',
            'high_ambient_humidity' => 'Humedad ambiental alta',
            default => 'Alerta de monitoreo',
        };
    }

    public static function severityLabel(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Crítica',
            'warning' => 'Advertencia',
            default => 'Informativa',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Activa',
            'acknowledged' => 'Atendida',
            'resolved' => 'Resuelta',
            default => $status,
        };
    }

    /**
     * Sensor que originó la alerta.
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    /**
     * Lectura que originó la alerta.
     */
    public function reading(): BelongsTo
    {
        return $this->belongsTo(Reading::class);
    }

    /**
     * Usuario que reconoció la alerta.
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'acknowledged_by'
        );
    }
}