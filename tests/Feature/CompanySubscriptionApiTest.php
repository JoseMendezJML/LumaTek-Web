<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SubscriptionQuote;
use App\Models\User;
use App\Services\SubscriptionContractService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * GET /api/company/subscription: la misma información que "Mi suscripción" en la web,
 * para la app móvil.
 */
class CompanySubscriptionApiTest extends TestCase
{
    use UsesInMemoryDatabase;

    private function actor(string $role = 'company_admin', bool $owner = true, string $status = 'active', ?Company $company = null): User
    {
        if ($role !== 'superadmin') {
            $company ??= Company::create(['name' => 'Empresa '.Str::random(8), 'email' => fake()->unique()->safeEmail(), 'status' => 'active']);
        }

        return User::factory()->create(['company_id' => $company?->id, 'role_id' => Role::where('name', $role)->value('id'),
            'is_company_owner' => $role !== 'superadmin' && $owner, 'status' => $status]);
    }

    private function catalog(): Plan
    {
        $plan = Plan::where('slug', 'pro')->firstOrFail();
        $plan->update(['price_monthly' => '199.50', 'price_yearly' => '1999.00']);

        return $plan;
    }

    private function quote(User $owner, string $period = 'monthly'): SubscriptionQuote
    {
        $this->catalog();

        return app(SubscriptionContractService::class)->quote($owner, $period, (string) Str::uuid());
    }

    private function api(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken(JWTAuth::fromUser($user));
    }

    public function test_requires_token(): void
    {
        $this->getJson('/api/company/subscription')->assertUnauthorized();
    }

    public static function permissions(): array
    {
        return [
            'propietario' => ['company_admin', true, 'active', 200],
            'administrador adicional' => ['company_admin', false, 'active', 403],
            'empleado' => ['employee', false, 'active', 403],
            'superadmin' => ['superadmin', false, 'active', 403],
            'propietario inactivo' => ['company_admin', true, 'inactive', 403],
        ];
    }

    #[DataProvider('permissions')]
    public function test_only_the_active_owner_can_read_it(string $role, bool $owner, string $status, int $expected): void
    {
        $this->api($this->actor($role, $owner, $status))->getJson('/api/company/subscription')->assertStatus($expected);
    }

    public function test_suspended_company_is_rejected(): void
    {
        $owner = $this->actor();
        $owner->company->update(['status' => 'inactive']);

        $this->api($owner)->getJson('/api/company/subscription')->assertForbidden();
    }

    public function test_free_plan_without_contracts(): void
    {
        $this->catalog();

        $this->api($this->actor())->getJson('/api/company/subscription')
            ->assertOk()
            ->assertJsonPath('plan.slug', 'free')
            ->assertJsonPath('plan.name', 'Gratis')
            ->assertJsonPath('plan.limits.max_greenhouses', 2)
            ->assertJsonPath('current', null)
            ->assertJsonPath('renewals', [])
            ->assertJsonPath('quotes', [])
            ->assertJsonPath('pro.available', true)
            ->assertJsonPath('pro.price_monthly', '199.50')
            ->assertJsonPath('pro.price_yearly', '1999.00')
            ->assertJsonPath('pro.currency', 'MXN');
    }

    public function test_owner_can_still_read_it_after_pro_expires(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01'));
        $owner = $this->actor();
        $subscription = app(SubscriptionContractService::class)->create($owner->company, $this->catalog(), 'monthly',
            now()->toDateTimeString(), now()->addMonth()->toDateTimeString(), 'expired-pro');
        $this->travelTo($subscription->ends_at);

        $this->api($owner)->getJson('/api/company/subscription')
            ->assertOk()
            ->assertJsonPath('plan.slug', 'free')
            ->assertJsonPath('current', null);
    }

    public function test_current_and_renewals_keep_the_contracted_limits_and_prices(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 12:00:00'));
        $owner = $this->actor();
        $plan = $this->catalog();
        $plan->update(['max_users' => 47]);
        $service = app(SubscriptionContractService::class);
        $admin = $this->actor('superadmin', false);
        $service->confirm($admin, $this->quote($owner, 'monthly'), 'Transferencia', 'first');
        $service->confirm($admin, $this->quote($owner, 'yearly'), 'Transferencia', 'second');
        $plan->update(['max_users' => 1, 'price_monthly' => '999.00', 'price_yearly' => '9999.00']);

        $response = $this->api($owner)->getJson('/api/company/subscription')->assertOk();

        $response
            ->assertJsonPath('plan.slug', 'pro')
            ->assertJsonPath('plan.limits.max_users', 47)
            ->assertJsonPath('current.billing_period', 'monthly')
            ->assertJsonPath('current.amount', '199.50')
            ->assertJsonPath('current.currency', 'MXN')
            ->assertJsonPath('current.starts_at', '2026-10-01T12:00:00-06:00')
            ->assertJsonCount(1, 'renewals')
            ->assertJsonPath('renewals.0.billing_period', 'yearly')
            ->assertJsonPath('renewals.0.amount', '1999.00')
            ->assertJsonPath('pro.price_monthly', '999.00');
        $this->assertSame($response->json('current.ends_at'), $response->json('renewals.0.starts_at'));
    }

