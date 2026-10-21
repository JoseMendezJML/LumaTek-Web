<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Greenhouse;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class PlatformAccessTest extends TestCase
{
    use UsesInMemoryDatabase;

    public static function roles(): array
    {
        $cases = [];
        foreach (['employee', 'company_admin', 'superadmin'] as $role) {
            foreach (['active', 'inactive'] as $status) {
                $cases[] = [$role, $status];
            }
        }

        return $cases;
    }

    #[DataProvider('roles')]
    public function test_platform_requires_active_superadmin_for_every_route(string $role, string $status): void
    {
        $user = $this->user($role, $status);
        $this->actingAs($user, 'web');
        $response = $this->get('/platform');
        if ($status === 'inactive') {
            $response->assertRedirect(route('login'));
        } elseif ($role !== 'superadmin') {
            $response->assertForbidden();
        } else {
            $response->assertOk()->assertSee('Cuenta de plataforma')->assertSee('Inicio de plataforma')
                ->assertDontSee('href="http://localhost/greenhouses"', false);
        }
        $this->actingAs($user, 'web');
        $response = $this->postJson('/platform/logout');
        $response->assertStatus($role === 'superadmin' && $status === 'active' ? 200 : 403);
    }

    public function test_guest_cannot_open_platform_and_bearer_token_does_not_replace_web_login(): void
    {
        $this->get('/platform')->assertRedirect(route('login'));
        $this->getJson('/platform')->assertUnauthorized();
        $this->postJson('/platform/logout')->assertUnauthorized();
        $this->withToken(JWTAuth::fromUser($this->user('superadmin')))->getJson('/platform')->assertUnauthorized();
    }

    public function test_login_session_and_logout_work_without_company(): void
    {
        $user = $this->user('superadmin');
        $this->withCredentials()->postJson('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.role', 'superadmin')->assertJsonPath('user.company_id', null);
        $this->assertAuthenticatedAs($user, 'web');
        $this->get('/platform')->assertOk();
        $this->postJson('/platform/logout')->assertOk();
        $this->assertGuest('web');
        $this->get('/platform')->assertRedirect(route('login'));
    }

    public function test_legacy_superadmin_company_and_plan_do_not_control_platform_login(): void
    {
        $user = $this->user('superadmin', 'active', $this->company());
        $user->update(['plan_restricted' => true]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->get('/platform')->assertOk();
        $this->assertNotNull($user->fresh()->company_id);
    }

    public function test_deactivation_invalidates_an_existing_platform_session(): void
    {
        $user = $this->user('superadmin');
        $this->withCredentials()->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $sessionId = session()->getId();
        $user->update(['status' => 'inactive']);
        $user->update(['status' => 'active']);
        $this->withCookie(config('session.cookie'), $sessionId);
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->get('/platform')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_demotion_is_checked_again_on_direct_platform_access(): void
    {
        $user = $this->user('superadmin', 'active', $this->company());
        $this->withCredentials()->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $user->update(['role_id' => Role::where('name', 'employee')->value('id')]);
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->get('/platform')->assertForbidden();
    }

    public static function businessRoutes(): array
    {
        return [
            ['GET', '/api/greenhouses'], ['GET', '/api/greenhouses/%d'],
            ['GET', '/api/dashboard'], ['GET', '/api/users'], ['GET', '/api/alerts'],
            ['POST', '/api/greenhouses'], ['GET', '/api/greenhouses/%d/zones'],
            ['POST', '/api/greenhouses/%d/zones'], ['GET', '/api/irrigation-events'],
        ];
    }

    #[DataProvider('businessRoutes')]
    public function test_platform_account_has_no_implicit_company_access(string $method, string $uri): void
    {
        $greenhouse = $this->greenhouse($this->company());
        $user = $this->user('superadmin');
        $this->withToken(JWTAuth::fromUser($user));
        Auth::forgetGuards();
        $this->json($method, sprintf($uri, $greenhouse->id), ['name' => 'Forbidden'])->assertForbidden();
        $this->assertDatabaseCount('greenhouses', 1);
        $this->assertDatabaseCount('zones', 0);
    }

    public function test_company_associated_superadmin_cannot_read_another_company(): void
    {
        $ownCompany = $this->company();
        $own = $this->greenhouse($ownCompany);
        $foreign = $this->greenhouse($this->company());
        $this->withToken(JWTAuth::fromUser($this->user('superadmin', 'active', $ownCompany)));
        Auth::forgetGuards();
        $this->getJson('/api/greenhouses')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson('/api/greenhouses/'.$foreign->id)->assertForbidden();
        $this->putJson('/api/greenhouses/'.$foreign->id, ['name' => 'Forbidden'])->assertForbidden();
        $this->assertSame('Greenhouse', $foreign->fresh()->name);
    }

    public function test_null_company_accounts_cannot_open_company_web_pages(): void
    {
        $this->actingAs($this->user('superadmin'), 'web');
        foreach (['/dashboard', '/greenhouses', '/zones', '/users', '/settings', '/irrigation', '/alerts'] as $uri) {
            $this->get($uri)->assertForbidden();
        }
    }

    public function test_public_registration_cannot_choose_platform_role(): void
    {
        Notification::fake();
        $this->postJson('/api/register', [
            'company_name' => 'Registered company', 'name' => 'Customer', 'email' => 'customer@example.test',
            'password' => 'Customer-Password-123!', 'password_confirmation' => 'Customer-Password-123!',
            'role' => 'superadmin', 'role_id' => Role::where('name', 'superadmin')->value('id'),
            'company_id' => null, 'is_company_owner' => false,
        ])->assertCreated();
        $user = User::where('email', 'customer@example.test')->firstOrFail();
        $this->assertSame('company_admin', $user->role->name);
        $this->assertTrue($user->is_company_owner);
        $this->assertNotNull($user->company_id);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_company_accounts_cannot_be_saved_without_company(): void
    {
        foreach (['employee', 'company_admin'] as $role) {
            try {
                $this->user($role, 'active', null, false);
                $this->fail('Company membership was not required.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('company_id', $exception->errors());
            }
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_company_migration_preserves_existing_rows_and_foreign_key(): void
    {
        $migration = require database_path('migrations/2026_10_21_000000_allow_platform_users_without_company.php');
        $migration->down();
        $user = $this->user('employee');
        $before = $user->fresh()->getRawOriginal();
        $migration->up();
        $this->assertSame($before, $user->fresh()->getRawOriginal());
        $foreignKeys = collect(DB::select('PRAGMA foreign_key_list(users)'));
        $this->assertTrue($foreignKeys->contains(fn ($key) => $key->from === 'company_id' && $key->table === 'companies' && $key->on_delete === 'RESTRICT'));
        $this->user('superadmin');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_nonexistent_company_is_still_rejected_by_the_database(): void
    {
        $this->expectException(QueryException::class);
        User::factory()->create(['company_id' => 999999, 'role_id' => Role::where('name', 'employee')->value('id')]);
    }

    public function test_bootstrap_command_creates_only_first_platform_account(): void
    {
        $this->bootstrapCommand()->expectsConfirmation('I verified this email belongs to the authorized platform operator. Create the first superadmin?', 'yes')->assertSuccessful();
        $user = User::where('email', 'operator@example.test')->firstOrFail();
        $this->assertNull($user->company_id);
        $this->assertSame('superadmin', $user->role->name);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertFalse($user->is_company_owner);
        $this->assertTrue(Hash::check('Example-Platform-123!', $user->password));
        $this->assertNotSame('Example-Platform-123!', $user->password);
        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'Example-Platform-123!'])->assertOk();
        $this->get('/platform')->assertOk();
        $this->artisan('platform:create-superadmin')->expectsOutput('A superadmin already exists. This bootstrap command cannot create another.')->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_bootstrap_command_rejects_noninteractive_and_cancelled_creation(): void
    {
        $this->artisan('platform:create-superadmin', ['--no-interaction' => true])->assertFailed();
        $this->bootstrapCommand()->expectsConfirmation('I verified this email belongs to the authorized platform operator. Create the first superadmin?', 'no')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_bootstrap_does_not_promote_existing_users(): void
    {
        $user = $this->user('employee');
        $user->update(['email' => 'operator@example.test']);
        $before = $user->fresh()->getRawOriginal();
        $this->bootstrapCommand()->assertFailed();
        $this->assertSame($before, $user->fresh()->getRawOriginal());
        $this->assertDatabaseCount('users', 1);
    }

    public static function invalidPasswords(): array
    {
        return [['weak', 'weak'], ['Example-Platform-123!', 'Different-Platform-456!']];
    }

    #[DataProvider('invalidPasswords')]
    public function test_bootstrap_rejects_weak_or_unconfirmed_passwords(string $password, string $confirmation): void
    {
        $this->artisan('platform:create-superadmin')
            ->expectsQuestion('Name', 'Operator')
            ->expectsQuestion('Email', 'operator@example.test')
            ->expectsQuestion('Password (16+ characters)', $password)
            ->expectsQuestion('Confirm password', $confirmation)
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_inactive_superadmin_also_blocks_bootstrap(): void
    {
        $this->user('superadmin', 'inactive');
        $this->artisan('platform:create-superadmin')->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_companyless_account_cannot_become_an_owner_or_employee(): void
    {
        $user = $this->user('superadmin');
        try {
            $user->update(['is_company_owner' => true]);
            $this->fail('Platform account became a company owner.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('company_id', $exception->errors());
        }
        $user->refresh();
        try {
            $user->update(['role_id' => Role::where('name', 'employee')->value('id')]);
            $this->fail('Employee has no company.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('company_id', $exception->errors());
        }
        $this->assertSame('superadmin', $user->fresh()->role->name);
        $this->assertFalse($user->fresh()->is_company_owner);
    }

    public function test_all_platform_routes_have_authentication_active_user_and_role_middleware(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'platform'));
        $this->assertCount(2, $routes);
        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            foreach (['web', 'auth', 'active.user', 'platform.admin'] as $required) {
                $this->assertContains($required, $middleware);
            }
            $this->assertNotContains('plan.access', $middleware);
        }
        $this->get('/login')->assertOk()->assertSee("data.user.role === 'superadmin' ? '/platform' : '/dashboard'", false);
    }

    private function bootstrapCommand()
    {
        return $this->artisan('platform:create-superadmin')
            ->expectsQuestion('Name', 'Platform operator')
            ->expectsQuestion('Email', 'operator@example.test')
            ->expectsQuestion('Password (16+ characters)', 'Example-Platform-123!')
            ->expectsQuestion('Confirm password', 'Example-Platform-123!');
    }

    private function company(): Company
    {
        return Company::create(['name' => 'Company', 'email' => fake()->unique()->safeEmail(), 'status' => 'active']);
    }

    private function user(string $role, string $status = 'active', ?Company $company = null, bool $assignCompany = true): User
    {
        return User::factory()->create([
            'company_id' => $company?->id ?? ($role !== 'superadmin' && $assignCompany ? $this->company()->id : null),
            'role_id' => Role::where('name', $role)->value('id'),
            'status' => $status, 'is_company_owner' => $role === 'company_admin' && $assignCompany,
        ]);
    }

    private function greenhouse(Company $company): Greenhouse
    {
        return Greenhouse::create(['company_id' => $company->id, 'name' => 'Greenhouse', 'crop_type' => 'Tomato', 'area' => 120, 'location' => 'Test', 'planting_date' => '2026-01-01', 'nominal_flow' => 15, 'status' => 'active']);
    }
}
