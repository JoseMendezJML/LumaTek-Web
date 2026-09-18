<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sensors', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Dispositivo
            |--------------------------------------------------------------------------
            |
            | Un dispositivo puede tener uno o varios sensores conectados.
            |
            */

            $table->foreignId('device_id')
                ->constrained('devices')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Identificación del sensor
            |--------------------------------------------------------------------------
            */

            $table->string('name', 120);

            /*
            | Código único utilizado para identificar el sensor cuando
            | posteriormente recibamos datos desde ESP32 u otros dispositivos.
            */

            $table->string('sensor_code', 100)
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Tipo de sensor
            |--------------------------------------------------------------------------
            |
            | Ejemplos:
            | temperature
            | soil_humidity
            | ambient_humidity
            |
            */

            $table->string('sensor_type', 80);

            /*
            |--------------------------------------------------------------------------
            | Unidad de medida
            |--------------------------------------------------------------------------
            |
            | Ejemplos:
            | °C
            | %
            |
            */

            $table->string('unit', 20);

            /*
            |--------------------------------------------------------------------------
            | Modelo físico
            |--------------------------------------------------------------------------
            |
            | Ejemplos futuros:
            | DHT22
            | DS18B20
            | SHT31
            |
            */

            $table->string('model', 100)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Posición relativa
            |--------------------------------------------------------------------------
            |
            | Permitirá ubicar visualmente el sensor dentro de una zona.
            | Valores esperados: 0.00 a 100.00 (%)
            |
            */

            $table->decimal('position_x', 5, 2)
                ->nullable();

            $table->decimal('position_y', 5, 2)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Estado
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'active',
                'inactive',
            ])->default('active');

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */

            $table->index('device_id');
            $table->index('sensor_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensors');
    }
};