<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionContract;
use App\Models\SubscriptionManualConfirmation;
use App\Models\User;
use App\Services\SubscriptionContractService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ManualProSubscriptionsTest extends TestCase
{
    use UsesInMemoryDatabase;

    private function actor(string $role = 'company_admin', bool $owner = true, string $status = 'active'): User
    {
        $company = $role === 'superadmin' ? null : Company::create(['name' => 'Manual Pro', 'email' => fake()->unique()->safeEmail(), 'status' => 'active']);

        return User::factory()->create(['company_id' => $company?->id, 'role_id' => Role::where('name', $role)->value('id'),
            'is_company_owner' => $role !== 'superadmin' && $owner, 'status' => $status]);
    }

    private function service(): SubscriptionContractService
    {
        return app(SubscriptionContractService::class);
    }

    private function catalog(): Plan
    {
        $plan = Plan::where('slug', 'pro')->firstOrFail();
        $plan->update(['price_monthly' => '199.50', 'price_yearly' => '1999.00']);

        return $plan;
    }

    private function jwt(User $user): static
    {
        Auth::forgetGuards();
        app('tymon.jwt')->unsetToken();

        return $this->withToken(JWTAuth::fromUser($user));
    }

    public static function requestPermissions(): array
    {
        return [['company_admin', true, 'active', 200], ['company_admin', false, 'active', 403],
            ['employee', false, 'active', 403], ['superadmin', false, 'active', 403], ['company_admin', true, 'inactive', 403]];
    }

    #[DataProvider('requestPermissions')]
    public function test_only_active_owner_can_request(string $role, bool $owner, string $status, int $expected): void
    {
        $this->catalog();
        $this->jwt($this->actor($role, $owner, $status))->postJson('/api/company/pro/quotes', [
            'billing_period' => 'monthly', 'request_key' => 'owner-test',
        ])->assertStatus($expected);
        $this->assertDatabaseCount('subscription_quotes', $expected === 200 ? 1 : 0);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_server_catalog_and_tenant_are_exclusive_and_request_is_idempotent(): void
    {
        $plan = $this->catalog();
        $owner = $this->actor();
        $other = $this->actor();
        $payload = ['billing_period' => 'monthly', 'request_key' => 'same', 'amount' => '0.01',
            'currency' => 'USD', 'max_users' => 999, 'company_id' => $other->company_id, 'plan_id' => 999];
        $first = $this->jwt($owner)->postJson('/api/company/pro/quotes', $payload)->assertOk()->json('quote');
        $this->assertSame($owner->company_id, $first['company_id']);
        $this->assertSame('199.50', $first['amount']);
        $this->assertSame('MXN', $first['currency']);
        $this->assertSame($plan->max_users, $first['max_users']);
        $this->jwt($owner)->postJson('/api/company/pro/quotes', $payload)->assertOk()->assertJsonPath('quote.id', $first['id']);
        $this->jwt($owner)->postJson('/api/company/pro/quotes', [...$payload, 'billing_period' => 'yearly'])->assertUnprocessable();
        $this->jwt($other)->postJson('/api/company/pro/quotes', $payload)->assertOk();
        $this->assertDatabaseCount('subscription_quotes', 2);
        $this->assertDatabaseCount('subscription_contracts', 0);
    }

    public function test_suspended_company_and_disabled_or_unconfigured_catalog_cannot_quote(): void
    {
        $plan = $this->catalog();
        $owner = $this->actor();
        $owner->company->update(['status' => 'inactive']);
        $this->jwt($owner)->postJson('/api/company/pro/quotes', ['billing_period' => 'monthly', 'request_key' => 'a'])->assertForbidden();
        $owner->company->update(['status' => 'active']);
        $plan->update(['is_active' => false]);
        $this->jwt($owner)->postJson('/api/company/pro/quotes', ['billing_period' => 'monthly', 'request_key' => 'b'])->assertUnprocessable();
        $plan->update(['is_active' => true, 'price_yearly' => null]);
        $this->jwt($owner)->postJson('/api/company/pro/quotes', ['billing_period' => 'yearly', 'request_key' => 'c'])->assertUnprocessable();
        $this->assertDatabaseCount('subscription_quotes', 0);
    }

    public static function confirmationPermissions(): array
    {
        return [['company_admin', 'active', 403], ['employee', 'active', 403], ['superadmin', 'inactive', 403], ['superadmin', 'active', 200]];
    }

    #[DataProvider('confirmationPermissions')]
    public function test_only_active_superadmin_can_confirm(string $role, string $status, int $expected): void
    {
        $this->catalog();
        $quote = $this->service()->quote($this->actor(), 'monthly', 'permission');
        $this->jwt($this->actor($role, false, $status))->postJson('/api/platform/pro/quotes/'.$quote->id.'/confirm', [
            'medium' => 'bank_transfer', 'reference' => 'External receipt 1',
        ])->assertStatus($expected);
        $this->assertDatabaseCount('subscriptions', $expected === 200 ? 1 : 0);
        $this->assertDatabaseCount('subscription_manual_confirmations', $expected === 200 ? 1 : 0);
    }

    public function test_no_authentication_and_missing_manual_evidence_are_rejected(): void
    {
        $this->postJson('/api/company/pro/quotes')->assertUnauthorized();
        $this->catalog();
        $quote = $this->service()->quote($this->actor(), 'monthly', 'evidence');
        $this->jwt($this->actor('superadmin', false))->postJson('/api/platform/pro/quotes/'.$quote->id.'/confirm', [])->assertUnprocessable();
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_frozen_quote_survives_catalog_changes_and_deactivation_and_audits_external_confirmation(): void
    {
        $plan = $this->catalog();
        $owner = $this->actor();
        $quote = $this->service()->quote($owner, 'yearly', 'frozen');
        $limits = $plan->only(SubscriptionContract::LIMITS);
        $plan->update(['price_yearly' => '9999.00', 'name' => 'Changed', 'max_users' => 1, 'is_active' => false]);
        $admin = $this->actor('superadmin', false);
        $confirmation = $this->service()->confirm($admin, $quote, 'bank_transfer', 'external-123');
        $contract = $confirmation->contract;
        $this->assertSame('1999.00', $contract->amount);
        $this->assertSame($quote->plan_name, $contract->plan_name);
        $this->assertSame($limits, $contract->only(SubscriptionContract::LIMITS));
        $this->assertSame($admin->id, $confirmation->confirmed_by);
        $this->assertSame('bank_transfer', $confirmation->medium);
        $this->assertSame('external-123', $confirmation->reference);
        $this->assertFalse(Subscription::findOrFail($contract->subscription_id)->auto_renew);
        $this->assertSame('pro', $owner->company->effectivePlan()->slug);
    }

    public static function expirationBoundaries(): array
    {
        return [[172799, true], [172800, false], [172801, false]];
    }

    #[DataProvider('expirationBoundaries')]
    public function test_exact_48_hour_boundary(int $seconds, bool $valid): void
    {
        $this->catalog();
        $this->travelTo(Carbon::parse('2026-01-01 12:00:00'));
        $quote = $this->service()->quote($this->actor(), 'monthly', 'expiry');
        $this->assertSame(172800.0, $quote->issued_at->diffInSeconds($quote->expires_at));
        $this->travelTo($quote->issued_at->addSeconds($seconds));
        try {
            $this->service()->confirm($this->actor('superadmin', false), $quote, 'transfer', 'ref');
            $this->assertTrue($valid);
        } catch (ValidationException $e) {
            $this->assertFalse($valid);
            $this->assertArrayHasKey('quote', $e->errors());
        }
        $this->assertDatabaseCount('subscriptions', $valid ? 1 : 0);
    }

    public function test_early_renewals_switch_periods_and_chain_without_overflow_or_history_changes(): void
    {
        $this->catalog();
        $this->travelTo(Carbon::parse('2028-01-31 10:00:00'));
        $owner = $this->actor();
        $admin = $this->actor('superadmin', false);
        $contracts = [];
        foreach (['monthly', 'yearly', 'monthly'] as $i => $period) {
            $quote = $this->service()->quote($owner, $period, 'chain-'.$i);
            $contracts[] = $this->service()->confirm($admin, $quote, 'transfer', 'ref-'.$i)->contract;
        }
        $this->assertSame('2028-02-29 10:00:00', $contracts[0]->ends_at->toDateTimeString());
        $this->assertTrue($contracts[0]->ends_at->equalTo($contracts[1]->starts_at));
        $this->assertSame('2029-02-28 10:00:00', $contracts[1]->ends_at->toDateTimeString());
        $this->assertTrue($contracts[1]->ends_at->equalTo($contracts[2]->starts_at));
        $this->assertSame('2029-03-28 10:00:00', $contracts[2]->ends_at->toDateTimeString());
        $this->assertSame('purchase', $contracts[0]->origin);
        $this->assertSame('renewal', $contracts[1]->origin);
        $before = $contracts[0]->getRawOriginal();
        $this->assertSame($contracts[0]->subscription_id, $owner->company->activeSubscription()->first()->id);
        $this->travelTo($contracts[1]->starts_at);
        $this->assertSame($contracts[1]->subscription_id, $owner->company->activeSubscription()->first()->id);
        $this->assertSame($before, $contracts[0]->fresh()->getRawOriginal());
    }

    public function test_lapsed_renewal_starts_now_and_successful_retry_is_idempotent_after_expiry(): void
    {
        $this->catalog();
        $owner = $this->actor();
        $admin = $this->actor('superadmin', false);
        $first = $this->service()->confirm($admin, $this->service()->quote($owner, 'monthly', 'first'), 'transfer', 'one');
        $this->travelTo($first->contract->ends_at->copy()->addDay());
        $quote = $this->service()->quote($owner, 'monthly', 'second');
        $second = $this->service()->confirm($admin, $quote, 'cash', 'two');
        $this->assertTrue($second->contract->starts_at->equalTo(now()->startOfSecond()));
        $this->travelTo($quote->expires_at->addDay());
        $retry = $this->service()->confirm($admin, $quote, 'other', 'changed');
        $this->assertSame($second->id, $retry->id);
        $this->assertSame('cash', $retry->medium);
        $this->assertDatabaseCount('subscriptions', 2);
        $this->assertDatabaseCount('subscription_manual_confirmations', 2);
    }

    public function test_open_ended_legacy_period_blocks_request_and_confirmation(): void
    {
        $plan = $this->catalog();
        $owner = $this->actor();
        $quote = $this->service()->quote($owner, 'monthly', 'before-legacy');
        Subscription::create(['company_id' => $owner->company_id, 'plan_id' => $plan->id, 'status' => 'active']);
        foreach (['quote', 'confirm'] as $operation) {
            try {
                $operation === 'quote' ? $this->service()->quote($owner, 'monthly', 'blocked')
                    : $this->service()->confirm($this->actor('superadmin', false), $quote, 'cash', 'ref');
                $this->fail('An open-ended period must block new contracting.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('subscription', $e->errors());
            }
        }
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('subscription_manual_confirmations', 0);
    }

    public function test_confirmation_failure_rolls_back_subscription_and_contract(): void
    {
        $this->catalog();
        $quote = $this->service()->quote($this->actor(), 'monthly', 'rollback');
        DB::unprepared("CREATE TRIGGER reject_manual_confirmation BEFORE INSERT ON subscription_manual_confirmations BEGIN SELECT RAISE(ABORT, 'Simulated audit failure'); END");
        try {
            $this->service()->confirm($this->actor('superadmin', false), $quote, 'cash', 'ref');
            $this->fail('Expected audit failure.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Simulated audit failure', $e->getMessage());
        }
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('subscription_contracts', 0);
        $this->assertDatabaseCount('subscription_manual_confirmations', 0);
        $this->assertDatabaseCount('subscription_quotes', 1);
    }

    public function test_expired_request_key_does_not_requote_and_new_key_is_required(): void
    {
        $plan = $this->catalog();
        $owner = $this->actor();
        $quote = $this->service()->quote($owner, 'monthly', 'original');
        $this->travelTo($quote->expires_at);
        $plan->update(['price_monthly' => '299.00']);
        $retry = $this->service()->quote($owner, 'monthly', 'original');
        $this->assertSame($quote->id, $retry->id);
        $this->assertTrue($retry->expires_at->equalTo($quote->expires_at));
        $new = $this->service()->quote($owner, 'monthly', 'new-request');
        $this->assertNotSame($quote->id, $new->id);
        $this->assertSame('299.00', $new->amount);
        $this->assertTrue($new->expires_at->equalTo(now()->addHours(48)->startOfSecond()));
    }

    public function test_suspension_after_quote_blocks_confirmation_without_creating_history(): void
    {
        $this->catalog();
        $owner = $this->actor();
        $quote = $this->service()->quote($owner, 'monthly', 'suspend');
        $owner->company->update(['status' => 'inactive']);
        $this->jwt($this->actor('superadmin', false))->postJson('/api/platform/pro/quotes/'.$quote->id.'/confirm', [
            'medium' => 'transfer', 'reference' => 'external-ref',
        ])->assertForbidden();
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('subscription_contracts', 0);
        $this->assertDatabaseCount('subscription_manual_confirmations', 0);
    }

    public function test_confirmation_unique_constraints_prevent_reusing_quote_or_contract(): void
    {
        $this->catalog();
        $owner = $this->actor();
        $admin = $this->actor('superadmin', false);
        $firstQuote = $this->service()->quote($owner, 'monthly', 'unique-first');
        $secondQuote = $this->service()->quote($owner, 'yearly', 'unique-second');
        $confirmation = $this->service()->confirm($admin, $firstQuote, 'cash', 'external');
        foreach ([['quote_id' => $firstQuote->id], ['quote_id' => $secondQuote->id]] as $override) {
            try {
                SubscriptionManualConfirmation::create([...$confirmation->getAttributes(), 'id' => 999, ...$override]);
                $this->fail('A contract or quote cannot be confirmed twice.');
            } catch (QueryException) {
                $this->assertDatabaseCount('subscription_manual_confirmations', 1);
            }
        }
    }

    public function test_quote_and_confirmation_history_cannot_be_updated_deleted_or_replaced(): void
    {
        $this->catalog();
        $quote = $this->service()->quote($this->actor(), 'monthly', 'immutable');
        $confirmation = $this->service()->confirm($this->actor('superadmin', false), $quote, 'cash', 'ref');
        foreach (['subscription_quotes' => $quote, 'subscription_manual_confirmations' => $confirmation] as $table => $record) {
            $row = (array) DB::table($table)->where('id', $record->id)->first();
            foreach (['update', 'delete', 'replace'] as $operation) {
                try {
                    match ($operation) {
                        'update' => DB::table($table)->where('id', $record->id)->update(['id' => $record->id]),
                        'delete' => DB::table($table)->where('id', $record->id)->delete(),
                        'replace' => DB::insert('INSERT OR REPLACE INTO '.$table.' ('.implode(',', array_keys($row)).') VALUES ('.implode(',', array_fill(0, count($row), '?')).')', array_values($row)),
                    };
                    $this->fail('Expected immutable history.');
                } catch (QueryException) {
                    $this->assertSame($row, (array) DB::table($table)->where('id', $record->id)->first());
                }
            }
        }
        $this->artisan('subscriptions:verify-integrity')->assertExitCode(0);
    }
}
