<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->json('old_values');
            $table->json('new_values');
            $table->timestamp('recorded_at');
            $table->index(['plan_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        if (DB::table('plan_changes')->exists()) {
            throw new RuntimeException('Refusing to drop persistent plan audit history.');
        }
        Schema::dropIfExists('plan_changes');
    }
};
