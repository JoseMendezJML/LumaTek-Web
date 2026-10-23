<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['readings', 'raw_sensor_readings'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->string('measurement_type', 80)->nullable();
                if ($name === 'readings') {
                    $table->string('unit', 20)->nullable();
                }
                $table->json('calibration_snapshot')->nullable();
                $table->unsignedInteger('interval_snapshot')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->string('metadata_origin', 30)->nullable();
            });
            DB::table($name)->orderBy('id')->chunkById(500, function ($rows) use ($name) {
                $sensors = DB::table('sensors')->whereIn('id', $rows->pluck('sensor_id'))->pluck('sensor_type', 'id');
                $batches = DB::table('iot_reading_batches')->whereIn('id', $rows->pluck('iot_reading_batch_id')->filter())->pluck('received_at', 'id');
                foreach ($rows as $row) {
                    $type = $name === 'raw_sensor_readings' ? 'soil_humidity' : ($sensors[$row->sensor_id] ?? null);
                    // Before this migration readings stored physical units/%;
                    // ADC was exclusively in raw_sensor_readings. Never use the
                    // sensor's current unit/calibration to relabel old values.
                    $unit = $name === 'raw_sensor_readings' ? 'raw' : match ($type) {
                        'temperature' => '°C', 'soil_humidity', 'ambient_humidity' => '%', default => null,
                    };
                    DB::table($name)->where('id', $row->id)->update([
                        'measurement_type' => $type, 'unit' => $unit,
                        'received_at' => $batches[$row->iot_reading_batch_id] ?? null,
                        'metadata_origin' => 'legacy_schema',
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        // Dropping snapshots would reinterpret measurements using mutable sensor
        // settings. Refuse destructive rollback once history is present.
        if (DB::table('readings')->exists() || DB::table('raw_sensor_readings')->exists()) {
            throw new RuntimeException('Cannot discard measurement metadata while history exists.');
        }
        foreach (['readings', 'raw_sensor_readings'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $columns = ['measurement_type', 'calibration_snapshot', 'interval_snapshot', 'received_at', 'metadata_origin'];
                if ($name === 'readings') {
                    $columns[] = 'unit';
                }
                $table->dropColumn($columns);
            });
        }
    }
};