    public function test_quotes_are_scoped_to_company_and_requester_newest_first_max_ten(): void
    {
        $owner = $this->actor();
        $otherOwner = $this->actor('company_admin', true, 'active', $owner->company);
        $foreign = $this->actor();
        $this->quote($otherOwner);
        $this->quote($foreign);
        $quotes = collect(range(1, 11))->map(fn () => $this->quote($owner));

        $response = $this->api($owner)->getJson('/api/company/subscription?company_id='.$foreign->company_id)->assertOk();

        $response->assertJsonCount(10, 'quotes');
        $this->assertSame(
            $quotes->reverse()->take(10)->pluck('id')->values()->all(),
            array_column($response->json('quotes'), 'id')
        );
    }

    public function test_quote_states_contract_and_contact_link(): void
    {
        config(['subscriptions.quote_contact_email' => 'administracion@example.com']);
        $this->travelTo(Carbon::parse('2026-10-01 12:00:00'));
        $owner = $this->actor();
        $pending = $this->quote($owner);
        $confirmed = $this->quote($owner, 'yearly');
        app(SubscriptionContractService::class)->confirm($this->actor('superadmin', false), $confirmed, 'Transferencia', 'EXT');

        $quotes = collect($this->api($owner)->getJson('/api/company/subscription')->assertOk()->json('quotes'))->keyBy('id');

        $this->assertSame('pending', $quotes[$pending->id]['status']);
        $this->assertSame('199.50', $quotes[$pending->id]['amount']);
        $this->assertStringStartsWith('mailto:administracion@example.com', $quotes[$pending->id]['contact_url']);
        $this->assertNull($quotes[$pending->id]['contract']);
        $this->assertSame('confirmed', $quotes[$confirmed->id]['status']);
        $this->assertNull($quotes[$confirmed->id]['contact_url']);
        $this->assertNotNull($quotes[$confirmed->id]['contract']['starts_at']);

    }

    public function test_confirmation_prevails_and_pending_quotes_expire(): void
    {
        config(['subscriptions.quote_contact_email' => 'administracion@example.com']);
        $this->travelTo(Carbon::parse('2026-10-01 12:00:00'));
        $owner = $this->actor();
        $pending = $this->quote($owner);
        $confirmed = $this->quote($owner, 'yearly');
        app(SubscriptionContractService::class)->confirm($this->actor('superadmin', false), $confirmed, 'Transferencia', 'EXT');
        $this->travelTo($pending->expires_at);

        $quotes = collect($this->api($owner)->getJson('/api/company/subscription')->assertOk()->json('quotes'))->keyBy('id');

        $this->assertSame('expired', $quotes[$pending->id]['status']);
        $this->assertNull($quotes[$pending->id]['contact_url']);
        $this->assertSame('confirmed', $quotes[$confirmed->id]['status']);
    }

    public function test_unconfigured_annual_price_and_disabled_pro(): void
    {
        $this->catalog()->update(['price_yearly' => null, 'is_active' => false]);

        $this->api($this->actor())->getJson('/api/company/subscription')
            ->assertOk()
            ->assertJsonPath('pro.available', false)
            ->assertJsonPath('pro.price_yearly', null);
    }

    public function test_quote_request_from_the_api_appears_in_the_summary(): void
    {
        $this->catalog();
        $owner = $this->actor();
        $key = (string) Str::uuid();

        $this->api($owner)->postJson('/api/company/pro/quotes', ['billing_period' => 'monthly', 'request_key' => $key])->assertOk();
        $this->api($owner)->postJson('/api/company/pro/quotes', ['billing_period' => 'monthly', 'request_key' => $key])->assertOk();

        $this->api($owner)->getJson('/api/company/subscription')
            ->assertJsonCount(1, 'quotes')
            ->assertJsonPath('quotes.0.status', 'pending')
            ->assertJsonPath('quotes.0.billing_period', 'monthly');
    }
}
