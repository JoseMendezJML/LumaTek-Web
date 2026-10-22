<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\GoogleAccountService;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class GoogleAuthTest extends TestCase
{
    use UsesInMemoryDatabase {
        setUp as setUpInMemoryDatabase;
    }

    private const CLIENT_ID = 'web-client.apps.googleusercontent.com';

    public static function changedEmails(): array
    {
        return [
            'web unverified' => [false, false],
            'mobile unverified' => [true, false],
            'web already verified' => [false, true],
            'mobile already verified' => [true, true],
        ];
    }

    #[DataProvider('changedEmails')]
    public function test_google_does_not_authenticate_or_verify_a_changed_local_email(bool $mobile, bool $verified): void
    {
        $user = $this->createUser('employee', [
            'google_id' => 'google-original', 'email' => 'actual@example.com',
            'email_verified_at' => $verified ? now() : null,
        ]);
        $before = $user->fresh()->getAttributes();
        $users = User::count();
        $companies = Company::count();
        // Another account may legitimately own the old email. It must not be linked either.
        $other = $this->createUser('employee', ['email' => 'anterior@example.com', 'email_verified_at' => null]);

        if ($mobile) {
            $this->fakeTokenInfo('google-original', 'anterior@example.com');
            $this->postJson('/api/auth/google', ['id_token' => 'token', 'company_name' => 'No crear'])
                ->assertConflict()->assertExactJson(['message' => GoogleAccountService::EMAIL_MISMATCH]);
        } else {
            $this->fakeGoogleUser('google-original', 'anterior@example.com');
            $this->get('/auth/google/callback')->assertRedirect(route('login'))
                ->assertSessionHas('google_error', GoogleAccountService::EMAIL_MISMATCH);
        }

        $this->assertGuest('web');
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertNull($other->fresh()->google_id);
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
        $this->assertSame($users + 1, User::count());
        $this->assertSame($companies + 1, Company::count());
    }

    public function test_link_rechecks_a_concurrently_changed_email_before_verifying(): void
    {
        $user = $this->createUser('employee', ['email_verified_at' => null]);
        $oldEmail = $user->email;
        User::whereKey($user->id)->update(['email' => 'cambio@example.com']);

        try {
            app(GoogleAccountService::class)->link($user, 'google-stale', $oldEmail);
            $this->fail('A stale identity must not verify a changed email.');
        } catch (ValidationException $error) {
            $this->assertSame([GoogleAccountService::EMAIL_MISMATCH], $error->errors()['email']);
        }
        $this->assertNull($user->fresh()->google_id);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_link_rechecks_a_concurrently_linked_google_identity(): void
    {
        $user = $this->createUser('employee', ['email_verified_at' => null]);
        User::whereKey($user->id)->update(['google_id' => 'another-google']);
        try {
            app(GoogleAccountService::class)->link($user, 'google-stale', $user->email);
            $this->fail('A stale request must not replace a linked Google identity.');
        } catch (ValidationException $error) {
            $this->assertSame([GoogleAccountService::LINKED_TO_OTHER_GOOGLE_ACCOUNT], $error->errors()['email']);
        }
        $this->assertSame('another-google', $user->fresh()->google_id);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_existing_mobile_user_is_linked_by_verified_email_without_changing_permissions(): void
    {
        $user = $this->createUser('employee', ['email_verified_at' => null]);
        $identity = $user->only(['company_id', 'role_id', 'is_company_owner', 'status']);
        $count = User::count();
        $this->fakeTokenInfo('google-mobile-existing', strtoupper($user->email));
        $this->postJson('/api/auth/google', ['id_token' => 'token'])
            ->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonPath('user.role', 'employee');
        $this->assertSame('google-mobile-existing', $user->fresh()->google_id);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertSame($identity, $user->fresh()->only(array_keys($identity)));
        $this->assertSame($count, User::count());
    }

    public function test_google_login_still_works_after_password_reset_with_current_credentials(): void
    {
        $user = $this->createUser('employee', ['google_id' => 'google-reset']);
        $identity = $user->only(['company_id', 'role_id', 'is_company_owner']);
        $this->postJson('/api/password/reset', [
            'email' => $user->email, 'token' => Password::createToken($user),
            'password' => 'Nueva-Clave-123!', 'password_confirmation' => 'Nueva-Clave-123!',
        ])->assertOk();
        $this->fakeTokenInfo('google-reset', $user->email);
        $token = $this->postJson('/api/auth/google', ['id_token' => 'token'])
            ->assertOk()->assertJsonPath('user.id', $user->id)->json('access_token');
        $this->assertSame($user->fresh()->authenticationVersion(), JWTAuth::setToken($token)->getPayload()->get('auth_version'));
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/greenhouses')->assertOk();
        $this->defaultHeaders = [];
        Auth::forgetGuards();
        $this->fakeGoogleUser('google-reset', $user->email);
        $this->get('/auth/google/callback')->assertOk();
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame($user->fresh()->authenticationVersion(), session(User::AUTH_VERSION_SESSION_KEY.'.version'));
        $this->assertSame($identity, $user->fresh()->only(array_keys($identity)));
    }

    protected function setUp(): void
    {
        $this->setUpInMemoryDatabase();

        config([
            'app.url' => 'http://localhost',
            'services.google.client_id' => self::CLIENT_ID,
            'services.google.mobile_client_ids' => [],
        ]);
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Web
    |--------------------------------------------------------------------------
    */

    public function test_new_web_user_completes_registration_with_free_subscription(): void
    {
        $this->fakeGoogleUser('google-new', 'Nueva@Example.com', 'Ana Nueva');

        $this->get('/auth/google/callback')
            ->assertRedirect(route('google.complete'));

        $this->assertGuest('web');

        $this->post('/auth/google/complete', ['company_name' => 'Invernaderos Ana'])
            ->assertOk()
            ->assertViewHas('redirectTo', '/dashboard');

        $user = User::where('email', 'nueva@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame('google-new', $user->google_id);
        $this->assertTrue($user->is_company_owner);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame('Invernaderos Ana', $user->company->name);
        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $user->company_id,
            'status' => 'active',
        ]);
        $this->assertSame('free', $user->company->activeSubscription()->first()->plan->slug);
    }

    public function test_existing_web_user_is_linked_by_email_and_verified(): void
    {
        $user = $this->createUser('employee', ['email_verified_at' => null]);

        $this->fakeGoogleUser('google-existing', strtoupper($user->email));

        $this->get('/auth/google/callback')
            ->assertOk()
            ->assertViewHas('redirectTo', '/dashboard');

        $user->refresh();

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame('google-existing', $user->google_id);
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_web_login_rejects_unverified_google_email(): void
    {
        $user = $this->createUser('employee');

        $this->fakeGoogleUser('google-unverified', $user->email, verified: false);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('google_error', 'Tu correo de Google no está verificado.');

        $this->assertGuest('web');
        $this->assertNull($user->refresh()->google_id);
    }

    public function test_web_login_rejects_email_linked_to_another_google_account(): void
    {
        $user = $this->createUser('employee', ['google_id' => 'google-original']);

        $this->fakeGoogleUser('google-other', $user->email);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('google_error', 'Este correo ya está vinculado a otra cuenta de Google.');

        $this->assertGuest('web');
        $this->assertSame('google-original', $user->refresh()->google_id);
    }

    public function test_web_login_rejects_inactive_account(): void
    {
        $user = $this->createUser('employee', ['status' => 'inactive']);

        $this->fakeGoogleUser('google-inactive', $user->email);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('google_error', 'La cuenta se encuentra inactiva.');

        $this->assertGuest('web');
    }

    public function test_web_login_rejects_suspended_company(): void
    {
        $user = $this->createUser('company_admin');
        $user->company->update(['status' => 'inactive']);

        $this->fakeGoogleUser('google-suspended', $user->email);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('google_error', 'La empresa se encuentra suspendida.');

        $this->assertGuest('web');
        $this->assertNull($user->refresh()->google_id);
    }

    public function test_web_login_rejects_user_restricted_by_plan(): void
    {
        $restricted = $this->createRestrictedAdmin();

        $this->fakeGoogleUser('google-restricted', $restricted->email);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas(
                'google_error',
                'Tu cuenta está restringida por el plan actual de la empresa. '
                    .'Contacta al propietario o renueva el plan Pro.'
            );

        $this->assertGuest('web');
    }

    public function test_web_superadmin_is_sent_to_platform(): void
    {
        $superadmin = $this->createSuperadmin();

        $this->fakeGoogleUser('google-superadmin', $superadmin->email);

        $this->get('/auth/google/callback')
            ->assertOk()
            ->assertViewHas('redirectTo', '/platform')
            ->assertSee('"\/platform"', false);

        $this->assertAuthenticatedAs($superadmin, 'web');
        $this->get('/platform')->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Móvil
    |--------------------------------------------------------------------------
    */

    public function test_mobile_rejects_token_for_another_client(): void
    {
        $this->fakeTokenInfo('google-x', 'x@example.com', audience: 'otro-cliente');

        $this->postJson('/api/auth/google', ['id_token' => 'token'])
            ->assertUnauthorized();
    }

    public function test_mobile_new_user_needs_company_and_gets_free_subscription(): void
    {
        $this->fakeTokenInfo('google-mobile', 'movil@example.com');

        $this->postJson('/api/auth/google', ['id_token' => 'token'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'company_required');

        $this->assertDatabaseMissing('users', ['email' => 'movil@example.com']);

        $this->postJson('/api/auth/google', [
            'id_token' => 'token',
            'company_name' => 'Empresa Móvil',
        ])
            ->assertCreated()
            ->assertJsonPath('user.role', 'company_admin')
            ->assertJsonPath('redirect_to', '/dashboard');

        $company = User::where('email', 'movil@example.com')->firstOrFail()->company;

        $this->assertSame('free', $company->activeSubscription()->first()->plan->slug);
    }

    public function test_mobile_rejects_suspended_company(): void
    {
        $user = $this->createUser('employee');
        $user->company->update(['status' => 'inactive']);

        $this->fakeTokenInfo('google-mobile-suspended', $user->email);

        $this->postJson('/api/auth/google', ['id_token' => 'token'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'La empresa se encuentra suspendida.']);
    }

    public function test_mobile_rejects_user_restricted_by_plan(): void
    {
        $restricted = $this->createRestrictedAdmin();

        $this->fakeTokenInfo('google-mobile-restricted', $restricted->email);

        $this->postJson('/api/auth/google', ['id_token' => 'token'])
            ->assertForbidden();
    }

    public function test_mobile_superadmin_receives_platform_redirect(): void
    {
        $superadmin = $this->createSuperadmin();

        $this->fakeTokenInfo('google-mobile-superadmin', $superadmin->email);

        $this->postJson('/api/auth/google', ['id_token' => 'token'])
            ->assertOk()
            ->assertJsonPath('user.role', 'superadmin')
            ->assertJsonPath('redirect_to', '/platform');
    }

    /*
    |--------------------------------------------------------------------------
    | Proxies confiables
    |--------------------------------------------------------------------------
    */

    public function test_forwarded_ip_is_ignored_without_trusted_proxies(): void
    {
        $this->registerIpRoute();

        $this->get('/_test/ip', ['X-Forwarded-For' => '203.0.113.9'])
            ->assertSeeText('127.0.0.1');
    }

    public function test_forwarded_ip_is_used_when_proxies_are_trusted(): void
    {
        config(['app.trusted_proxies' => '*']);
        (new AppServiceProvider($this->app))->boot();

        $this->registerIpRoute();

        $this->get('/_test/ip', ['X-Forwarded-For' => '203.0.113.9'])
            ->assertSeeText('203.0.113.9');
    }

    /*
    |--------------------------------------------------------------------------
    | Utilidades
    |--------------------------------------------------------------------------
    */

    private function fakeGoogleUser(
        string $id,
        string $email,
        string $name = 'Usuario Google',
        bool $verified = true
    ): void {

        $googleUser = (new SocialiteUser)
            ->setRaw(['email_verified' => $verified])
            ->map(['id' => $id, 'name' => $name, 'email' => $email]);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function fakeTokenInfo(
        string $sub,
        string $email,
        string $audience = self::CLIENT_ID
    ): void {

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'aud' => $audience,
                'iss' => 'https://accounts.google.com',
                'sub' => $sub,
                'email' => $email,
                'email_verified' => 'true',
                'name' => 'Usuario Móvil',
            ]),
        ]);
    }

    private function registerIpRoute(): void
    {
        Route::get('/_test/ip', fn (Request $request) => $request->ip());
    }

    private function createUser(
        string $role,
        array $attributes = [],
        ?Company $company = null
    ): User {

        $company ??= Company::create([
            'name' => 'Empresa '.fake()->uuid(),
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
        ]);

        return User::factory()->create(array_merge([
            'company_id' => $company->id,
            'role_id' => Role::where('name', $role)->firstOrFail()->id,
            'is_company_owner' => $role === 'company_admin',
            'status' => 'active',
            'plan_restricted' => false,
        ], $attributes));
    }

    private function createRestrictedAdmin(): User
    {
        // El plan Gratis no permite administradores adicionales.
        $owner = $this->createUser('company_admin');

        $restricted = $this->createUser(
            'company_admin',
            ['is_company_owner' => false],
            $owner->company
        );

        $this->assertTrue($owner->company->isUserRestrictedByPlan($restricted));

        return $restricted;
    }

    private function createSuperadmin(): User
    {
        return User::factory()->create([
            'company_id' => null,
            'role_id' => Role::where('name', 'superadmin')->firstOrFail()->id,
            'is_company_owner' => false,
            'status' => 'active',
            'plan_restricted' => false,
        ]);
    }
}
