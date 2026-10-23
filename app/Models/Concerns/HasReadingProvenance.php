<?php

namespace App\Models\Concerns;

use App\Models\Sensor;
use App\Services\IotConnectivity;

trait HasReadingProvenance
{
    public function getIsPhysicalAttribute(): bool
    {
        return $this->source === 'iot' && $this->iot_reading_batch_id !== null;
    }

    public function getProvenanceAttribute(): string
    {
        return $this->is_physical ? 'physical_iot' : ($this->source === 'simulation' ? 'manual_or_simulated' : 'unverified_iot');
    }

    public function getSourceLabelAttribute(): string
    {
        return match ($this->provenance) {
            'physical_iot' => 'IoT físico',
            'manual_or_simulated' => 'Simulación / manual',
            default => 'IoT no verificado',
        };
    }

    public function connectionStatus(int $interval = 60, ?Sensor $sensor = null): string
    {
        if ($sensor && ($sensor->status !== 'active' || $sensor->device->status !== 'active'
            || $sensor->device->zone->status !== 'active' || $sensor->device->zone->greenhouse->status !== 'active')) {
            return 'disabled';
        }
        if (! $this->is_physical) {
            return $this->source === 'simulation' ? 'simulated' : 'unverified';
        }
        if ($sensor && (! $sensor->device->paired_at || ! $sensor->device->credential_hash)) {
            return 'unpaired';
        }

        $policy = app(IotConnectivity::class);

        return $sensor ? $policy->status($sensor->device) : $policy->freshness(null, $interval);
    }

    public function connectionDetails(Sensor $sensor): array
    {
        $policy = app(IotConnectivity::class);
        $stale = $policy->freshness($this->recorded_at, $sensor->device->measurement_interval ?? 60) !== 'connected';

        return ['last_authenticated_communication_at' => $sensor->device->last_connection_at?->toIso8601String(),
            'measurement_is_stale' => $stale,
            'measurement_label' => $stale ? 'Medición antigua' : 'Medición reciente'];
    }

    public function connectionLabel(int $interval = 60, ?Sensor $sensor = null): string
    {
        return match ($this->connectionStatus($interval, $sensor)) {
            'disabled' => 'Desactivado',
            'unpaired' => 'Pendiente de vinculación',
            'connected' => 'Conectado',
            'stale' => 'Sin comunicación reciente',
            'disconnected' => 'Desconectado',
            default => $this->source_label,
        };
    }
}
