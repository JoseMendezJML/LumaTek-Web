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
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Sensor relacionado
            |--------------------------------------------------------------------------
            |
            | La alerta pertenece al sensor que generó la condición.
            |
            */

            $table->foreignId('sensor_id')
                ->constrained('sensors')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Lectura que originó la alerta
            |--------------------------------------------------------------------------
            |
            | Puede ser null para permitir otros tipos de alertas
            | que no dependan directamente de una lectura.
            |
            */

            $table->foreignId('reading_id')
                ->nullable()
                ->constrained('readings')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Tipo de alerta
            |--------------------------------------------------------------------------
            |
            | Ejemplos:
            | high_temperature
            | low_temperature
            | disconnected
            |
            */

            $table->string('type', 80);

            /*
            |--------------------------------------------------------------------------
            | Severidad
            |--------------------------------------------------------------------------
            */

            $table->enum('severity', [
                'info',
                'warning',
                'critical',
            ])->default('warning');

            /*
            |--------------------------------------------------------------------------
            | Mensaje
            |--------------------------------------------------------------------------
            */

            $table->string('message', 255);

            /*
            |--------------------------------------------------------------------------
            | Estado
            |--------------------------------------------------------------------------
            |
            | active       = alerta pendiente
            | acknowledged = el usuario ya la revisó
            | resolved     = condición normalizada
            |
            */

            $table->enum('status', [
                'active',
                'acknowledged',
                'resolved',
            ])->default('active');

            /*
            |--------------------------------------------------------------------------
            | Usuario que reconoció la alerta
            |--------------------------------------------------------------------------
            */

            $table->foreignId('acknowledged_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('acknowledged_at')
                ->nullable();

            $table->timestamp('resolved_at')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */

            $table->index('sensor_id');
            $table->index('type');
            $table->index('status');
            $table->index('severity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};