<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\PlanChange;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionContractService;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class PlatformPlansTest extends TestCase
{
    use UsesInMemoryDatabase;

    private function user(string $role = 'superadmin', string $status = 'active'): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role)->value('id'), 'status' => $status,
            'company_id' => $role === 'superadmin' ? null : $this->company()->id,
        ]);
    }

    private function company(): Company
    {
        return Company::create(['name' => 'Company', 'email' => fake()->unique()->safeEmail(), 'status' => 'active']);
    }

    private function pro(): Plan
    {
        return Plan::where('slug', 'pro')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['price_monthly' => '199.50', 'price_yearly' => '1999.00', 'is_active' => '1', 'confirmed' => '1'], $overrides);
    }

    public static function roles(): array
    {
        $cases = [];
        foreach (['superadmin', 'company_admin', 'employee'] as $role) {
            foreach (['active', 'inactive'] as $status) {
                $cases[] = [$role, $status];
            }
        }

        return $cases;
    }

    #[DataProvider('roles')]
    public function test_only_active_superadmin_can_read_or_update_plans(string $role, string $status): void
    {
        $user = $this->user($role, $status);
        $allowed = $role === 'superadmin' && $status === 'active';
        $this->actingAs($user, 'web')->getJson('/platform/plans')->assertStatus($allowed ? 200 : 403);
        $this->actingAs($user, 'web')->patchJson('/platform/plans/'.$this->pro()->id, $this->payload())->assertStatus($allowed ? 302 : 403);
        $this->assertDatabaseCount('plan_changes', $allowed ? 1 : 0);
        $this->assertSame($allowed ? '199.50' : '0.00', $this->pro()->price_monthly);
    }

    public function test_guests_and_jwt_only_cannot_use_platform_plan_routes(): void
    {
        $this->get('/platform/plans')->assertRedirect(route('login'));
        $this->patchJson('/platform/plans/'.$this->pro()->id, $this->payload())->assertUnauthorized();
        $this->withToken(JWTAuth::fromUser($this->user()))->getJson('/platform/plans')->assertUnauthorized();
        $this->patchJson('/platform/plans/'.$this->pro()->id, $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('plan_changes', 0);
    }

    public function test_listing_includes_terms_navigation_form_and_unknown_annual_price(): void
    {
        $this->actingAs($this->user(), 'web')->get('/platform/plans')->assertOk()
            ->assertSee('Gratis')->assertSee('Pro')->assertSee('MXN')->assertSee('Sin configurar')
            ->assertSee('Precio mensual')->assertSee('Precio anual')->assertSee('Administradores adicionales')
            ->assertSee('Guardar cambios de Pro')->assertSee('Confirmo los precios')
            ->assertSee(route('platform.plans.update', $this->pro()), false)
            ->assertDontSee('action="'.route('platform.plans.update', Plan::where('slug', 'free')->first()).'"', false);
        $this->get('/platform')->assertSee(route('platform.plans.index'), false)->assertSee('Consultar y administrar planes');
    }

    public static function invalidPrices(): array
    {
        $cases = [];
        foreach (['price_monthly', 'price_yearly'] as $field) {
            foreach (['-0.01', '100000000', '1.001', 'NaN', 'abc', '1e2', '1,50', [], true] as $value) {
                $cases[] = [$field, $value];
            }
        }
        $cases[] = ['price_monthly', null];
        $cases[] = ['is_active', 'invalid'];
        $cases[] = ['confirmed', '0'];

        return $cases;
    }

    #[DataProvider('invalidPrices')]
    public function test_invalid_values_do_not_change_plan_or_create_audit(string $field, mixed $value): void
    {
        $before = $this->pro()->getRawOriginal();
        $this->actingAs($this->user(), 'web')->patchJson('/platform/plans/'.$this->pro()->id, $this->payload([$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, $this->pro()->getRawOriginal());
        $this->assertDatabaseCount('plan_changes', 0);
    }

    public static function requiredFields(): array
    {
        return [['price_monthly'], ['price_yearly'], ['is_active'], ['confirmed']];
    }

    #[DataProvider('requiredFields')]
    public function test_backend_requires_all_edit_fields_and_confirmation(string $field): void
    {
        $payload = $this->payload();
        unset($payload[$field]);
        $this->actingAs($this->user(), 'web')->patchJson('/platform/plans/'.$this->pro()->id, $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('plan_changes', 0);
    }

    public function test_html_validation_errors_render_safely_with_malformed_array_inputs(): void
    {
        $this->actingAs($this->user(), 'web')->from('/platform/plans')->patch('/platform/plans/'.$this->pro()->id,
            $this->payload(['price_monthly' => ['bad'], 'price_yearly' => ['bad'], 'is_active' => ['bad']]))
            ->assertRedirect('/platform/plans')->assertSessionHasErrors(['price_monthly', 'price_yearly', 'is_active']);
        $this->withSession(['_old_input' => ['price_monthly' => ['bad'], 'price_yearly' => ['bad'], 'is_active' => ['bad']]])
            ->get('/platform/plans')->assertOk();
        $this->assertDatabaseCount('plan_changes', 0);
    }

    public function test_edit_records_real_actor_old_and_new_values_and_date_atomically(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = $this->user();
        $plan = $this->pro();
        $old = $plan->only(['price_monthly', 'price_yearly', 'currency', 'is_active']);
        $this->actingAs($user, 'web')->patch('/platform/plans/'.$plan->id, $this->payload([
            'user_id' => $this->user()->id, 'plan_id' => 999, 'currency' => 'USD', 'max_users' => 0, 'slug' => 'free', 'name' => 'Tampered',
        ]))->assertRedirect(route('platform.plans.index'))->assertSessionHas('status');
        $plan->refresh();
        $this->assertSame('199.50', $plan->price_monthly);
        $this->assertSame('1999.00', $plan->price_yearly);
        $this->assertSame('MXN', $plan->currency);
        $this->assertSame(20, $plan->max_users);
        $this->assertSame('pro', $plan->slug);
        $this->assertSame('Pro', $plan->name);
        $audit = PlanChange::sole();
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame($plan->id, $audit->plan_id);
        $this->assertSame($old, $audit->old_values);
        $this->assertSame($plan->only(['price_monthly', 'price_yearly', 'currency', 'is_active']), $audit->new_values);
        $this->assertTrue($audit->recorded_at->equalTo(now()));
        $this->get('/platform/plans')->assertSee('Plan Pro actualizado');
    }

    public function test_audit_failure_rolls_back_the_catalog_update(): void
    {
        $before = $this->pro()->getRawOriginal();
        $user = $this->user();
        PlanChange::creating(function () {
            throw new \RuntimeException('Simulated audit failure');
        });
        try {
            $this->withoutExceptionHandling()->actingAs($user, 'web')->patch('/platform/plans/'.$this->pro()->id, $this->payload());
            $this->fail('Audit failure was ignored.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated audit failure', $exception->getMessage());
            $this->assertSame($before, $this->pro()->getRawOriginal());
            $this->assertDatabaseCount('plan_changes', 0);
        } finally {
            PlanChange::flushEventListeners();
        }
    }

    public function test_same_values_do_not_create_false_audit_and_unknown_annual_price_remains_null(): void
    {
        $this->actingAs($this->user(), 'web')->patch('/platform/plans/'.$this->pro()->id,
            $this->payload(['price_monthly' => '0.00', 'price_yearly' => null]))
            ->assertRedirect()->assertSessionHas('status', 'No hay cambios para guardar.');
        $this->assertDatabaseCount('plan_changes', 0);
        $this->assertNull($this->pro()->price_yearly);
    }

    public function test_boundary_prices_and_clearing_unknown_annual_price_are_supported(): void
    {
        $this->actingAs($this->user(), 'web');
        $this->patch('/platform/plans/'.$this->pro()->id, $this->payload(['price_monthly' => '99999999.99', 'price_yearly' => '0']))->assertRedirect();
        $this->assertSame('99999999.99', $this->pro()->price_monthly);
        $this->assertSame('0.00', $this->pro()->price_yearly);
        $this->patch('/platform/plans/'.$this->pro()->id, $this->payload(['price_yearly' => '']))->assertRedirect();
        $this->assertNull($this->pro()->price_yearly);
        $this->assertSame('0.00', PlanChange::orderByDesc('id')->first()->old_values['price_yearly']);
        $this->assertNull(PlanChange::orderByDesc('id')->first()->new_values['price_yearly']);
    }

    public function test_free_cannot_be_edited_or_disabled_even_by_superadmin(): void
    {
        $free = Plan::where('slug', 'free')->firstOrFail();
        $before = $free->getRawOriginal();
        $this->actingAs($this->user(), 'web')->patchJson('/platform/plans/'.$free->id, $this->payload(['is_active' => '0']))->assertForbidden();
        $this->assertSame($before, $free->fresh()->getRawOriginal());
        $this->assertSame('0.00', $free->fresh()->price_monthly);
        $this->assertTrue($free->fresh()->is_active);
        $this->assertDatabaseCount('plan_changes', 0);
    }

    public function test_seeder_preserves_edited_prices_availability_and_audit(): void
    {
        $this->actingAs($this->user(), 'web')->patch('/platform/plans/'.$this->pro()->id, $this->payload(['is_active' => '0']))->assertRedirect();
        $before = $this->pro()->getRawOriginal();
        $this->seed(PlanSeeder::class);
        $this->assertSame($before, $this->pro()->getRawOriginal());
        $this->assertDatabaseCount('plan_changes', 1);
        $this->assertDatabaseCount('plans', 2);
    }

    public function test_catalog_changes_preserve_current_and_future_contracts_all_companies_and_access_until_expiry(): void
    {
        $this->travelTo(now()->startOfDay());
        $plan = $this->pro();
        $plan->update(['price_monthly' => 100, 'price_yearly' => 1000]);
        $company = $this->company();
        $other = $this->company();
        $service = app(SubscriptionContractService::class);
        $current = $service->create($company, $plan, 'monthly', now()->toDateTimeString(), now()->addMonth()->toDateTimeString(), 'current');
        $service->create($company, $plan, 'yearly', now()->addMonth()->toDateTimeString(), now()->addMonth()->addYear()->toDateTimeString(), 'future', 'renewal');
        Subscription::create(['company_id' => $other->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth()]);
        $contracts = DB::table('subscription_contracts')->orderBy('id')->get()->toJson();
        $subscriptions = DB::table('subscriptions')->orderBy('id')->get()->toJson();
        $this->actingAs($this->user(), 'web')->patch('/platform/plans/'.$plan->id, $this->payload(['is_active' => '0']))->assertRedirect();
        $this->assertSame($contracts, DB::table('subscription_contracts')->orderBy('id')->get()->toJson());
        $this->assertSame($subscriptions, DB::table('subscriptions')->orderBy('id')->get()->toJson());
        $this->assertSame('100.00', $company->effectivePlan()->price_monthly);
        $this->assertSame(20, $other->effectivePlan()->max_users);
        $employee = User::factory()->create(['company_id' => $other->id, 'role_id' => Role::where('name', 'employee')->value('id'), 'status' => 'active']);
        Auth::forgetGuards();
        $this->app['tymon.jwt']->unsetToken();
        $this->app['tymon.jwt.auth']->unsetToken();
        $this->withToken(JWTAuth::fromUser($employee))->getJson('/api/dashboard')->assertOk();
        $this->travelTo($current->ends_at);
        $this->assertSame('1000.00', $company->effectivePlan()->price_yearly);
        $this->assertSame('free', $other->effectivePlan()->slug);
        $this->assertSame($contracts, DB::table('subscription_contracts')->orderBy('id')->get()->toJson());
    }

    public function test_disabling_pro_rejects_new_contracts_without_changing_existing_ones(): void
    {
        $company = $this->company();
        $this->actingAs($this->user(), 'web')->patch('/platform/plans/'.$this->pro()->id, $this->payload(['is_active' => '0']))->assertRedirect();
        try {
            app(SubscriptionContractService::class)->create($company, $this->pro(), 'monthly', now()->toDateTimeString(), now()->addMonth()->toDateTimeString(), 'disabled-pro');
            $this->fail('Disabled Pro was available for a new contract.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('subscriptions', 0);
            $this->assertDatabaseCount('subscription_contracts', 0);
            $this->assertDatabaseCount('plan_changes', 1);
        }
    }

    public function test_every_successful_change_appends_audit_and_preserves_previous_entries(): void
    {
        $this->actingAs($this->user(), 'web');
        $this->patch('/platform/plans/'.$this->pro()->id, $this->payload())->assertRedirect();
        $first = PlanChange::sole()->getRawOriginal();
        $this->patch('/platform/plans/'.$this->pro()->id, $this->payload(['price_monthly' => '250.00']))->assertRedirect();
        $this->assertSame($first, PlanChange::findOrFail($first['id'])->getRawOriginal());
        $this->assertDatabaseCount('plan_changes', 2);
        $this->assertSame('199.50', PlanChange::orderByDesc('id')->first()->old_values['price_monthly']);
        $this->assertSame('250.00', PlanChange::orderByDesc('id')->first()->new_values['price_monthly']);
    }

    public function test_audit_migration_rollback_refuses_to_delete_persistent_history(): void
    {
        $this->actingAs($this->user(), 'web')->patch('/platform/plans/'.$this->pro()->id, $this->payload())->assertRedirect();
        $before = PlanChange::sole()->getRawOriginal();
        $migration = require database_path('migrations/2026_10_21_020000_create_plan_changes_table.php');
        try {
            $migration->down();
            $this->fail('Persistent audit history was removed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Refusing to drop persistent plan audit history', $exception->getMessage());
            $this->assertSame($before, PlanChange::sole()->getRawOriginal());
        }
    }

    public function test_pro_can_be_reenabled_with_independent_audit_for_each_change(): void
    {
        $this->actingAs($this->user(), 'web');
        $this->patch('/platform/plans/'.$this->pro()->id, $this->payload(['is_active' => '0']))->assertRedirect();
        $this->patch('/platform/plans/'.$this->pro()->id, $this->payload())->assertRedirect();
        $this->assertTrue($this->pro()->is_active);
        $changes = PlanChange::orderBy('id')->get();
        $this->assertCount(2, $changes);
        $this->assertFalse($changes[1]->old_values['is_active']);
        $this->assertTrue($changes[1]->new_values['is_active']);
    }
}
