<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ContractedPlan;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionContract;
use App\Services\SubscriptionContractIntegrity;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class SubscriptionContractHardeningTest extends TestCase
{
    use UsesInMemoryDatabase;

    private function subscription(): Subscription
    {
        $company = Company::create(['name' => 'Isolated company', 'email' => fake()->unique()->safeEmail(), 'status' => 'active']);

        return Subscription::create(['company_id' => $company->id, 'plan_id' => Plan::where('slug', 'pro')->value('id'), 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth()]);
    }

    public static function mutations(): array
    {
        $cases = [];
        foreach (['contract', 'projection'] as $target) {
            foreach (['increment', 'decrement', 'incrementQuietly', 'decrementQuietly', 'incrementEach', 'decrementEach', 'incrementEachQuietly', 'decrementEachQuietly', 'replicate', 'push', 'forceDelete', 'builder-update', 'builder-delete', 'builder-forceDelete', 'insert', 'insertOrIgnore', 'insertGetId', 'upsert', 'updateOrInsert', 'truncate', 'base-update', 'base-insert', 'base-increment', 'base-delete'] as $method) {
                $cases[$target.'-'.$method] = [$target, $method];
            }
        }

        return $cases;
    }

    #[DataProvider('mutations')]
    public function test_inherited_and_bulk_mutations_cannot_change_history_or_catalog(string $target, string $method): void
    {
        $subscription = $this->subscription();
        $model = $target === 'contract' ? $subscription->contract : $subscription->company->effectivePlan();
        $plans = DB::table('plans')->orderBy('id')->get()->toJson();
        $contracts = DB::table('subscription_contracts')->get()->toJson();
        $query = $model->newQuery();
        try {
            match ($method) {
                'increment', 'decrement', 'incrementQuietly', 'decrementQuietly' => $model->{$method}('max_users'),
                'incrementEach', 'decrementEach', 'incrementEachQuietly', 'decrementEachQuietly' => $model->{$method}(['max_users' => 1]),
                'replicate' => $model->replicate()->save(),
                'push' => $model->forceFill(['max_users' => 999])->push(),
                'forceDelete' => $model->forceDelete(),
                'builder-update' => $query->update(['max_users' => 999]),
                'builder-delete' => $query->delete(),
                'builder-forceDelete' => $query->forceDelete(),
                'insert', 'insertOrIgnore', 'insertGetId' => $query->{$method}($model->getAttributes()),
                'upsert' => $query->upsert([$model->getAttributes()], ['id'], ['max_users']),
                'updateOrInsert' => $query->updateOrInsert(['id' => $model->id], ['max_users' => 999]),
                'truncate' => $query->truncate(),
                'base-update' => $query->toBase()->update(['max_users' => 999]),
                'base-insert' => $query->getQuery()->insert($model->getAttributes()),
                'base-increment' => $query->getQuery()->increment('max_users'),
                'base-delete' => $query->toBase()->delete(),
            };
            $this->fail('Unauthorized mutation was accepted: '.$target.' '.$method);
        } catch (LogicException|QueryException) {
            $this->assertSame($plans, DB::table('plans')->orderBy('id')->get()->toJson());
            $this->assertSame($contracts, DB::table('subscription_contracts')->get()->toJson());
        }
    }

    public static function collisions(): array
    {
        return [['id'], ['subscription_id'], ['request_key']];
    }

    #[DataProvider('collisions')]
    public function test_sqlite_replace_cannot_replace_history_with_recursive_triggers_disabled(string $collision): void
    {
        $subscription = $this->subscription();
        // Give this fixture a request key during authorized capture, never edit history.
        $other = $this->subscription();
        $newId = DB::table('subscriptions')->insertGetId(['company_id' => $other->company_id, 'plan_id' => $other->plan_id, 'status' => 'active']);
        $keyed = new Subscription(['company_id' => $subscription->company_id, 'plan_id' => $subscription->plan_id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth()]);
        $keyed->withContractContext('monthly', 'purchase', 'original-key')->save();
        $row = $keyed->contract->getRawOriginal();
        $replacement = [...$row, 'id' => 999, 'subscription_id' => $newId, 'request_key' => 'different-key', 'max_users' => 999];
        $replacement[$collision] = $row[$collision];
        $before = DB::table('subscription_contracts')->orderBy('id')->get()->toJson();
        DB::statement('PRAGMA recursive_triggers = OFF');
        $this->assertSame(0, DB::selectOne('PRAGMA recursive_triggers')->recursive_triggers);
        try {
            $columns = implode(', ', array_map(fn ($key) => '"'.$key.'"', array_keys($replacement)));
            DB::insert('INSERT OR REPLACE INTO subscription_contracts ('.$columns.') VALUES ('.implode(', ', array_fill(0, count($replacement), '?')).')', array_values($replacement));
            $this->fail('REPLACE rewrote immutable history.');
        } catch (QueryException) {
            $this->assertSame($before, DB::table('subscription_contracts')->orderBy('id')->get()->toJson());
        }
    }

    public function test_missing_contract_blocks_access_and_read_only_integrity_command(): void
    {
        $subscription = $this->subscription();
        $other = $this->subscription();
        DB::table('subscriptions')->insert(['company_id' => $subscription->company_id, 'plan_id' => $subscription->plan_id, 'status' => 'active']);
        $before = DB::table('subscription_contracts')->get()->toJson();
        $this->artisan('subscriptions:verify-integrity')->expectsOutputToContain('has no contract')->assertExitCode(1);
        $this->assertSame($before, DB::table('subscription_contracts')->get()->toJson());
        $this->assertInstanceOf(ContractedPlan::class, $other->company->effectivePlan());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has no contract');
        $subscription->company->effectivePlan();
    }

    public function test_rewritten_subscription_identity_is_detected_before_access(): void
    {
        $subscription = $this->subscription();
        DB::table('subscriptions')->where('id', $subscription->id)->update(['ends_at' => now()->addYear()]);
        $this->artisan('subscriptions:verify-integrity')->expectsOutputToContain('integrity failed')->assertExitCode(1);
        $this->expectException(RuntimeException::class);
        $subscription->company->effectivePlan();
    }

    public function test_partial_price_schema_fails_without_changing_existing_values(): void
    {
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('currency'));
        Plan::where('slug', 'pro')->update(['price_yearly' => 500]);
        $migration = require database_path('migrations/2026_10_21_010000_add_annual_price_and_currency_to_plans.php');
        try {
            $migration->up();
            $this->fail('Partial pricing schema accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Partial plan pricing schema', $exception->getMessage());
            $this->assertSame('500.00', Plan::where('slug', 'pro')->first()->price_yearly);
            $this->assertFalse(Schema::hasColumn('plans', 'currency'));
        }
    }

    public function test_partial_contract_schema_is_rejected_without_backfill_or_destroying_rows(): void
    {
        $subscription = $this->subscription();
        Schema::table('subscription_contracts', fn (Blueprint $table) => $table->dropColumn('plan_slug'));
        $afterAlter = DB::table('subscription_contracts')->get()->toJson();
        $migration = require database_path('migrations/2026_10_21_010001_create_subscription_contracts_table.php');
        try {
            $migration->up();
            $this->fail('Partial contracts schema accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Partial subscription_contracts schema', $exception->getMessage());
            $this->assertSame($afterAlter, DB::table('subscription_contracts')->get()->toJson());
            $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);
        }
    }

    public function test_forward_hardening_adds_missing_replace_guard_without_rewriting_history(): void
    {
        $subscription = $this->subscription();
        $before = $subscription->contract->getRawOriginal();
        DB::unprepared('DROP TRIGGER subscription_contracts_no_replace');
        $this->artisan('subscriptions:verify-integrity')->expectsOutputToContain('Missing immutability trigger')->assertExitCode(1);
        $migration = require database_path('migrations/2026_10_21_010002_harden_subscription_contracts.php');
        $migration->up();
        $migration->up();
        $migration->down();
        $this->artisan('subscriptions:verify-integrity')->assertExitCode(0);
        $this->assertSame($before, $subscription->contract->fresh()->getRawOriginal());
        $this->assertNull($subscription->contract->amount);
    }

    public function test_conflicting_trigger_is_not_removed_or_replaced(): void
    {
        DB::unprepared('DROP TRIGGER subscription_contracts_no_replace');
        DB::unprepared('CREATE TRIGGER subscription_contracts_no_replace BEFORE INSERT ON subscription_contracts BEGIN SELECT 1; END');
        try {
            app(SubscriptionContractIntegrity::class)->ensureTriggers(install: true);
            $this->fail('Conflicting trigger silently replaced.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Conflicting contract trigger', $exception->getMessage());
            $this->assertStringContainsString('SELECT 1', DB::selectOne("SELECT sql FROM sqlite_master WHERE name = 'subscription_contracts_no_replace'")->sql);
        }
    }

    public function test_read_only_queries_support_subqueries_and_nested_query_builders(): void
    {
        $subscription = $this->subscription();
        $this->assertSame(1, SubscriptionContract::whereIn('subscription_id', fn ($query) => $query->select('id')->from('subscriptions')->where('company_id', $subscription->company_id))->count());
        $this->assertSame(2, ContractedPlan::whereExists(fn ($query) => $query->selectRaw('1')->from('subscriptions'))->count());
    }

    public function test_forward_migration_rejects_missing_history_instead_of_recapturing_catalog(): void
    {
        $subscription = $this->subscription();
        DB::table('subscriptions')->insert(['company_id' => $subscription->company_id, 'plan_id' => $subscription->plan_id, 'status' => 'active']);
        $before = DB::table('subscription_contracts')->get()->toJson();
        $migration = require database_path('migrations/2026_10_21_010002_harden_subscription_contracts.php');
        try {
            $migration->up();
            $this->fail('Incomplete history accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('has no contract', $exception->getMessage());
            $this->assertSame($before, DB::table('subscription_contracts')->get()->toJson());
        }
    }

    public function test_partial_contract_schema_missing_unique_key_is_rejected(): void
    {
        Schema::table('subscription_contracts', fn (Blueprint $table) => $table->dropUnique(['request_key']));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing unique key request_key');
        app(SubscriptionContractIntegrity::class)->assertSchema();
    }

    public function test_compatible_price_migration_can_resume_without_overwriting_catalog(): void
    {
        Plan::where('slug', 'pro')->update(['price_yearly' => 450, 'price_monthly' => 50]);
        $before = DB::table('plans')->get()->toJson();
        $migration = require database_path('migrations/2026_10_21_010000_add_annual_price_and_currency_to_plans.php');
        $migration->up();
        $this->assertSame($before, DB::table('plans')->get()->toJson());
    }

    public function test_rollback_refuses_to_drop_configured_catalog_prices(): void
    {
        Plan::where('slug', 'pro')->update(['price_yearly' => 500]);
        $migration = require database_path('migrations/2026_10_21_010000_add_annual_price_and_currency_to_plans.php');
        try {
            $migration->down();
            $this->fail('Configured prices were dropped.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Refusing to drop configured plan pricing', $exception->getMessage());
            $this->assertSame('500.00', Plan::where('slug', 'pro')->first()->price_yearly);
        }
    }

    public function test_rollback_refuses_to_drop_nonempty_history(): void
    {
        $subscription = $this->subscription();
        $migration = require database_path('migrations/2026_10_21_010001_create_subscription_contracts_table.php');
        try {
            $migration->down();
            $this->fail('Historical table was dropped.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Refusing to drop', $exception->getMessage());
            $this->assertDatabaseHas('subscription_contracts', ['subscription_id' => $subscription->id]);
            app(SubscriptionContractIntegrity::class)->ensureTriggers();
        }
    }
}
