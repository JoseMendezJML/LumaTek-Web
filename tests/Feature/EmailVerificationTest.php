<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase;

    private const PASSWORD = 'Valid-Password-123!';

    private function pending(): User
    {
        $user = $this->user($this->company());
        $user->forceFill(['email_verified_at' => null, 'password' => self::PASSWORD])->save();

        return $user;
    }

    private function link(User $user, int $minutes = 60, array $override = []): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), array_replace([
            'id' => $user->id, 'hash' => sha1($user->getEmailForVerification()),
        ], $override));
    }

    private function registration(): array
    {
        return ['company_name' => 'Verification company', 'name' => 'Owner', 'email' => 'owner@example.test',
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD];
    }

    public function test_registration_commits_pending_owner_and_subscription_before_notifying(): void
    {
        Notification::fake();
        $this->postJson('/api/register', $this->registration())->assertCreated()
            ->assertJsonPath('verification_status', 'pending')->assertJsonPath('verification_url', route('verification.notice'))
            ->assertJsonMissingPath('access_token');
        $user = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame('company_admin', $user->role->name);
        $this->assertTrue($user->is_company_owner);
        $this->assertSame('free', $user->company->activeSubscription()->first()->plan->slug);
        Notification::assertSentTo($user, VerifyEmail::class, function ($notification) use ($user) {
            $this->assertSame(0, \Illuminate\Support\Facades\DB::transactionLevel());
            $this->assertSame(1, Company::count());
            $this->assertSame(1, Subscription::count());
            $this->get($notification->toMail($user)->actionUrl, ['Accept' => 'text/html'])->assertOk()->assertSee('Iniciar sesión');

            return true;
        });
    }

    public function test_transport_failure_keeps_account_and_provides_a_safe_retry(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP secret that must never be exposed'));
        Log::shouldReceive('warning')->once()->with('email_verification_delivery_failed', Mockery::on(fn ($context) => array_keys($context) === ['user_id']));
        $this->postJson('/api/register', $this->registration())->assertCreated()
            ->assertJsonPath('verification_status', 'delivery_failed')->assertDontSee('SMTP secret')
            ->assertJsonMissingPath('access_token');
        $user = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('subscriptions', 1);
        Notification::fake();
        $this->postJson(route('verification.send'), ['email' => $user->email, 'password' => self::PASSWORD])->assertStatus(202);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_valid_browser_link_and_reuse_have_clear_states_without_authentication(): void
    {
        Event::fake([Verified::class]);
        $user = $this->pending();
        $link = $this->link($user);
        $identity = $user->only(['role_id', 'company_id', 'is_company_owner', 'status', 'auth_version']);
        $this->get($link, ['Accept' => 'text/html'])->assertOk()->assertViewIs('auth.verification-result')
            ->assertViewHas('state', 'verified')->assertSee('Iniciar sesión')->assertDontSee($user->email)
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertGuest('web');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertSame($identity, $user->fresh()->only(array_keys($identity)));
        $verifiedAt = $user->fresh()->email_verified_at;
        $this->getJson($link)->assertOk()->assertJsonPath('status', 'already_verified');
        $this->assertEquals($verifiedAt, $user->fresh()->email_verified_at);
        Event::assertDispatchedTimes(Verified::class, 1);
    }

    public function test_api_verification_returns_json_and_expired_or_altered_links_never_verify(): void
    {
        $user = $this->pending();
        $valid = $this->link($user);
        $expired = $this->link($user, -1);
        $this->get($expired, ['Accept' => 'text/html'])->assertStatus(410)->assertViewHas('state', 'expired');
        $this->getJson($expired)->assertStatus(410)->assertJsonPath('status', 'expired');
        $this->getJson($valid.'&extra=altered')->assertForbidden()->assertJsonPath('status', 'invalid');
        $this->getJson(str_replace('signature=', 'signature=bad', $valid))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->getJson($valid)->assertOk()->assertJsonPath('status', 'verified');
    }

    public function test_unsigned_wrong_identity_hash_and_changed_email_cannot_verify(): void
    {
        $user = $this->pending();
        $this->getJson('/api/email/verify/'.$user->id.'/'.sha1($user->email))->assertForbidden();
        $this->getJson($this->link($user, 60, ['hash' => sha1('other@example.test')]))->assertForbidden()->assertJsonPath('status', 'invalid');
        $this->getJson($this->link($user, 60, ['id' => 999999]))->assertForbidden();
        $oldLink = $this->link($user);
        $user->update(['email' => 'changed@example.test']);
        $this->getJson($oldLink)->assertForbidden()->assertJsonPath('status', 'invalid');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_expiry_timestamp_cannot_be_extended_and_html_tampering_is_clear(): void
    {
        $user = $this->pending();
        $url = $this->link($user, -1);
        $altered = preg_replace('/expires=\d+/', 'expires='.now()->addDay()->timestamp, $url);
        $this->get($altered, ['Accept' => 'text/html'])->assertForbidden()->assertViewHas('state', 'invalid');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_requires_password_proof_without_enumerating_accounts(): void
    {
        Notification::fake();
        $user = $this->pending();
        $generic = $this->postJson(route('verification.send'), ['email' => 'unknown@example.test', 'password' => self::PASSWORD])
            ->assertStatus(202)->json();
        $this->postJson(route('verification.send'), ['email' => $user->email, 'password' => 'incorrect'])
            ->assertStatus(202)->assertExactJson($generic);
        Notification::assertNothingSent();
        $this->postJson(route('verification.send'), ['email' => strtoupper($user->email), 'password' => self::PASSWORD])
            ->assertStatus(202)->assertExactJson($generic)->assertJsonMissingPath('access_token');
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertGuest('web');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $user->markEmailAsVerified();
        $this->postJson(route('verification.send'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertStatus(202)->assertExactJson($generic);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_resend_failed_delivery_is_reported_only_with_valid_credentials(): void
    {
        $user = $this->pending();
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('private smtp debug'));
        $this->postJson(route('verification.send'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertStatus(503)->assertJsonPath('status', 'delivery_failed')->assertDontSee('private smtp debug');
        $this->postJson(route('verification.send'), ['email' => $user->email, 'password' => 'incorrect'])->assertStatus(202);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_limits_sending_by_user_even_across_ips_and_email_case(): void
    {
        Notification::fake();
        $user = $this->pending();
        for ($i = 1; $i <= 4; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$i])->postJson(route('verification.send'), [
                'email' => $i % 2 ? $user->email : strtoupper($user->email), 'password' => self::PASSWORD,
            ])->assertStatus(202);
        }
        Notification::assertSentToTimes($user, VerifyEmail::class, 3);
        $this->travel(3601)->seconds();
        $this->postJson(route('verification.send'), ['email' => $user->email, 'password' => self::PASSWORD])->assertStatus(202);
        Notification::assertSentToTimes($user, VerifyEmail::class, 4);
    }

    public function test_resend_email_limit_is_independent_of_ip_and_exists_for_unknown_accounts(): void
    {
        Notification::fake();
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$i])->postJson(route('verification.send'), [
                'email' => $i % 2 ? 'unknown@example.test' : 'UNKNOWN@example.test', 'password' => 'wrong',
            ])->assertStatus(202);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])->postJson(route('verification.send'), [
            'email' => 'unknown@example.test', 'password' => 'wrong',
        ])->assertStatus(429)->assertHeader('Retry-After');
        Notification::assertNothingSent();
    }

    public function test_resend_ip_limit_and_malformed_input_are_safe(): void
    {
        Notification::fake();
        $this->postJson(route('verification.send'), ['email' => ['bad']])->assertUnprocessable();
        for ($i = 0; $i < 9; $i++) {
            $this->postJson(route('verification.send'), ['email' => 'unknown'.$i.'@example.test', 'password' => 'wrong'])->assertStatus(202);
        }
        $this->postJson(route('verification.send'), ['email' => 'any@example.test', 'password' => 'wrong'])
            ->assertStatus(429)->assertHeader('Retry-After');
        Notification::assertNothingSent();
    }

    public function test_inactive_and_suspended_accounts_do_not_receive_resends(): void
    {
        Notification::fake();
        $user = $this->pending();
        $user->update(['status' => 'inactive']);
        $data = ['email' => $user->email, 'password' => self::PASSWORD];
        $this->postJson(route('verification.send'), $data)->assertStatus(202);
        $user->update(['status' => 'active']);
        $user->company->update(['status' => 'inactive']);
        $this->postJson(route('verification.send'), $data)->assertStatus(202);
        Notification::assertNothingSent();
    }

    public function test_registration_throttle_is_separate_from_login_and_google(): void
    {
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/register', [])->assertUnprocessable();
        }
        $this->postJson('/api/register', [])->assertStatus(429)->assertHeader('Retry-After');
        $this->postJson('/api/login', [])->assertUnprocessable();
        $this->postJson('/api/auth/google', [])->assertUnprocessable();
        $this->postJson('/login', [])->assertUnprocessable();
        $this->assertDatabaseCount('companies', 0);
    }

    public function test_registration_hourly_limit_cannot_be_bypassed_by_waiting_a_minute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/register', [])->assertUnprocessable();
            $this->travel(61)->seconds();
        }
        $this->postJson('/api/register', [])->assertStatus(429);
    }

    public static function loginEndpoints(): array
    {
        return [['/login'], ['/api/login']];
    }

    #[DataProvider('loginEndpoints')]
    public function test_pending_login_has_retry_url_and_verified_login_keeps_normal_access(string $endpoint): void
    {
        $user = $this->pending();
        $payload = ['email' => $user->email, 'password' => self::PASSWORD];
        $this->postJson($endpoint, $payload)->assertForbidden()->assertJsonPath('code', 'email_verification_required')
            ->assertJsonPath('verification_url', route('verification.notice'))->assertJsonMissingPath('access_token');
        $this->assertGuest('web');
        $this->getJson($this->link($user))->assertOk();
        $this->postJson($endpoint, $payload)->assertOk()->assertJsonStructure(['access_token', 'user']);
        if ($endpoint === '/login') {
            $this->assertAuthenticatedAs($user, 'web');
            $this->get('/dashboard')->assertOk();
        }
    }

    public function test_notice_and_result_links_are_available_without_session(): void
    {
        $this->get(route('verification.notice'))->assertOk()->assertSee('Reenviar correo de verificación')->assertSee('verification-password');
        $this->get('/login')->assertOk()->assertSee(route('verification.notice'));
        $this->get('/register')->assertOk()->assertSee(route('verification.notice'));
    }

    public function test_initial_notification_counts_towards_user_resend_limit(): void
    {
        Notification::fake();
        $this->postJson('/api/register', $this->registration())->assertCreated();
        $user = User::where('email', 'owner@example.test')->firstOrFail();
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('verification.send'), ['email' => $user->email, 'password' => self::PASSWORD])->assertStatus(202);
        }
        Notification::assertSentToTimes($user, VerifyEmail::class, 3);
    }

    public function test_verification_link_is_rate_limited_and_repeated_use_does_not_change_identity(): void
    {
        $user = $this->pending();
        $link = $this->link($user);
        for ($i = 0; $i < 6; $i++) {
            $this->getJson($link)->assertOk();
        }
        $this->getJson($link)->assertStatus(429)->assertHeader('Retry-After');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
