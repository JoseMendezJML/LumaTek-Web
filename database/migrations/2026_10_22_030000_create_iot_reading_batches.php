<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot_reading_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->uuid('batch_id');
            $table->char('payload_hash', 64);
            $table->timestamp('recorded_at');
            $table->timestamp('received_at');
            $table->unsignedTinyInteger('reading_count');
            $table->unique(['device_id', 'batch_id']);
        });
        Schema::table('readings', function (Blueprint $table) {
            $table->foreignId('iot_reading_batch_id')->nullable()->constrained('iot_reading_batches')->nullOnDelete();
            $table->unique(['iot_reading_batch_id', 'sensor_id']);
        });
        // RAW never enters the percentage consumers of readings.value.
        Schema::create('raw_sensor_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('iot_reading_batch_id')->constrained('iot_reading_batches')->cascadeOnDelete();
            $table->unsignedSmallInteger('value');
            $table->string('unit', 20)->default('raw');
            $table->string('source', 20)->default('iot');
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->unique(['iot_reading_batch_id', 'sensor_id']);
            $table->index(['sensor_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_sensor_readings');
        Schema::table('readings', function (Blueprint $table) {
            $table->dropForeign(['iot_reading_batch_id']);
            $table->dropUnique(['iot_reading_batch_id', 'sensor_id']);
            $table->dropColumn('iot_reading_batch_id');
        });
        Schema::dropIfExists('iot_reading_batches');
    }
};
