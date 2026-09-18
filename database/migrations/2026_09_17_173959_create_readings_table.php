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
        Schema::create('readings', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Sensor
            |--------------------------------------------------------------------------
            |
            | Cada lectura pertenece a un sensor específico.
            |
            */

            $table->foreignId('sensor_id')
                ->constrained('sensors')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Valor registrado
            |--------------------------------------------------------------------------
            |
            | Para temperatura:
            | 26.4
            |
            | Para humedad:
            | 58.7
            |
            */

            $table->decimal('value', 10, 2);

            /*
            |--------------------------------------------------------------------------
            | Fecha y hora de la lectura
            |--------------------------------------------------------------------------
            |
            | Este campo es fundamental para:
            |
            | - saber cuál es la lectura más reciente;
            | - comprobar si el sensor actualiza cada 5 minutos;
            | - mostrar "Sin conexión" si pasan más de 10 minutos.
            |
            */

            $table->timestamp('recorded_at');

            /*
            |--------------------------------------------------------------------------
            | Fuente
            |--------------------------------------------------------------------------
            |
            | simulation = datos simulados durante desarrollo/pruebas
            | iot        = datos enviados por sensores físicos
            |
            */

            $table->enum('source', [
                'simulation',
                'iot',
            ])->default('simulation');

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */

            $table->index('sensor_id');

            $table->index('recorded_at');

            /*
            | Este índice ayudará a recuperar rápidamente
            | la lectura más reciente de un sensor.
            */

            $table->index([
                'sensor_id',
                'recorded_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('readings');
    }
};