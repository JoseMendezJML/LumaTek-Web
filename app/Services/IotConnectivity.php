<?php

namespace App\Services;

use App\Models\Device;
use Carbon\CarbonInterface;

class IotConnectivity
{
    public function limits(int $interval): array
    {
        $interval = max(1, $interval);
        $tolerance = max(60, (int) ceil($interval * 0.2));

        return ['recent_seconds' => $interval + $tolerance, 'disconnect_seconds' => 2 * $interval + $tolerance];
    }

    public function freshness(?CarbonInterface $timestamp, int $interval): string
    {
        if (! $timestamp || $timestamp->gt(now())) {
            return 'disconnected';
        }
        $age = $timestamp->diffInSeconds(now());
        $limits = $this->limits($interval);

        return $age < $limits['recent_seconds'] ? 'connected' : ($age < $limits['disconnect_seconds'] ? 'stale' : 'disconnected');
    }

    public function status(Device $device): string
    {
        if ($device->status !== 'active' || $device->zone->status !== 'active' || $device->zone->greenhouse->status !== 'active') {
            return 'disabled';
        }
        if (! $device->paired_at || ! $device->credential_hash) {
            return 'unpaired';
        }

        return $this->freshness($device->last_connection_at, $device->measurement_interval ?? 60);
    }

    public function label(string $status): string
    {
        return match ($status) {
            'connected' => 'Conectado', 'stale' => 'Sin comunicación reciente', 'disconnected' => 'Desconectado',
            'disabled' => 'Desactivado', 'unpaired' => 'Pendiente de vinculación', default => 'Sin datos',
        };
    }
}
