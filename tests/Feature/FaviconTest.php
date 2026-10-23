<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

/**
 * Favicon e iconos de acceso directo de la web.
 */
class FaviconTest extends TestCase
{
    use UsesInMemoryDatabase;

    private function assertHasIcons($response): void
    {
        $response->assertSee('rel="icon" type="image/svg+xml" href="'.asset('favicon.svg').'"', false);
        $response->assertSee('rel="icon" href="'.asset('favicon.ico').'" sizes="any"', false);
        $response->assertSee('rel="apple-touch-icon" href="'.asset('apple-touch-icon.png').'"', false);
        $response->assertSee('rel="manifest" href="'.asset('site.webmanifest').'"', false);
    }

    public function test_icon_files_exist_and_are_not_empty(): void
    {
        foreach (['favicon.ico', 'favicon.svg', 'apple-touch-icon.png', 'icon-192.png', 'icon-512.png', 'site.webmanifest'] as $file) {
            $this->assertFileExists(public_path($file));
            $this->assertGreaterThan(0, filesize(public_path($file)), "$file está vacío");
        }

        // ICO válido: reservado 0, tipo 1 (icono) y al menos tres tamaños.
        $header = unpack('vreserved/vtype/vcount', file_get_contents(public_path('favicon.ico'), false, null, 0, 6));
        $this->assertSame(['reserved' => 0, 'type' => 1], ['reserved' => $header['reserved'], 'type' => $header['type']]);
        $this->assertGreaterThanOrEqual(3, $header['count']);

        $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true);
        $this->assertSame('LumaTek', $manifest['name']);
        $this->assertSame(['192x192', '512x512'], array_column($manifest['icons'], 'sizes'));
    }

    public function test_public_pages_declare_the_icons(): void
    {
        $this->assertHasIcons($this->get('/'));
        $this->assertHasIcons($this->get('/login'));
    }

    public function test_company_and_platform_pages_declare_the_icons(): void
    {
        $company = Company::create(['name' => 'Empresa '.Str::random(6), 'email' => fake()->unique()->safeEmail(), 'status' => 'active']);
        $owner = User::factory()->create(['company_id' => $company->id, 'role_id' => Role::where('name', 'company_admin')->value('id'), 'is_company_owner' => true, 'status' => 'active']);
        $this->assertHasIcons($this->actingAs($owner)->withSession([User::AUTH_VERSION_SESSION_KEY => ['user_id' => $owner->id, 'version' => $owner->authenticationVersion()]])->get('/dashboard'));

        $admin = User::factory()->create(['company_id' => null, 'role_id' => Role::where('name', 'superadmin')->value('id'), 'status' => 'active']);
        $this->app['auth']->forgetGuards();
        $this->assertHasIcons($this->actingAs($admin)->withSession([User::AUTH_VERSION_SESSION_KEY => ['user_id' => $admin->id, 'version' => $admin->authenticationVersion()]])->get('/platform'));
    }
}
