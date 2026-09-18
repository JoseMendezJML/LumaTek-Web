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
        Schema::create('irrigation_events', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Zona donde se realiza el riego
            |--------------------------------------------------------------------------
            |
            | No guardamos greenhouse_id porque la zona ya pertenece
            | directamente a un invernadero.
            |
            */

            $table->foreignId('zone_id')
                ->constrained('zones')
                ->restrictOnDelete();


            /*
            |--------------------------------------------------------------------------
            | Usuario que inició el riego
            |--------------------------------------------------------------------------
            |
            | En un riego automático puede ser NULL.
            |
            */

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();


            /*
            |--------------------------------------------------------------------------
            | Lectura que provocó el riego automático
            |--------------------------------------------------------------------------
            |
            | Será NULL cuando el riego sea manual.
            |
            */

            $table->foreignId('trigger_reading_id')
                ->nullable()
                ->constrained('readings')
                ->nullOnDelete();


            /*
            |--------------------------------------------------------------------------
            | Tipo de activación
            |--------------------------------------------------------------------------
            */

            $table->enum('mode', [
                'manual',
                'automatic',
            ])->default('manual');


            /*
            |--------------------------------------------------------------------------
            | Estado del evento
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'started',
                'completed',
                'cancelled',
                'failed',
            ])->default('started');


            /*
            |--------------------------------------------------------------------------
            | Información del riego
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('duration_minutes')
                ->nullable();

            $table->decimal('water_liters', 10, 2)
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | Humedad del suelo
            |--------------------------------------------------------------------------
            |
            | Permite comparar el nivel antes y después del riego.
            |
            */

            $table->decimal('soil_humidity_before', 5, 2)
                ->nullable();

            $table->decimal('soil_humidity_after', 5, 2)
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | Fechas del evento
            |--------------------------------------------------------------------------
            */

            $table->timestamp('started_at');

            $table->timestamp('ended_at')
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | Información adicional
            |--------------------------------------------------------------------------
            */

            $table->text('notes')
                ->nullable();


            $table->timestamps();


            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */

            $table->index([
                'zone_id',
                'started_at',
            ]);

            $table->index([
                'mode',
                'status',
            ]);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('irrigation_events');
    }
};