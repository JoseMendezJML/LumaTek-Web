<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('board_model')->default('esp32_devkit_v1');
            $table->unsignedInteger('measurement_interval')->default(60);
            $table->string('credential_hash', 64)->nullable()->unique();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_reading_at')->nullable();
            $table->boolean('configuration_error')->default(false);
        });
        Schema::table('sensors', function (Blueprint $table) {
            $table->unsignedTinyInteger('pin')->nullable();
            $table->json('calibration')->nullable();
        });
        Schema::create('iot_pairings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('proof_hash', 64);
            $table->string('exchange_hash', 64);
            $table->foreignId('device_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iot_pairings');
        Schema::table('sensors', fn (Blueprint $table) => $table->dropColumn(['pin', 'calibration']));
        Schema::table('devices', fn (Blueprint $table) => $table->dropColumn(['board_model', 'measurement_interval', 'credential_hash', 'paired_at', 'last_reading_at', 'configuration_error']));
    }
};
