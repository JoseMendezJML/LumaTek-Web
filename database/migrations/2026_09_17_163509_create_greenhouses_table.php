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
        Schema::create('greenhouses', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Empresa propietaria
            |--------------------------------------------------------------------------
            |
            | Permite mantener aislados los invernaderos de cada empresa.
            |
            */

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Datos del invernadero
            |--------------------------------------------------------------------------
            */

            $table->string('name', 150);

            $table->string('crop_type', 120);

            $table->decimal('area', 10, 2);

            $table->string('location', 255);

            $table->date('planting_date');

            /*
            |--------------------------------------------------------------------------
            | Caudal nominal
            |--------------------------------------------------------------------------
            |
            | Se manejará inicialmente en litros por minuto (L/min).
            |
            */

            $table->decimal('nominal_flow', 10, 2);

            /*
            |--------------------------------------------------------------------------
            | Estado
            |--------------------------------------------------------------------------
            |
            | Más adelante permitirá desactivar un invernadero sin eliminar
            | su historial.
            |
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

            $table->index('company_id');
            $table->index('status');
            $table->index('crop_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('greenhouses');
    }
};