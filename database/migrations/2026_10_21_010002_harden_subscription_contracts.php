<?php

use App\Services\SubscriptionContractIntegrity;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $integrity = app(SubscriptionContractIntegrity::class);
        $integrity->assertSchema();
        // On an already migrated installation never reconstruct missing history
        // from today's catalog. Missing/mismatched snapshots need human review.
        $integrity->assertSubscriptions();
        $integrity->ensureTriggers(install: true);
    }

    public function down(): void
    {
        // Deliberately retain historical protections on rollback.
    }
};
