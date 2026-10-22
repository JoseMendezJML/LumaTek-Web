<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_restriction_states', function (Blueprint $table) {
            $table->foreignId('company_id')->primary()->constrained()->cascadeOnDelete();
            $table->char('fingerprint', 64);
            $table->timestamp('synced_at');
        });
    }

    public function down(): void
    {
        // Derived synchronization metadata only, never subscription/contract history.
        Schema::dropIfExists('subscription_restriction_states');
    }
};
