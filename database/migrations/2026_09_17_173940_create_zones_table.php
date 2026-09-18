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
        Schema::create('zones', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Invernadero
            |--------------------------------------------------------------------------
            |
            | Cada zona pertenece a un único invernadero.
            |
            */

            $table->foreignId('greenhouse_id')
                ->constrained('greenhouses')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Datos de la zona
            |--------------------------------------------------------------------------
            */

            $table->string('name', 120);

            $table->string('description', 255)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Posición relativa
            |--------------------------------------------------------------------------
            |
            | Nos permitirá representar posteriormente las zonas dentro
            | del plano del invernadero.
            |
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
            | Restricciones
            |--------------------------------------------------------------------------
            |
            | No permitimos dos zonas con el mismo nombre dentro del mismo
            | invernadero.
            |
            */

            $table->unique([
                'greenhouse_id',
                'name',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};