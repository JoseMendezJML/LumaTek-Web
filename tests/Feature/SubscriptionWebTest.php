<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SubscriptionQuote;
use App\Models\User;
use App\Services\SubscriptionContractService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SubscriptionWebTest extends TestCase
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

    private function web(User $user): static
    {
        Auth::forgetGuards();

        return $this->actingAs($user, 'web')->withSession([User::AUTH_VERSION_SESSION_KEY => [
            'user_id' => $user->id, 'version' => $user->authenticationVersion(),
        ]]);
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

    private function confirmationPayload(): array
    {
        return ['medium' => 'Transferencia bancaria', 'reference' => 'EXTERNA-123', 'payment_verified' => '1'];
    }

    public static function mailtoPeriods(): array
    {
        return [['monthly', 'Mensual', '199.50'], ['yearly', 'Anual', '1999.00']];
    }

    #[DataProvider('mailtoPeriods')]
    public function test_pending_quote_contact_uses_frozen_server_data_and_rfc3986_encoding(string $period, string $label, string $amount): void
    {
        config(['subscriptions.quote_contact_email' => 'administracion+pro@example.com']);
        $owner = $this->actor();
        $name = 'Cultivos Álamo & Hijos + "Norte" #1? bcc=ajeno@example.com';
        $owner->company->update(['name' => $name]);
        $quote = $this->quote($owner, $period);
        $other = $this->actor();
        Plan::where('slug', 'pro')->firstOrFail()->update(['price_monthly' => '888.00', 'price_yearly' => '8888.00']);
        $page = $this->web($owner)->get('/subscription?company_id='.$other->company_id.'&amount=0.01&currency=USD')->assertOk()
            ->assertSee('Solicitar instrucciones por correo')->assertSee('Revisa el mensaje y envíalo desde esa aplicación');
        $this->assertSame(1, preg_match('/class="subscription-contact-button" href="([^"]+)"/', $page->getContent(), $matches));
        $url = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $parts = parse_url($url);
        $this->assertSame('mailto', $parts['scheme']);
        $this->assertSame('administracion+pro@example.com', rawurldecode($parts['path']));
        parse_str($parts['query'], $headers);
        $this->assertSame(['subject', 'body'], array_keys($headers));
        $this->assertSame('LumaTek - Instrucciones de pago para cotización #'.$quote->id, $headers['subject']);
        $this->assertStringContainsString('Cotización: #'.$quote->id, $headers['body']);
        $this->assertStringContainsString('Empresa: '.$name, $headers['body']);
        $this->assertStringContainsString('Modalidad: '.$label, $headers['body']);
        $this->assertStringContainsString('Importe cotizado: '.$amount.' MXN', $headers['body']);
        $this->assertStringContainsString('medios de pago disponibles', $headers['body']);
        $this->assertStringContainsString('instrucciones para completar la contratación', $headers['body']);
        $this->assertStringContainsString('%20', $url);
        $this->assertStringContainsString('%0D%0A', $url);
        $this->assertStringContainsString('%C3', $url);
        $this->assertStringContainsString('%26', $url);
        $this->assertStringContainsString('%2B', $url);
        $this->assertStringNotContainsString(' ', $url);
        $this->assertStringNotContainsString('+', $url);
        $this->assertStringNotContainsString('USD', $headers['body']);
        $this->assertStringNotContainsString($owner->password, $headers['body']);
        $this->assertStringNotContainsString('request_key', $headers['body']);
        $this->assertStringNotContainsString('JWT', $headers['body']);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('subscription_manual_confirmations', 0);
    }

    public static function unavailableContactRecipients(): array
    {
        return [[null], [''], ['not-an-email'], ["admin@example.com\r\nBcc: other@example.com"]];
    }

    #[DataProvider('unavailableContactRecipients')]
    public function test_pending_quote_without_valid_recipient_shows_contact_instructions(?string $recipient): void
    {
        config(['subscriptions.quote_contact_email' => $recipient]);
        $owner = $this->actor();
        $this->quote($owner);
        $this->web($owner)->get('/subscription')->assertOk()->assertSee('Pendiente')
            ->assertSee('El contacto por correo no está disponible')->assertSee('canal de contacto habitual')
            ->assertDontSee('Solicitar instrucciones por correo')->assertDontSee('mailto:', false);
    }

    public function test_contact_only_appears_for_pending_quotes_until_exact_expiration(): void
    {
        config(['subscriptions.quote_contact_email' => 'administracion@example.com']);
        $this->travelTo(Carbon::parse('2026-10-01 12:00:00'));
        $owner = $this->actor();
        $pending = $this->quote($owner);
        $confirmed = $this->quote($owner);
        app(SubscriptionContractService::class)->confirm($this->actor('superadmin', false), $confirmed, 'Transferencia', 'SENSITIVE-REFERENCE');
        $confirmedUrlSubject = rawurlencode('LumaTek - Instrucciones de pago para cotización #'.$confirmed->id);
        $this->travelTo($pending->expires_at->subSecond());
        $page = $this->web($owner)->get('/subscription')->assertOk()->assertSee('Solicitar instrucciones por correo');
        $this->assertSame(1, substr_count($page->getContent(), 'class="subscription-contact-button"'));
        $page->assertDontSee($confirmedUrlSubject, false)->assertDontSee('SENSITIVE-REFERENCE');
        $this->travelTo($pending->expires_at);
        $this->web($owner)->get('/subscription')->assertOk()->assertSee('Vencida')->assertSee('Confirmada')
            ->assertDontSee('Solicitar instrucciones por correo')->assertDontSee('mailto:', false);
    }

    public static function ownerPermissions(): array
    {
        return [['company_admin', true, 'active', 200], ['company_admin', false, 'active', 403],
            ['employee', false, 'active', 403], ['superadmin', false, 'active', 403], ['company_admin', true, 'inactive', 403]];
    }

    #[DataProvider('ownerPermissions')]
    public function test_owner_routes_enforce_access(string $role, bool $owner, string $status, int $expected): void
    {
        $this->catalog();
        $actor = $this->actor($role, $owner, $status);
        $this->web($actor)->getJson('/subscription')->assertStatus($expected);
        $this->web($actor)->postJson('/subscription/quotes', ['billing_period' => 'monthly', 'request_key' => (string) Str::uuid()])
            ->assertStatus($expected === 200 ? 302 : 403);
        $this->assertDatabaseCount('subscription_quotes', $expected === 200 ? 1 : 0);
    }

    public function test_owner_can_renew_expired_pro_but_not_a_suspended_company(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01'));
        $owner = $this->actor();
        $plan = $this->catalog();
        $subscription = app(SubscriptionContractService::class)->create($owner->company, $plan, 'monthly',
            now()->toDateTimeString(), now()->addMonth()->toDateTimeString(), 'expired-pro');
        $this->travelTo($subscription->ends_at);
        $this->web($owner)->get('/subscription')->assertOk()->assertSee('Gratis')->assertSee('Solicitar cotización');
        $this->post('/subscription/quotes', ['billing_period' => 'yearly', 'request_key' => (string) Str::uuid()])->assertRedirect('/subscription');
        $owner->company->update(['status' => 'inactive']);
        $this->web($owner)->getJson('/subscription')->assertForbidden();
        $this->web($owner)->postJson('/subscription/quotes', [])->assertForbidden();
    }

    public function test_web_requires_session_and_does_not_accept_jwt_only(): void
    {
        $this->get('/subscription')->assertRedirect('/login');
        $this->get('/platform/subscriptions')->assertRedirect('/login');
        $owner = $this->actor();
        $this->withToken(JWTAuth::fromUser($owner))->getJson('/subscription')->assertUnauthorized();
        $this->postJson('/subscription/quotes', [])->assertUnauthorized();
        $this->withToken(JWTAuth::fromUser($this->actor('superadmin', false)))->getJson('/platform/subscriptions')->assertUnauthorized();
        $this->assertDatabaseCount('subscription_quotes', 0);
    }

    public function test_owner_navigation_is_hidden_from_employees_and_additional_admins(): void
    {
        Plan::where('slug', 'free')->firstOrFail()->update(['max_additional_admins' => 1]);
        $this->web($this->actor())->get('/dashboard')->assertOk()->assertSee('href="'.route('subscription.index').'"', false);
        foreach (['employee', 'company_admin'] as $role) {
            $this->web($this->actor($role, false))->get('/dashboard')->assertOk()->assertDontSee('href="'.route('subscription.index').'"', false);
        }
    }

    public function test_quote_uses_server_catalog_and_prg_and_retry_preserves_id(): void
    {
        $this->catalog();
        $owner = $this->actor();
        $other = $this->actor();
        $page = $this->web($owner)->get('/subscription')->assertOk();
        $payload = ['billing_period' => 'yearly', 'request_key' => $page->viewData('requestKey'),
            'company_id' => $other->company_id, 'amount' => '0.01', 'max_users' => 999, 'currency' => 'USD', 'starts_at' => '2000-01-01'];
        $this->post('/subscription/quotes', $payload)->assertRedirect('/subscription')->assertSessionHas('status');
        $this->post('/subscription/quotes', $payload)->assertRedirect('/subscription');
        $this->assertDatabaseCount('subscription_quotes', 1);
        $quote = SubscriptionQuote::firstOrFail();
        $this->assertSame($owner->company_id, $quote->company_id);
        $this->assertSame('1999.00', $quote->amount);
        $this->assertSame('MXN', $quote->currency);
        $this->assertNotSame(999, $quote->max_users);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('subscription_contracts', 0);
        $this->get('/subscription')->assertSee('Cotización #'.$quote->id)->assertSee('Pendiente')->assertSee('1,999.00');
    }

    public function test_owner_history_is_scoped_to_company_and_requester_and_paginated(): void
    {
        $owner = $this->actor();
        $otherOwner = $this->actor('company_admin', true, 'active', $owner->company);
        $foreign = $this->actor();
        $own = $this->quote($owner);
        $sameCompanyOther = $this->quote($otherOwner);
        $foreignQuote = $this->quote($foreign);
        for ($i = 0; $i < 10; $i++) {
            $this->quote($owner);
        }
        $page = $this->web($owner)->get('/subscription?company_id='.$foreign->company_id.'&requested_by='.$otherOwner->id)->assertOk();
        $quotes = $page->viewData('quotes');
        $this->assertSame(11, $quotes->total());
        $this->assertCount(10, $quotes->items());
        $page->assertDontSee('id="quote-'.$sameCompanyOther->id.'"', false)->assertDontSee('id="quote-'.$foreignQuote->id.'"', false);
        $this->get('/subscription?page=2')->assertOk()->assertSee('id="quote-'.$own->id.'"', false);
    }

    public function test_states_expiration_and_confirmed_state_precedence(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 12:00:00'));
        $owner = $this->actor();
        $quote = $this->quote($owner);
        $confirmed = $this->quote($owner);
        app(SubscriptionContractService::class)->confirm($this->actor('superadmin', false), $confirmed, 'Transferencia', 'EXT');
        $this->web($owner)->get('/subscription')->assertOk()->assertSee('Pendiente')->assertSee('Confirmada');
        $this->travelTo($quote->expires_at);
        $this->web($owner)->get('/subscription')->assertOk()->assertSee('Vencida')->assertSee('Confirmada')->assertDontSee('subscription-state-pending', false);
    }

    public function test_current_and_scheduled_contracts_keep_historical_limits_and_prices(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01'));
        $owner = $this->actor();
        $plan = $this->catalog();
        $plan->update(['max_users' => 47]);
        $service = app(SubscriptionContractService::class);
        $quote = $service->quote($owner, 'monthly', (string) Str::uuid());
        $admin = $this->actor('superadmin', false);
        $first = $service->confirm($admin, $quote, 'Transferencia', 'first');
        $next = $service->confirm($admin, $this->quote($owner, 'yearly'), 'Transferencia', 'second');
        $before = DB::table('subscription_contracts')->orderBy('id')->get()->toJson();
        $plan->update(['max_users' => 1, 'price_monthly' => '999.00', 'price_yearly' => '9999.00']);
        $page = $this->web($owner)->get('/subscription')->assertOk()->assertSee('Renovaciones programadas')->assertSee('47')->assertSee('199.50')->assertSee('1,999.00');
        $this->assertSame(47, $page->viewData('plan')->max_users);
        $this->assertSame($first->contract->subscription_id, $page->viewData('current')->id);
        $this->assertSame($next->contract->subscription_id, $page->viewData('renewals')->first()->id);
        $this->assertSame($before, DB::table('subscription_contracts')->orderBy('id')->get()->toJson());
    }

    public function test_unconfigured_annual_and_disabled_pro_show_clear_messages_and_service_rejects_posts(): void
    {
        $owner = $this->actor();
        $plan = $this->catalog();
        $plan->update(['price_yearly' => null]);
        $this->web($owner)->get('/subscription')->assertOk()->assertSee('Sin configurar')->assertDontSee('value="yearly"', false);
        $this->from('/subscription')->post('/subscription/quotes', ['billing_period' => 'yearly', 'request_key' => (string) Str::uuid()])
            ->assertRedirect('/subscription')->assertSessionHasErrors('subscription');
        $plan->update(['is_active' => false]);
        $this->web($owner)->get('/subscription')->assertOk()->assertSee('Pro no está disponible')->assertDontSee('type="submit">Solicitar cotización', false);
        $this->from('/subscription')->post('/subscription/quotes', ['billing_period' => 'monthly', 'request_key' => (string) Str::uuid()])
            ->assertRedirect('/subscription')->assertSessionHasErrors('subscription');
        $this->assertDatabaseCount('subscription_quotes', 0);
    }

    public static function platformPermissions(): array
    {
        return [['company_admin', true, 'active', 403], ['company_admin', false, 'active', 403],
            ['employee', false, 'active', 403], ['superadmin', false, 'inactive', 403], ['superadmin', false, 'active', 200]];
    }

    #[DataProvider('platformPermissions')]
    public function test_platform_authorizes_before_looking_up_existing_or_missing_quotes(string $role, bool $owner, string $status, int $expected): void
    {
        config(['app.debug' => false]);
        $quote = $this->quote($this->actor());
        $actor = $this->actor($role, $owner, $status);
        $this->web($actor)->getJson('/platform/subscriptions')->assertStatus($expected);
        DB::enableQueryLog();
        foreach ([$quote->id, $quote->id + 1000] as $id) {
            $this->web($actor)->getJson('/platform/subscriptions/'.$id)->assertStatus($expected === 200 ? ($id === $quote->id ? 200 : 404) : 403);
            $this->web($actor)->postJson('/platform/subscriptions/'.$id.'/confirm', $this->confirmationPayload())
                ->assertStatus($expected === 200 ? ($id === $quote->id ? 302 : 404) : 403);
        }
        $quoteQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'subscription_quotes'));
        DB::disableQueryLog();
        if ($expected !== 200) {
            $this->assertCount(0, $quoteQueries);
            $this->assertDatabaseCount('subscription_manual_confirmations', 0);
        }
    }

    public function test_confirmation_requires_explicit_verification_and_is_idempotent_after_expiration(): void
    {
        $quote = $this->quote($this->actor());
        $admin = $this->actor('superadmin', false);
        $url = '/platform/subscriptions/'.$quote->id;
        $payload = $this->confirmationPayload();
        $this->web($admin)->from($url)->post($url.'/confirm', ['medium' => 'Transferencia', 'reference' => 'EXT'])
            ->assertRedirect($url)->assertSessionHasErrors('payment_verified');
        $this->assertDatabaseCount('subscriptions', 0);
        $this->from($url)->post($url.'/confirm', $payload)->assertRedirect($url)->assertSessionHas('status');
        $this->get($url)->assertOk()->assertSee('Confirmación manual registrada')->assertSee('EXTERNA-123')
            ->assertDontSee('Registrar confirmación manual');
        $this->travelTo($quote->expires_at->addDay());
        $this->web($admin)->post($url.'/confirm', [...$payload, 'reference' => 'changed'])->assertRedirect($url);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('subscription_contracts', 1);
        $this->assertDatabaseCount('subscription_manual_confirmations', 1);
        $this->assertDatabaseHas('subscription_manual_confirmations', ['reference' => 'EXTERNA-123']);
    }

    public function test_expired_quote_cannot_be_confirmed_but_live_quote_survives_catalog_deactivation(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01'));
        $quote = $this->quote($this->actor());
        $admin = $this->actor('superadmin', false);
        $this->travelTo($quote->expires_at);
        $url = '/platform/subscriptions/'.$quote->id;
        $this->web($admin)->get($url)->assertOk()->assertSee('La cotización venció')->assertDontSee('Registrar confirmación manual');
        $this->from($url)->post($url.'/confirm', $this->confirmationPayload())->assertRedirect($url)->assertSessionHasErrors('confirmation');
        $this->assertDatabaseCount('subscriptions', 0);
        $owner = $this->actor();
        $live = $this->quote($owner);
        Plan::where('slug', 'pro')->firstOrFail()->update(['is_active' => false, 'price_monthly' => '999.00', 'max_users' => 1]);
        $this->web($admin)->post('/platform/subscriptions/'.$live->id.'/confirm', $this->confirmationPayload())->assertRedirect();
        $this->assertDatabaseHas('subscription_contracts', ['amount' => '199.50', 'max_users' => $live->max_users]);
    }

    public function test_listing_pagination_and_eager_loading_and_escaped_details(): void
    {
        $owner = $this->actor();
        $owner->update(['name' => '<script>owner()</script>']);
        $owner->company->update(['name' => '<script>company()</script>']);
        $quote = $this->quote($owner);
        $admin = $this->actor('superadmin', false);
        $this->web($admin);
        DB::enableQueryLog();
        $this->get('/platform/subscriptions')->assertOk()->assertSee('Solicitudes Pro');
        $small = count(DB::getQueryLog());
        DB::flushQueryLog();
        DB::disableQueryLog();
        for ($i = 0; $i < 15; $i++) {
            $this->quote($owner);
        }
        DB::enableQueryLog();
        $page = $this->get('/platform/subscriptions')->assertOk()->assertSee('&lt;script&gt;owner()', false)->assertDontSee('<script>owner()', false);
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($small + 2, $large);
        $this->assertSame(16, $page->viewData('quotes')->total());
        $this->assertCount(15, $page->viewData('quotes')->items());
        $this->get('/platform/subscriptions?page=2')->assertSee('Cotización #'.$quote->id);
        app(SubscriptionContractService::class)->confirm($admin, $quote, '<script>medium()</script>', '<script>reference()</script>');
        $this->get('/platform/subscriptions/'.$quote->id)->assertOk()->assertSee('&lt;script&gt;reference()', false)
            ->assertDontSee('<script>reference()', false)->assertDontSee('<script>company()', false);
    }

    public static function sessionRoles(): array
    {
        return [['company_admin'], ['superadmin']];
    }

    #[DataProvider('sessionRoles')]
    public function test_revoked_session_cannot_read_or_write(string $role): void
    {
        $actor = $this->actor($role, $role === 'company_admin');
        $oldVersion = $actor->authenticationVersion();
        $actor->update(['status' => 'inactive']);
        $actor->update(['status' => 'active']);
        $url = $role === 'company_admin' ? '/subscription' : '/platform/subscriptions';
        $marker = [User::AUTH_VERSION_SESSION_KEY => ['user_id' => $actor->id, 'version' => $oldVersion]];
        $this->actingAs($actor->fresh(), 'web')->withSession($marker)->get($url)->assertRedirect('/login')->assertSessionHas('error');
        $this->actingAs($actor->fresh(), 'web')->withSession($marker)
            ->post($role === 'company_admin' ? '/subscription/quotes' : '/platform/subscriptions/999/confirm', [])
            ->assertRedirect('/login');
        $this->assertDatabaseCount('subscription_quotes', 0);
    }

    public function test_csrf_is_actually_enforced_for_both_write_routes(): void
    {
        // Activate real token checks while retaining the guarded testing database.
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $owner = $this->actor();
        $this->catalog();
        $token = Str::random(40);
        $payload = ['billing_period' => 'monthly', 'request_key' => (string) Str::uuid()];
        $this->web($owner)->withSession(['_token' => $token])->post('/subscription/quotes', $payload)->assertStatus(419);
        $this->post('/subscription/quotes', [...$payload, '_token' => 'invalid'])->assertStatus(419);
        $this->assertDatabaseCount('subscription_quotes', 0);
        $this->post('/subscription/quotes', [...$payload, '_token' => $token])->assertRedirect('/subscription');
        $quote = SubscriptionQuote::firstOrFail();
        $admin = $this->actor('superadmin', false);
        $url = '/platform/subscriptions/'.$quote->id.'/confirm';
        $this->web($admin)->withSession(['_token' => $token])->post($url, $this->confirmationPayload())->assertStatus(419);
        $this->post($url, [...$this->confirmationPayload(), '_token' => 'invalid'])->assertStatus(419);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->post($url, [...$this->confirmationPayload(), '_token' => $token])->assertRedirect();
        $this->assertDatabaseCount('subscription_manual_confirmations', 1);
    }
}
