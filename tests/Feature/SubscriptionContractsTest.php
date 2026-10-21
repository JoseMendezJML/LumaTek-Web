<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionContract;
use App\Models\User;
use App\Services\SubscriptionContractService;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SubscriptionContractsTest extends TestCase
{
    use UsesInMemoryDatabase;

    public function test_catalog_compatibility_and_seeder_never_overwrite_manual_values(): void
    {
        $plan = Plan::where('slug', 'pro')->firstOrFail();
        $this->assertSame('0.00', $plan->price_monthly);
        $this->assertNull($plan->price_yearly);
        $this->assertSame('MXN', $plan->currency);
        $plan->update(['price_monthly' => '129.50', 'price_yearly' => '1200.00', 'name' => 'Edited Pro', 'max_users' => 45, 'is_active' => false]);
        $before = $plan->fresh()->getRawOriginal();
        $this->seed(PlanSeeder::class);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 2);
    }

    public static function periods(): array
    {
        return [['monthly', '129.50'], ['yearly', '1200.00']];
    }

    #[DataProvider('periods')]
    public function test_contract_retains_price_dates_and_all_limits_until_expiration(string $period, string $amount): void
    {
        $this->travelTo(now()->startOfDay());
        $company = $this->company();
        $plan = $this->pro();
        $end = $period === 'monthly' ? now()->addMonth() : now()->addYear();
        $subscription = app(SubscriptionContractService::class)->create($company, $plan, $period, now()->toDateTimeString(), $end->toDateTimeString(), 'purchase-1');
        $before = $subscription->contract->getRawOriginal();
        $this->assertSame($amount, $subscription->contract->amount);
        $this->assertSame('MXN', $subscription->contract->currency);
        $this->assertSame($period, $subscription->contract->billing_period);
        $this->assertSame('purchase', $subscription->contract->origin);
        $limits = $plan->only(SubscriptionContract::LIMITS);
        $newLimits = array_fill_keys(SubscriptionContract::LIMITS, 1);
        $plan->update([...$newLimits, 'price_monthly' => 999, 'price_yearly' => 9999, 'name' => 'New name', 'is_active' => false]);
        $effective = $company->effectivePlan();
        $this->assertInstanceOf(Plan::class, $effective);
        $this->assertSame($limits, $effective->only(SubscriptionContract::LIMITS));
        $this->assertSame('Pro', $effective->name);
        $this->assertSame($before, $subscription->contract()->first()->getRawOriginal());
        $this->travelTo($end->copy()->subSecond());
        $this->assertSame($plan->id, $company->effectivePlan()->id);
        $this->travelTo($end);
        $this->assertTrue($subscription->isExpired());
        $this->assertNull($company->activeSubscription()->first());
        $this->assertSame('free', $company->effectivePlan()->slug);
        $this->assertSame($before, $subscription->contract()->first()->getRawOriginal());
    }

    public function test_future_renewal_does_not_override_current_period_and_uses_new_price_only_when_it_starts(): void
    {
        $this->travelTo(now()->startOfDay());
        $company = $this->company();
        $plan = $this->pro();
        $service = app(SubscriptionContractService::class);
        $current = $service->create($company, $plan, 'monthly', now()->toDateTimeString(), now()->addMonth()->toDateTimeString(), 'current');
        $plan->update(['price_monthly' => 199, 'max_users' => 30]);
        $renewal = $service->create($company, $plan, 'monthly', now()->addMonth()->toDateTimeString(), now()->addMonths(2)->toDateTimeString(), 'renewal', 'renewal');
        $this->assertSame('129.50', $current->contract->amount);
        $this->assertSame('199.00', $renewal->contract->amount);
        $this->assertSame($current->id, $company->activeSubscription()->first()->id);
        $this->assertSame(20, $company->effectivePlan()->max_users);
        $this->travelTo($renewal->starts_at);
        $this->assertSame($renewal->id, $company->activeSubscription()->first()->id);
        $this->assertSame(30, $company->effectivePlan()->max_users);
        $this->assertSame(2, SubscriptionContract::count());
        $this->assertSame(2, Subscription::count());
    }

    public function test_future_subscription_without_current_one_stays_free_and_nullable_dates_remain_compatible(): void
    {
        $this->travelTo(now()->startOfDay());
        $company = $this->company();
        $future = Subscription::create(['company_id' => $company->id, 'plan_id' => $this->pro()->id, 'status' => 'active', 'starts_at' => now()->addDay(), 'ends_at' => now()->addMonth()]);
        $this->assertSame('free', $company->effectivePlan()->slug);
        $this->travelTo($future->starts_at);
        $this->assertSame('pro', $company->effectivePlan()->slug);
        $openCompany = $this->company();
        $open = Subscription::create(['company_id' => $openCompany->id, 'plan_id' => $future->plan_id, 'status' => 'active']);
        $this->assertNull($open->starts_at);
        $this->assertNull($open->ends_at);
        $this->assertSame($open->id, $openCompany->activeSubscription()->first()->id);
        $this->assertNull($open->contract->amount);
        $this->assertNull($open->contract->billing_period);
        $this->assertSame('compatibility', $open->contract->origin);
        $this->pro()->update(['max_users' => 1]);
        $this->assertSame(20, $openCompany->effectivePlan()->max_users);
    }

    public function test_idempotent_requests_do_not_duplicate_periods_or_contracts(): void
    {
        $company = $this->company();
        $plan = $this->pro();
        $start = now()->startOfDay()->toDateTimeString();
        $end = now()->startOfDay()->addMonth()->toDateTimeString();
        $service = app(SubscriptionContractService::class);
        $first = $service->create($company, $plan, 'monthly', $start, $end, 'same-request');
        $plan->update(['price_monthly' => 300]);
        $retry = $service->create($company, $plan, 'monthly', $start, $end, 'same-request');
        $this->assertSame($first->id, $retry->id);
        $this->assertSame('129.50', $retry->contract->amount);
        $this->assertDatabaseCount('subscription_contracts', 1);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->expectException(ValidationException::class);
        $service->create($this->company(), $plan, 'monthly', $start, $end, 'same-request');
    }

    public function test_new_overlapping_paid_periods_are_rejected_without_rewriting_existing_history(): void
    {
        $company = $this->company();
        $plan = $this->pro();
        $service = app(SubscriptionContractService::class);
        $first = $service->create($company, $plan, 'monthly', now()->toDateTimeString(), now()->addMonth()->toDateTimeString(), 'first');
        $before = $first->contract->getRawOriginal();
        try {
            $service->create($company, $plan, 'monthly', now()->addDay()->toDateTimeString(), now()->addMonths(2)->toDateTimeString(), 'overlap', 'renewal');
            $this->fail('An overlapping paid period was created.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('subscriptions', 1);
            $this->assertDatabaseCount('subscription_contracts', 1);
            $this->assertSame($before, $first->contract()->first()->getRawOriginal());
        }
    }

    public static function rejectedContracts(): array
    {
        return [['yearly-unconfigured'], ['inactive-plan'], ['suspended-company'], ['invalid-dates']];
    }

    #[DataProvider('rejectedContracts')]
    public function test_invalid_new_contracts_roll_back_atomically(string $reason): void
    {
        $company = $this->company();
        $plan = $this->pro();
        if ($reason === 'yearly-unconfigured') {
            $plan->update(['price_yearly' => null]);
        }
        if ($reason === 'inactive-plan') {
            $plan->update(['is_active' => false]);
        }
        if ($reason === 'suspended-company') {
            $company->update(['status' => 'inactive']);
        }
        try {
            app(SubscriptionContractService::class)->create($company, $plan, 'yearly', now()->toDateTimeString(), ($reason === 'invalid-dates' ? now()->subDay() : now()->addYear())->toDateTimeString(), 'rejected');
            $this->fail('Invalid contract accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('subscriptions', 0);
            $this->assertDatabaseCount('subscription_contracts', 0);
        }
    }

    public static function historyMutations(): array
    {
        return [['model-update'], ['quiet-update'], ['model-delete'], ['sql-update'], ['sql-delete'], ['bulk-update'], ['bulk-delete'], ['rewrite-dates'], ['rewrite-plan'], ['projection-save']];
    }

    #[DataProvider('historyMutations')]
    public function test_history_is_immutable_in_models_and_database(string $operation): void
    {
        $company = $this->company();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $this->pro()->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth()]);
        $contract = $subscription->contract;
        $before = $contract->getRawOriginal();
        try {
            match ($operation) {
                'model-update' => $contract->update(['amount' => 900]),
                'quiet-update' => $contract->forceFill(['amount' => 900])->saveQuietly(),
                'model-delete' => $contract->delete(),
                'sql-update' => DB::table('subscription_contracts')->where('id', $contract->id)->update(['amount' => 900]),
                'sql-delete' => DB::table('subscription_contracts')->where('id', $contract->id)->delete(),
                'bulk-update' => SubscriptionContract::whereKey($contract->id)->update(['max_users' => 900]),
                'bulk-delete' => SubscriptionContract::whereKey($contract->id)->delete(),
                'rewrite-dates' => $subscription->forceFill(['ends_at' => now()->addYear()])->saveQuietly(),
                'rewrite-plan' => $subscription->update(['plan_id' => Plan::where('slug', 'free')->value('id')]),
                'projection-save' => $company->effectivePlan()->save(),
            };
            $this->fail('Historical conditions were rewritten.');
        } catch (\LogicException|QueryException) {
            $this->assertSame($before, $contract->fresh()->getRawOriginal());
            $this->assertDatabaseCount('subscription_contracts', 1);
        }
    }

    public function test_migration_preserves_legacy_subscription_rows_and_unknown_prices_without_duplicate_baselines(): void
    {
        $migration = require database_path('migrations/2026_10_21_010001_create_subscription_contracts_table.php');
        $migration->down();
        $company = $this->company();
        $plan = $this->pro();
        $prices = require database_path('migrations/2026_10_21_010000_add_annual_price_and_currency_to_plans.php');
        // Remove only this synthetic annual quote before simulating the old schema.
        $plan->update(['price_yearly' => null]);
        $prices->down();
        $planBefore = (array) DB::table('plans')->find($plan->id);
        $prices->up();
        $this->assertSame($planBefore, (array) collect((array) DB::table('plans')->find($plan->id))->except(['price_yearly', 'currency'])->all());
        $this->assertNull($plan->fresh()->price_yearly);
        $this->assertSame('MXN', $plan->fresh()->currency);
        $id = DB::table('subscriptions')->insertGetId(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => '2026-01-01 00:00:00', 'ends_at' => '2030-01-01 00:00:00', 'auto_renew' => false, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-02 00:00:00']);
        $before = (array) DB::table('subscriptions')->find($id);
        $migration->up();
        $this->assertSame($before, (array) DB::table('subscriptions')->find($id));
        $contract = SubscriptionContract::where('subscription_id', $id)->firstOrFail();
        $history = $contract->getRawOriginal();
        $this->assertSame('legacy', $contract->origin);
        $this->assertNull($contract->amount);
        $this->assertNull($contract->billing_period);
        $this->assertNull($contract->currency);
        $this->assertSame('2026-01-01 00:00:00', $contract->getRawOriginal('starts_at'));
        $plan->update(['price_monthly' => 999, 'max_users' => 1]);
        $migration->up();
        $this->assertDatabaseCount('subscription_contracts', 1);
        $this->assertSame($history, $contract->fresh()->getRawOriginal());
        $this->assertSame(20, $company->effectivePlan()->max_users);
        $this->assertSame($before, (array) DB::table('subscriptions')->find($id));
    }

    public function test_frozen_user_limits_keep_plan_access_until_expiry_and_company_suspension_still_wins(): void
    {
        $this->travelTo(now()->startOfDay());
        $company = $this->company();
        $plan = $this->pro();
        $subscription = app(SubscriptionContractService::class)->create($company, $plan, 'monthly', now()->toDateTimeString(), now()->addMonth()->toDateTimeString(), 'access');
        $user = User::factory()->create(['company_id' => $company->id, 'role_id' => Role::where('name', 'employee')->value('id'), 'status' => 'active']);
        $plan->update(['max_users' => 0, 'is_active' => false]);
        $this->api($user)->assertOk();
        $company->update(['status' => 'inactive']);
        $this->api($user)->assertForbidden();
        $company->update(['status' => 'active']);
        $this->travelTo($subscription->ends_at);
        Plan::where('slug', 'free')->update(['max_users' => 0]);
        $this->api($user)->assertForbidden();
        $this->assertSame('active', $user->fresh()->status);
    }

    public static function inactiveSubscriptionStates(): array
    {
        return [['inactive'], ['cancelled'], ['expired']];
    }

    #[DataProvider('inactiveSubscriptionStates')]
    public function test_non_active_subscriptions_do_not_grant_access_even_with_future_expiry(string $state): void
    {
        $company = $this->company();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $this->pro()->id, 'status' => $state, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $this->assertNull($company->activeSubscription()->first());
        $this->assertSame('free', $company->effectivePlan()->slug);
        $this->assertDatabaseCount('subscription_contracts', 1);
    }

    public function test_quiet_creation_captures_conditions_and_duplicate_contract_is_rejected(): void
    {
        $company = $this->company();
        $subscription = new Subscription(['company_id' => $company->id, 'plan_id' => $this->pro()->id, 'status' => 'active']);
        $subscription->saveQuietly();
        $before = $subscription->contract->getRawOriginal();
        try {
            SubscriptionContract::capture($subscription);
            $this->fail('Duplicate history was accepted.');
        } catch (QueryException) {
            $this->assertDatabaseCount('subscription_contracts', 1);
            $this->assertSame($before, $subscription->contract()->first()->getRawOriginal());
        }
    }

    private function api(User $user)
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->app['tymon.jwt']->unsetToken();
        $this->app['tymon.jwt.auth']->unsetToken();

        return $this->withToken(JWTAuth::fromUser($user->fresh()))->getJson('/api/dashboard');
    }

    private function company(): Company
    {
        return Company::create(['name' => 'Company', 'email' => fake()->unique()->safeEmail(), 'status' => 'active']);
    }

    private function pro(): Plan
    {
        $plan = Plan::where('slug', 'pro')->firstOrFail();
        $plan->update(['price_monthly' => '129.50', 'price_yearly' => '1200.00']);

        return $plan->fresh();
    }
}
