<?php

use App\Services\SubscriptionContractIntegrity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['sqlite', 'mysql'], true)) {
            throw new RuntimeException('Contract immutability requires SQLite or MySQL.');
        }
        // Preflight references before any DDL/backfill, including legacy orphan rows.
        if (DB::table('subscriptions as s')->leftJoin('plans as p', 'p.id', '=', 's.plan_id')
            ->leftJoin('companies as c', 'c.id', '=', 's.company_id')
            ->where(fn ($q) => $q->whereNull('p.id')->orWhereNull('c.id'))->exists()) {
            throw new RuntimeException('Subscriptions have missing plan/company references. Restore verified references before migrating contracts.');
        }
        if (! Schema::hasTable('subscription_contracts')) {
            Schema::create('subscription_contracts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_id')->unique()->constrained()->restrictOnDelete();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('plan_id')->constrained()->restrictOnDelete();
                $table->string('request_key', 100)->nullable()->unique();
                $table->enum('origin', ['legacy', 'compatibility', 'purchase', 'renewal']);
                $table->decimal('amount', 10, 2)->nullable();
                $table->char('currency', 3)->nullable();
                $table->enum('billing_period', ['monthly', 'yearly'])->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->string('plan_name');
                $table->string('plan_slug');
                foreach (['max_greenhouses', 'max_zones_per_greenhouse', 'max_sensors_per_zone', 'max_users', 'max_additional_admins'] as $limit) {
                    $table->unsignedSmallInteger($limit);
                }
                $table->timestamp('recorded_at');
            });

        }

        $integrity = app(SubscriptionContractIntegrity::class);
        $integrity->assertSchema();
        $integrity->assertSubscriptions(allowMissing: true);
        // Install absent guards without dropping protections already in place.
        $integrity->ensureTriggers(install: true);
        DB::transaction(function () {
            // Preserve original subscription rows/dates. These are observed baseline
            // conditions, not a reconstruction of prices or originally agreed limits.
            $plans = DB::table('plans')->get()->keyBy('id');
            DB::table('subscriptions')->whereNotExists(fn ($query) => $query->selectRaw('1')->from('subscription_contracts')->whereColumn('subscription_contracts.subscription_id', 'subscriptions.id'))->orderBy('id')->chunkById(200, function ($subscriptions) use ($plans) {
                foreach ($subscriptions as $subscription) {
                    $plan = $plans->get($subscription->plan_id);
                    $terms = [];
                    foreach (['max_greenhouses', 'max_zones_per_greenhouse', 'max_sensors_per_zone', 'max_users', 'max_additional_admins'] as $limit) {
                        $terms[$limit] = $plan->{$limit};
                    }
                    DB::table('subscription_contracts')->insert([
                        'subscription_id' => $subscription->id, 'company_id' => $subscription->company_id,
                        'plan_id' => $subscription->plan_id, 'origin' => 'legacy',
                        'amount' => null, 'currency' => null, 'billing_period' => null,
                        'starts_at' => $subscription->starts_at, 'ends_at' => $subscription->ends_at,
                        'plan_name' => $plan->name, 'plan_slug' => $plan->slug,
                        ...$terms, 'recorded_at' => now(),
                    ]);
                }
            });

            app(SubscriptionContractIntegrity::class)->assertSubscriptions();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('subscription_contracts') && DB::table('subscription_contracts')->exists()) {
            throw new RuntimeException('Refusing to drop historical subscription contracts. Use a reviewed forward recovery migration.');
        }
        foreach (['update', 'delete', 'replace'] as $operation) {
            DB::unprepared('DROP TRIGGER IF EXISTS subscription_contracts_no_'.$operation);
        }
        Schema::dropIfExists('subscription_contracts');
    }
};
