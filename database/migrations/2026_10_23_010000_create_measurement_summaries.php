<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_measurement_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('greenhouse_id');
            $table->unsignedBigInteger('sensor_id');
            $table->string('granularity', 8);
            $table->string('timezone', 64);
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->string('measurement_type', 80)->nullable();
            $table->string('unit', 20)->nullable();
            $table->string('provenance', 30);
            $table->string('calibration_key', 64);
            $table->json('calibration_snapshot')->nullable();
            $table->string('metadata_origin', 30)->nullable();
            $table->unsignedInteger('interval_snapshot')->nullable();
            $table->string('series_key', 64);
            $table->decimal('minimum', 24, 6);
            $table->decimal('maximum', 24, 6);
            $table->decimal('value_sum', 24, 6);
            $table->decimal('average', 24, 6);
            $table->unsignedBigInteger('measurement_count');
            $table->dateTime('first_measured_at');
            $table->dateTime('last_measured_at');
            $table->timestamps();
            $table->unique(['company_id', 'granularity', 'period_start', 'series_key'], 'measurement_summary_period_unique');
            $table->index(['company_id', 'granularity', 'measurement_type', 'period_start'], 'measurement_summary_query');
            $table->index(['sensor_id', 'period_start']);
        });
        Schema::create('measurement_summary_contributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('stream', 24);
            $table->unsignedBigInteger('reading_id');
            $table->timestamp('processed_at');
            $table->unique(['stream', 'reading_id'], 'measurement_contribution_unique');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_summary_contributions');
        Schema::dropIfExists('sensor_measurement_summaries');
    }
};
