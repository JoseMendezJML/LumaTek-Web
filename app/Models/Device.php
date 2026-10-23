<?php

namespace App\Models;

use App\Services\IotConnectivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Device extends Model
{
    use HasFactory;

    protected $hidden = ['credential_hash'];

    protected $appends = ['connection_state', 'connection_status', 'connection_label'];

    public function getConnectionStateAttribute(): string
    {
        if ($this->status !== 'active') {
            return 'disabled';
        }
        if ($this->configuration_error) {
            return 'configuration_error';
        }
        if (! $this->paired_at) {
            return 'pending_pairing';
        }
        if (in_array($this->connection_status, ['stale', 'disconnected', 'unpaired', 'disabled'])) {
            return 'stale';
        }
        if (! $this->last_reading_at) {
            return 'paired_without_readings';
        }

        return app(IotConnectivity::class)->freshness($this->last_reading_at, $this->measurement_interval ?? 60) === 'connected' ? 'receiving_readings' : 'stale';
    }

    public function getConnectionStatusAttribute(): string
    {
        return app(IotConnectivity::class)->status($this);
    }

    public function getConnectionLabelAttribute(): string
    {
        return app(IotConnectivity::class)->label($this->connection_status);
    }

    protected $fillable = [
        'board_model', 'measurement_interval',
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
        'last_manual_reading_at' => 'datetime',
        'paired_at' => 'datetime',
        'last_reading_at' => 'datetime',
        'configuration_error' => 'boolean',
        'measurement_interval' => 'integer',
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
    public function readings(): HasManyThrough
    {
        return $this->hasManyThrough(Reading::class, Sensor::class);
    }

    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class);
    }
}
