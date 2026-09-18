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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Zona
            |--------------------------------------------------------------------------
            |
            | Cada dispositivo pertenece a una zona del invernadero.
            |
            */

            $table->foreignId('zone_id')
                ->constrained('zones')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Identificación del dispositivo
            |--------------------------------------------------------------------------
            */

            $table->string('name', 120);

            $table->string('device_code', 100)
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Tipo de dispositivo
            |--------------------------------------------------------------------------
            |
            | Ejemplos:
            | ESP32
            | ESP8266
            | Gateway
            | Simulador
            |
            */

            $table->string('device_type', 80)
                ->default('ESP32');

            /*
            |--------------------------------------------------------------------------
            | Tipo de conexión
            |--------------------------------------------------------------------------
            |
            | Ejemplos:
            | wifi
            | ethernet
            | lora
            | simulation
            |
            */

            $table->enum('connection_type', [
                'wifi',
                'ethernet',
                'lora',
                'simulation',
            ])->default('wifi');

            /*
            |--------------------------------------------------------------------------
            | Estado
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'active',
                'inactive',
            ])->default('active');

            /*
            |--------------------------------------------------------------------------
            | Última conexión
            |--------------------------------------------------------------------------
            |
            | Nos servirá posteriormente para detectar si el dispositivo
            | dejó de enviar información.
            |
            */

            $table->timestamp('last_connection_at')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */

            $table->index('zone_id');
            $table->index('status');
            $table->index('last_connection_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};