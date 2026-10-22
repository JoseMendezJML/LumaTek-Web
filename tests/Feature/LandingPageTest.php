<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use UsesInMemoryDatabase;

    public function test_public_catalog_displays_actual_prices_currency_and_all_limits(): void
    {
        Plan::where('slug', 'pro')->update([
            'price_monthly' => '1234.56', 'price_yearly' => '12345.67', 'currency' => 'USD',
            'max_greenhouses' => 7, 'max_zones_per_greenhouse' => 8, 'max_sensors_per_zone' => 9,
            'max_users' => 21, 'max_additional_admins' => 4,
        ]);
        $response = $this->get('/')->assertOk()->assertViewIs('landing');
        $card = $this->card($response, 'pro');
        foreach (['1,234.56 USD', '12,345.67 USD', '/ mes', '/ año', 'Invernaderos: 7',
            'Zonas por invernadero: 8', 'Sensores por zona: 9', 'Usuarios por empresa: 21',
            'Administradores adicionales: 4'] as $text) {
            $this->assertStringContainsString($text, $card);
        }
        $free = $this->card($response, 'free');
        $this->assertStringContainsString('$0 MXN / mes', $free);
        $this->assertStringContainsString('Administradores adicionales: 0', $free);
        $this->assertStringNotContainsString('Sin límite', $free);
        $response->assertSee('href="'.route('register').'"', false)
            ->assertSee('Crear cuenta gratis')->assertDontSee('Próximamente');
    }

    public function test_catalog_price_changes_are_visible_on_the_next_request(): void
    {
        $plan = Plan::where('slug', 'pro')->firstOrFail();
        $plan->update(['price_monthly' => '410.25', 'price_yearly' => '4100.50']);
        $this->get('/')->assertSee('410.25 MXN')->assertSee('4,100.50 MXN');
        $plan->update(['price_monthly' => '620.75', 'price_yearly' => '6200.25']);
        $this->get('/')->assertSee('620.75 MXN')->assertSee('6,200.25 MXN')
            ->assertDontSee('410.25 MXN')->assertDontSee('4,100.50 MXN');
    }

    public function test_free_price_format_uses_the_catalog_without_changing_stored_prices_or_pro_format(): void
    {
        $free = Plan::where('slug', 'free')->firstOrFail();
        Plan::where('slug', 'pro')->update(['price_monthly' => '1234.56', 'price_yearly' => '12345.67']);
        $before = Plan::orderBy('id')->get()->map->getRawOriginal()->all();
        $response = $this->get('/')->assertOk();
        $this->assertStringContainsString('$0 MXN / mes', $this->card($response, 'free'));
        $this->assertStringContainsString('1,234.56 MXN / mes', $this->card($response, 'pro'));
        $this->assertStringContainsString('12,345.67 MXN / año', $this->card($response, 'pro'));
        $this->assertSame($before, Plan::orderBy('id')->get()->map->getRawOriginal()->all());

        $free->update(['price_monthly' => '50.25']);
        $card = $this->card($this->get('/')->assertOk(), 'free');
        $this->assertStringContainsString('50.25 MXN / mes', $card);
        $this->assertStringNotContainsString('$0 MXN', $card);

        $free->update(['price_monthly' => 0, 'currency' => 'EUR']);
        $card = $this->card($this->get('/')->assertOk(), 'free');
        $this->assertStringContainsString('0.00 EUR / mes', $card);
        $this->assertStringNotContainsString('MXN', $card);
        $this->assertStringNotContainsString('$', $card);
    }

    public static function incompletePrices(): array
    {
        return [
            'seed placeholder' => ['0.00', null, 'MXN'],
            'both zero' => ['0.00', '0.00', 'MXN'],
            'missing currency' => ['700.00', '7000.00', ''],
            'invalid currency' => ['700.00', '7000.00', '??'],
            'negative amounts' => ['-1.00', '-2.00', 'MXN'],
        ];
    }

    #[DataProvider('incompletePrices')]
    public function test_incomplete_pro_prices_are_never_advertised_as_free(string $monthly, ?string $yearly, string $currency): void
    {
        Plan::where('slug', 'pro')->update([
            'price_monthly' => $monthly, 'price_yearly' => $yearly, 'currency' => $currency,
        ]);
        $card = $this->card($this->get('/')->assertOk(), 'pro');
        $this->assertStringContainsString('Precio mensual por confirmar', $card);
        $this->assertStringContainsString('Precio anual por confirmar', $card);
        $this->assertStringNotContainsString('0.00', $card);
        $this->assertStringNotContainsString('$0', $card);
        $this->assertStringNotContainsString('gratis', $card);
    }

    public function test_only_the_configured_pro_modality_is_priced(): void
    {
        Plan::where('slug', 'pro')->update(['price_monthly' => 0, 'price_yearly' => '9999.50']);
        $card = $this->card($this->get('/')->assertOk(), 'pro');
        $this->assertStringContainsString('Precio mensual por confirmar', $card);
        $this->assertStringContainsString('9,999.50 MXN / año', $card);

        Plan::where('slug', 'pro')->update(['price_monthly' => '999.50', 'price_yearly' => null]);
        $card = $this->card($this->get('/')->assertOk(), 'pro');
        $this->assertStringContainsString('999.50 MXN / mes', $card);
        $this->assertStringContainsString('Precio anual por confirmar', $card);
    }

    public static function inactivePlans(): array
    {
        return [['free'], ['pro']];
    }

    #[DataProvider('inactivePlans')]
    public function test_inactive_plans_are_not_displayed(string $slug): void
    {
        Plan::where('slug', $slug)->update(['is_active' => false, 'name' => 'Plan oculto del catálogo']);
        $this->get('/')->assertOk()->assertDontSee('data-plan="'.$slug.'"', false)
            ->assertDontSee('Plan oculto del catálogo')
            ->assertSee('data-plan="'.($slug === 'pro' ? 'free' : 'pro').'"', false);
    }

    public function test_empty_catalog_has_a_clear_fallback_without_invented_prices_or_plans(): void
    {
        Plan::query()->delete();
        $this->get('/')->assertOk()
            ->assertSee('Los planes no están disponibles temporalmente.')
            ->assertDontSee('data-plan=', false)->assertDontSee('class="plan-price"', false);
    }

    public function test_unknown_catalog_entries_are_not_published(): void
    {
        $plan = Plan::where('slug', 'pro')->firstOrFail()->replicate();
        $plan->slug = 'private-catalog-entry';
        $plan->name = 'No publicar este plan';
        $plan->save();
        $this->get('/')->assertOk()->assertDontSee('No publicar este plan')
            ->assertViewHas('plans', fn ($plans) => $plans->pluck('slug')->values()->all() === ['free', 'pro']);
    }

    public function test_incomplete_descriptions_and_zero_limits_are_not_replaced_by_unlimited_capacity(): void
    {
        Plan::where('slug', 'pro')->update(['name' => '', 'description' => null, 'max_additional_admins' => 0]);
        $card = $this->card($this->get('/')->assertOk(), 'pro');
        $this->assertStringContainsString('Pro', $card);
        $this->assertStringContainsString('Capacidad y condiciones del catálogo vigente.', $card);
        $this->assertStringContainsString('Administradores adicionales: 0', $card);
        $this->assertStringNotContainsString('Sin límite', $card);
    }

    public function test_guest_is_sent_to_existing_login_and_registration_routes_for_pro(): void
    {
        $this->get('/')->assertOk()->assertSee('Iniciar sesión para solicitar Pro')
            ->assertSee('href="'.route('login').'"', false)->assertSee('registra tu empresa')
            ->assertDontSee('href="'.route('subscription.index').'"', false)
            ->assertSee('Solicitar una cotización no activa Pro.')
            ->assertSee('confirmación manual del pago externo')
            ->assertSee('No hay cobros ni renovaciones automáticas.');
    }

    public static function audiences(): array
    {
        return [
            'active owner' => ['company_admin', true, 'active', 'active', true],
            'inactive owner' => ['company_admin', true, 'inactive', 'active', false],
            'suspended company' => ['company_admin', true, 'active', 'inactive', false],
            'additional admin' => ['company_admin', false, 'active', 'active', false],
            'employee' => ['employee', false, 'active', 'active', false],
            'owner flag without admin role' => ['employee', true, 'active', 'active', false],
            'platform account' => ['superadmin', false, 'active', 'active', false],
        ];
    }

    #[DataProvider('audiences')]
    public function test_only_an_active_company_owner_receives_the_subscription_link(string $role, bool $owner, string $status, string $companyStatus, bool $link): void
    {
        $company = $role === 'superadmin' ? null : Company::create([
            'name' => 'Empresa confidencial', 'email' => 'private@example.test', 'status' => $companyStatus,
        ]);
        $user = User::factory()->create([
            'company_id' => $company?->id, 'role_id' => Role::where('name', $role)->value('id'),
            'is_company_owner' => $owner, 'status' => $status,
        ]);
        $response = $this->actingAs($user, 'web')->get('/')->assertOk()
            ->assertDontSee('Empresa confidencial')->assertDontSee('private@example.test');
        if ($link) {
            $response->assertSee('href="'.route('subscription.index').'"', false);
        } else {
            $response->assertDontSee('href="'.route('subscription.index').'"', false)
                ->assertSee('únicamente para propietarios de empresas activas');
        }
    }

    public function test_guest_only_queries_the_public_catalog_once_and_exposes_no_private_models(): void
    {
        Company::create(['name' => 'Empresa privada', 'email' => 'confidential@example.test', 'status' => 'active']);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->get('/')->assertOk()->assertDontSee('Empresa privada')->assertDontSee('confidential@example.test')
            ->assertViewHas('plans', function ($plans): bool {
                foreach ($plans as $plan) {
                    $this->assertSame(['slug', 'name', 'description', 'monthly', 'yearly', 'limits'], array_keys($plan));
                }

                return true;
            });
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('from "plans"', $queries[0]);
        $this->assertStringNotContainsString('select *', $queries[0]);
    }

    public function test_dynamic_catalog_content_is_escaped(): void
    {
        Plan::where('slug', 'pro')->update([
            'name' => '<script>alert("name")</script>', 'description' => '<img src=x onerror=alert("description")>',
        ]);
        $this->get('/')->assertOk()->assertSee('<script>alert("name")</script>')
            ->assertSee('<img src=x onerror=alert("description")>')
            ->assertDontSee('<script>alert("name")</script>', false)
            ->assertDontSee('<img src=x onerror=alert("description")>', false);
    }

    public function test_public_copy_describes_software_and_conditional_luma_without_exposing_service_configuration(): void
    {
        config(['services.anthropic.api_key' => 'private-service-test-key']);
        $this->get('/')->assertOk()->assertSee('Asistente Luma')->assertSee('cuando el servicio esté configurado y disponible')
            ->assertSee('requieren integración y pruebas en hardware')
            ->assertSee('Usuarios y permisos')->assertSee('Reportes')
            ->assertSee('id="funcionamiento"', false)->assertSee('lumatek-logo.png')
            ->assertDontSee('private-service-test-key')->assertDontSee('Conecta dispositivos')
            ->assertDontSee('Historial ampliado')->assertDontSee('Funciones avanzadas de monitoreo');
    }

    private function card(TestResponse $response, string $slug): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $nodes = (new DOMXPath($document))->query('//article[@data-plan="'.$slug.'"]');
        $this->assertSame(1, $nodes->length);

        return trim(preg_replace('/\s+/u', ' ', $nodes->item(0)->textContent));
    }
}
