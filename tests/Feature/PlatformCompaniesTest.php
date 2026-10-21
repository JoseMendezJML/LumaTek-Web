<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Greenhouse;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class PlatformCompaniesTest extends TestCase
{
    use UsesInMemoryDatabase;

    public static function accessCases(): array
    {
        $cases = [];
        foreach (['guest', 'employee', 'company_admin', 'superadmin'] as $role) {
            foreach (['active', 'inactive'] as $status) {
                foreach (['list', 'show', 'suspend', 'reactivate'] as $operation) {
                    $cases[] = [$role, $status, $operation];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('accessCases')]
    public function test_only_active_superadmin_can_manage_companies(string $role, string $status, string $operation): void
    {
        $company = $this->company($operation === 'reactivate' ? 'inactive' : 'active');
        if ($role !== 'guest') {
            $user = $this->user($role, $role === 'superadmin' ? null : $this->company(), $status);
            $this->actingAs($user, 'web');
        }
        $uri = match ($operation) {
            'list' => '/platform/companies?q=Company&status=active',
            'show' => '/platform/companies/'.$company->id,
            default => '/platform/companies/'.$company->id.'/status',
        };
        $response = in_array($operation, ['list', 'show']) ? $this->getJson($uri) : $this->patchJson($uri, ['status' => $operation === 'suspend' ? 'inactive' : 'active', 'confirmed' => true]);
        $allowed = $role === 'superadmin' && $status === 'active';
        $response->assertStatus($allowed ? (in_array($operation, ['list', 'show']) ? 200 : 302) : ($role === 'guest' ? 401 : 403));
        $this->assertSame($allowed && in_array($operation, ['suspend', 'reactivate']) ? ($operation === 'suspend' ? 'inactive' : 'active') : ($operation === 'reactivate' ? 'inactive' : 'active'), $company->fresh()->status);
    }

    public function test_listing_search_filter_detail_and_pagination(): void
    {
        $company = $this->company();
        $company->update(['name' => 'Alpha greenhouse company', 'legal_name' => 'Alpha Legal', 'phone' => '5551234567']);
        $owner = $this->user('company_admin', $company);
        $owner->update(['name' => 'Searchable Owner']);
        $pro = Plan::where('slug', 'pro')->firstOrFail();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $pro->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth(), 'auto_renew' => false]);
        for ($i = 0; $i < 16; $i++) {
            $this->company();
        }
        $this->actingAs($this->user('superadmin'), 'web');
        $this->get('/platform/companies')->assertOk()->assertViewHas('companies', fn ($companies) => $companies->total() === 17 && $companies->count() === 15);
        $this->get('/platform/companies?page=2')->assertOk()->assertViewHas('companies', fn ($companies) => $companies->count() === 2);
        foreach (['Alpha Legal', $company->email, 'Searchable Owner', $owner->email] as $search) {
            $this->get('/platform/companies?q='.urlencode($search).'&status=active&plan_id='.$pro->id)->assertOk()
                ->assertViewHas('companies', fn ($companies) => $companies->total() === 1 && $companies->first()->id === $company->id)
                ->assertSee($owner->name)->assertSee($pro->name);
        }
        $this->get('/platform/companies/'.$company->id)->assertOk()->assertSee('Alpha Legal')->assertSee('5551234567')
            ->assertSee($owner->email)->assertSee('name="confirmed"', false)->assertSee('confirm(', false);
        $subscription->update(['ends_at' => now()->subDay()]);
        $free = Plan::where('slug', 'free')->firstOrFail();
        $this->get('/platform/companies?q=Alpha&plan_id='.$free->id)->assertOk()->assertViewHas('companies', fn ($companies) => $companies->total() === 1);
        $this->get('/platform/companies?q=Alpha&plan_id='.$pro->id)->assertOk()->assertViewHas('companies', fn ($companies) => $companies->isEmpty());
        $this->get('/platform/companies/'.$company->id)->assertSee($free->name);
        $this->get('/platform/companies?status=inactive')->assertOk()->assertViewHas('companies', fn ($companies) => $companies->isEmpty());
    }

    public function test_filters_and_confirmation_are_validated_and_missing_company_is_404(): void
    {
        $company = $this->company();
        $this->actingAs($this->user('superadmin'), 'web');
        foreach (['status=deleted', 'plan_id=99999', 'page=0', 'q[]=%25'] as $query) {
            $this->getJson('/platform/companies?'.$query)->assertUnprocessable();
        }
        foreach ([['status' => 'inactive'], ['status' => 'inactive', 'confirmed' => false], ['status' => 'deleted', 'confirmed' => true]] as $payload) {
            $this->patchJson('/platform/companies/'.$company->id.'/status', $payload)->assertUnprocessable();
            $this->assertSame('active', $company->fresh()->status);
        }
        $this->get('/platform/companies/99999')->assertNotFound();
    }

    public static function companyRoles(): array
    {
        return [['employee'], ['company_admin']];
    }

    #[DataProvider('companyRoles')]
    public function test_suspension_revokes_jwt_and_reactivation_requires_fresh_login(string $role): void
    {
        $company = $this->company();
        $user = $this->user($role, $company);
        $inactive = $this->user('employee', $company, 'inactive');
        $otherCompany = $this->company();
        $other = $this->user('company_admin', $otherCompany);
        $otherBefore = $other->fresh()->getRawOriginal();
        $oldVersion = $user->authenticationVersion();
        $oldToken = JWTAuth::fromUser($user);
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => Plan::where('slug', 'pro')->value('id'), 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth(), 'auto_renew' => false]);
        $greenhouse = Greenhouse::create(['company_id' => $company->id, 'name' => 'Original', 'crop_type' => 'Tomato', 'area' => 120, 'location' => 'Test', 'planting_date' => '2026-01-01', 'nominal_flow' => 15, 'status' => 'active']);
        $subscriptionBefore = $subscription->fresh()->getRawOriginal();
        $greenhouseBefore = $greenhouse->fresh()->getRawOriginal();
        $admin = $this->user('superadmin');
        $this->change($admin, $company, 'inactive');
        $this->assertNotSame($oldVersion, $user->fresh()->authenticationVersion());
        $version = $user->fresh()->authenticationVersion();
        $this->api($oldToken, '/api/dashboard')->assertUnauthorized();
        // A freshly forged token still cannot bypass the suspended company check.
        $this->api(JWTAuth::fromUser($user->fresh()), '/api/dashboard')->assertForbidden();
        foreach (['/login', '/api/login'] as $uri) {
            $this->resetGuards();
            $this->postJson($uri, ['email' => $user->email, 'password' => 'password'])->assertForbidden();
        }
        $this->api(JWTAuth::fromUser($other), '/api/dashboard')->assertOk();
        $this->change($admin, $company, 'active');
        $this->api($oldToken, '/api/dashboard')->assertUnauthorized();
        $this->assertNotSame($version, $user->fresh()->authenticationVersion());
        $this->assertSame('inactive', $inactive->fresh()->status);
        $this->assertSame('active', $user->fresh()->status);
        $this->assertSame($otherBefore, $other->fresh()->getRawOriginal());
        $this->assertSame($subscriptionBefore, $subscription->fresh()->getRawOriginal());
        $this->assertSame($greenhouseBefore, $greenhouse->fresh()->getRawOriginal());
        $this->resetGuards();
        $fresh = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->json('access_token');
        $this->api($fresh, '/api/dashboard')->assertOk();
        $this->postJson('/api/login', ['email' => $inactive->email, 'password' => 'password'])->assertForbidden();
    }

    #[DataProvider('companyRoles')]
    public function test_web_credentials_remain_revoked_after_company_reactivation(string $role): void
    {
        $company = $this->company();
        $user = $this->user($role, $company);
        $this->withCredentials()->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $credentials = session(User::AUTH_VERSION_SESSION_KEY);
        $admin = $this->user('superadmin');
        $this->change($admin, $company, 'inactive');
        $this->actingAs($user->fresh(), 'web')->withSession([User::AUTH_VERSION_SESSION_KEY => $credentials]);
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->change($admin, $company, 'active');
        $this->actingAs($user->fresh(), 'web')->withSession([User::AUTH_VERSION_SESSION_KEY => $credentials]);
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->get('/dashboard')->assertOk();
    }

    public function test_platform_access_survives_suspending_its_legacy_company_and_changes_are_idempotent(): void
    {
        $company = $this->company();
        $admin = $this->user('superadmin', $company);
        $before = $admin->fresh()->getRawOriginal();
        $this->change($admin, $company, 'inactive');
        $this->get('/platform')->assertOk();
        $this->get('/platform/companies')->assertOk();
        $this->get('/dashboard')->assertForbidden();
        $this->assertSame($before, $admin->fresh()->getRawOriginal());
        $user = $this->user('employee', $company);
        $version = $user->authenticationVersion();
        $this->change($admin, $company, 'inactive');
        $this->assertSame($version, $user->fresh()->authenticationVersion());
        $this->change($admin, $company, 'active');
        $this->assertNotSame($version, $user->fresh()->authenticationVersion());
        $version = $user->fresh()->authenticationVersion();
        $this->change($admin, $company, 'active');
        $this->assertSame($version, $user->fresh()->authenticationVersion());
    }

    public function test_plan_access_and_company_isolation_still_apply_after_reactivation(): void
    {
        $company = $this->company();
        $user = $this->user('employee', $company);
        $admin = $this->user('superadmin');
        $this->change($admin, $company, 'inactive');
        $this->change($admin, $company, 'active');
        Plan::where('slug', 'free')->update(['max_users' => 0]);
        $this->api(JWTAuth::fromUser($user->fresh()), '/api/dashboard')->assertForbidden();
        Plan::where('slug', 'free')->update(['max_users' => 3]);
        $foreign = Greenhouse::create(['company_id' => $this->company()->id, 'name' => 'Foreign', 'crop_type' => 'Tomato', 'area' => 120, 'location' => 'Test', 'planting_date' => '2026-01-01', 'nominal_flow' => 15, 'status' => 'active']);
        $this->api(JWTAuth::fromUser($user->fresh()), '/api/greenhouses/'.$foreign->id)->assertForbidden();
    }

    public function test_every_company_route_is_guarded_and_every_business_route_checks_plan_access(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'platform/companies')) {
                foreach (['auth', 'active.user', 'platform.admin'] as $middleware) {
                    $this->assertContains($middleware, $route->gatherMiddleware());
                }
            }
            if (in_array('plan.access', $route->gatherMiddleware())) {
                $this->assertContains('active.user', $route->gatherMiddleware());
            }
        }
    }

    public static function suspendedBusinessRequests(): array
    {
        $cases = [];
        foreach (['employee', 'company_admin'] as $role) {
            foreach ([
                ['GET', '/api/dashboard'], ['GET', '/api/greenhouses'], ['GET', '/api/alerts'],
                ['GET', '/api/irrigation-events'], ['GET', '/api/users'], ['GET', '/api/company/settings'],
                ['POST', '/api/greenhouses'], ['POST', '/api/greenhouses/%d/zones'],
            ] as $request) {
                $cases[] = [$role, ...$request];
            }
        }

        return $cases;
    }

    #[DataProvider('suspendedBusinessRequests')]
    public function test_current_jwt_cannot_read_or_write_any_suspended_company_module(string $role, string $method, string $uri): void
    {
        $company = $this->company('inactive');
        $user = $this->user($role, $company);
        $greenhouse = Greenhouse::create(['company_id' => $company->id, 'name' => 'Unchanged', 'crop_type' => 'Tomato', 'area' => 120, 'location' => 'Test', 'planting_date' => '2026-01-01', 'nominal_flow' => 15, 'status' => 'active']);
        $token = JWTAuth::fromUser($user);
        $this->resetGuards();
        $this->json($method, sprintf($uri, $greenhouse->id), ['name' => 'Forbidden'], ['Authorization' => 'Bearer '.$token])
            ->assertForbidden()->assertJsonPath('message', 'La empresa se encuentra suspendida.');
        $this->assertDatabaseCount('greenhouses', 1);
        $this->assertDatabaseCount('zones', 0);
        $this->assertSame('Unchanged', $greenhouse->fresh()->name);
    }

    #[DataProvider('companyRoles')]
    public function test_current_web_session_cannot_bypass_company_suspension(string $role): void
    {
        $user = $this->user($role, $this->company('inactive'));
        foreach (['/dashboard', '/greenhouses', '/zones', '/users', '/settings', '/irrigation', '/alerts', '/history', '/reports'] as $uri) {
            $this->actingAs($user, 'web')->get($uri)->assertForbidden();
        }
    }

    public function test_expired_latest_subscription_uses_free_plan_even_if_an_older_pro_subscription_is_active(): void
    {
        $company = $this->company();
        $pro = Plan::where('slug', 'pro')->firstOrFail();
        $free = Plan::where('slug', 'free')->firstOrFail();
        foreach ([now()->addMonth(), now()->subDay()] as $end) {
            Subscription::create(['company_id' => $company->id, 'plan_id' => $pro->id, 'status' => 'active', 'starts_at' => now()->subMonth(), 'ends_at' => $end]);
        }
        $this->actingAs($this->user('superadmin'), 'web');
        $this->get('/platform/companies?plan_id='.$free->id)->assertOk()->assertViewHas('companies', fn ($companies) => $companies->total() === 1);
        $this->get('/platform/companies?plan_id='.$pro->id)->assertOk()->assertViewHas('companies', fn ($companies) => $companies->isEmpty());
    }

    #[DataProvider('companyRoles')]
    public function test_reactivating_previously_inactive_company_revokes_all_old_credentials(string $role): void
    {
        $company = $this->company();
        $user = $this->user($role, $company);
        $inactive = $this->user('employee', $company, 'inactive');
        $other = $this->user('company_admin', $this->company());
        $otherBefore = $other->fresh()->getRawOriginal();
        $admin = $this->user('superadmin', $company);
        $adminBefore = $admin->fresh()->getRawOriginal();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => Plan::where('slug', 'pro')->value('id'), 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth(), 'auto_renew' => false]);
        $subscriptionBefore = $subscription->fresh()->getRawOriginal();

        $this->withCredentials()->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $credentials = session(User::AUTH_VERSION_SESSION_KEY);
        $oldToken = JWTAuth::fromUser($user->fresh());
        $oldRememberToken = $user->fresh()->remember_token;
        $oldInactiveVersion = $inactive->fresh()->authenticationVersion();
        // Simulate an already-inactive company whose suspension predates the
        // platform revocation mechanism. No suspension endpoint is called.
        $company->update(['status' => 'inactive']);
        $tokenWhileInactive = JWTAuth::fromUser($user->fresh());
        $this->assertSame($credentials['version'], $user->fresh()->authenticationVersion());

        $this->change($admin, $company, 'active');
        $this->assertNotSame($credentials['version'], $user->fresh()->authenticationVersion());
        $this->assertNotSame($oldRememberToken, $user->fresh()->remember_token);
        $this->assertNotSame($oldInactiveVersion, $inactive->fresh()->authenticationVersion());
        $this->assertSame('active', $user->fresh()->status);
        $this->assertSame('inactive', $inactive->fresh()->status);
        $this->assertSame($otherBefore, $other->fresh()->getRawOriginal());
        $this->assertSame($adminBefore, $admin->fresh()->getRawOriginal());
        $this->assertSame($subscriptionBefore, $subscription->fresh()->getRawOriginal());
        $this->get('/platform')->assertOk();
        foreach ([$oldToken, $tokenWhileInactive] as $token) {
            $this->api($token, '/api/dashboard')->assertUnauthorized();
        }
        $this->actingAs($user->fresh(), 'web')->withSession([User::AUTH_VERSION_SESSION_KEY => $credentials]);
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->resetGuards();
        $fresh = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->json('access_token');
        $this->api($fresh, '/api/dashboard')->assertOk();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->get('/dashboard')->assertOk();
        $this->postJson('/api/login', ['email' => $inactive->email, 'password' => 'password'])->assertForbidden();
    }

    private function change(User $admin, Company $company, string $status): void
    {
        $this->resetGuards();
        $this->actingAs($admin->fresh(), 'web')->withSession([User::AUTH_VERSION_SESSION_KEY => ['user_id' => $admin->id, 'version' => $admin->fresh()->authenticationVersion()]])
            ->patch('/platform/companies/'.$company->id.'/status', ['status' => $status, 'confirmed' => true])->assertRedirect('/platform/companies/'.$company->id);
    }

    private function api(string $token, string $uri)
    {
        $this->resetGuards();

        return $this->withToken($token)->getJson($uri);
    }

    private function resetGuards(): void
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->app['tymon.jwt']->unsetToken();
        $this->app['tymon.jwt.auth']->unsetToken();
    }

    private function company(string $status = 'active'): Company
    {
        return Company::create(['name' => 'Company '.fake()->unique()->numerify('####'), 'email' => fake()->unique()->safeEmail(), 'status' => $status]);
    }

    private function user(string $role, ?Company $company = null, string $status = 'active'): User
    {
        return User::factory()->create(['company_id' => $company?->id, 'role_id' => Role::where('name', $role)->value('id'), 'status' => $status, 'is_company_owner' => $role === 'company_admin']);
    }
}
