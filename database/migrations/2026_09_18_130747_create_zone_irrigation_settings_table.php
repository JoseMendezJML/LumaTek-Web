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
        Schema::create(
            'zone_irrigation_settings',
            function (Blueprint $table) {

                $table->id();

                /*
                |--------------------------------------------------------------------------
                | Zona
                |--------------------------------------------------------------------------
                |
                | Cada zona tendrá como máximo una configuración
                | de riego automático.
                |
                */

                $table->foreignId('zone_id')
                    ->unique()
                    ->constrained('zones')
                    ->cascadeOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Activar / desactivar automatización
                |--------------------------------------------------------------------------
                */

                $table->boolean('automatic_enabled')
                    ->default(false);


                /*
                |--------------------------------------------------------------------------
                | Duración programada
                |--------------------------------------------------------------------------
                |
                | Duración aproximada que tendrá un riego automático.
                |
                */

                $table->unsignedInteger('duration_minutes')
                    ->default(10);


                /*
                |--------------------------------------------------------------------------
                | Agua estimada
                |--------------------------------------------------------------------------
                |
                | Cantidad aproximada de agua que utilizará el evento.
                |
                */

                $table->decimal(
                    'water_liters',
                    10,
                    2
                )
                    ->nullable();


                /*
                |--------------------------------------------------------------------------
                | Tiempo mínimo entre riegos automáticos
                |--------------------------------------------------------------------------
                |
                | Evita que varias lecturas bajas consecutivas
                | creen un nuevo riego inmediatamente.
                |
                */

                $table->unsignedInteger('cooldown_minutes')
                    ->default(60);


                /*
                |--------------------------------------------------------------------------
                | Timestamps
                |--------------------------------------------------------------------------
                */

                $table->timestamps();
            }
        );
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'zone_irrigation_settings'
        );
    }
};