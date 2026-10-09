<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {

            $table->id();

            $table->string('name');

            $table
                ->string('slug')
                ->unique();

            $table
                ->text('description')
                ->nullable();

            $table
                ->decimal(
                    'price_monthly',
                    10,
                    2
                )
                ->default(0);

            $table
                ->unsignedSmallInteger(
                    'max_greenhouses'
                );

            $table
                ->unsignedSmallInteger(
                    'max_zones_per_greenhouse'
                );

            $table
                ->unsignedSmallInteger(
                    'max_sensors_per_zone'
                );

            $table
                ->unsignedSmallInteger(
                    'max_users'
                );

            $table
                ->unsignedSmallInteger(
                    'max_additional_admins'
                );

            $table
                ->boolean('is_active')
                ->default(true);

            $table->timestamps();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};