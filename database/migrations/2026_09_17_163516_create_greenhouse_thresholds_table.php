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
        Schema::create('greenhouse_thresholds', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Invernadero
            |--------------------------------------------------------------------------
            |
            | Cada umbral pertenece a un invernadero específico.
            |
            */

            $table->foreignId('greenhouse_id')
                ->constrained('greenhouses')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Variable monitoreada
            |--------------------------------------------------------------------------
            |
            | Ejemplos:
            | temperature
            | soil_humidity
            | ambient_humidity
            |
            */

            $table->string('variable', 80);

            /*
            |--------------------------------------------------------------------------
            | Valores permitidos
            |--------------------------------------------------------------------------
            */

            $table->decimal('min_value', 10, 2)->nullable();

            $table->decimal('max_value', 10, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Unidad
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
            | Umbral predeterminado
            |--------------------------------------------------------------------------
            |
            | true  = generado automáticamente por LumaTek.
            | false = modificado posteriormente por el usuario.
            |
            */

            $table->boolean('is_default')
                ->default(true);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Restricciones e índices
            |--------------------------------------------------------------------------
            |
            | Un invernadero no debe tener dos configuraciones de umbral
            | para la misma variable.
            |
            */

            $table->unique([
                'greenhouse_id',
                'variable',
            ]);

            $table->index('variable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('greenhouse_thresholds');
    }
};