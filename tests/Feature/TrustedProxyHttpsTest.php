<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyHttpsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue($this->app->environment('testing'));
        $this->assertFalse($this->app->configurationIsCached());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        // No migrations, seeders or database access are needed for URL generation.
        config([
            'database.connections' => ['sqlite' => config('database.connections.sqlite')],
            'session.driver' => 'array', 'cache.default' => 'array', 'logging.default' => 'null',
            'app.key' => 'base64:'.base64_encode(str_repeat('t', 32)),
            'app.trusted_proxies' => null,
        ]);
        TrustProxies::flushState();
        Request::setTrustedProxies([], 0);

        Route::get('/_test/proxy-urls', fn (Request $request) => [
            'secure' => $request->isSecure(),
            'css' => asset('css/app.css'),
            'js' => asset('js/app.js'),
            'url' => url('/forgot-password'),
            'route' => route('login'),
            'ip' => $request->ip(),
        ]);
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        Request::setTrustedProxies([], 0);
        parent::tearDown();
    }

    private function bootProxyEnvironment(string $environment, ?string $configured = null): void
    {
        $this->app->instance('env', $environment);
        config(['app.trusted_proxies' => $configured]);
        (new AppServiceProvider($this->app))->boot();
    }

    private function caddyHeaders(): array
    {
        // Caddy preserves Host (including :8443); it does not require a forwarded port.
        return [
            'Host' => '192.168.1.175:8443',
            'X-Forwarded-Host' => '192.168.1.175:8443',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '192.168.1.80',
        ];
    }

    public function test_local_caddy_generates_https_assets_and_urls_with_external_port(): void
    {
        $this->bootProxyEnvironment('local');
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1', 'SERVER_PORT' => 8002])
            ->getJson('http://127.0.0.1:8002/_test/proxy-urls', $this->caddyHeaders())
            ->assertOk()->assertJsonPath('secure', true)
            ->assertJsonPath('css', 'https://192.168.1.175:8443/css/app.css')
            ->assertJsonPath('js', 'https://192.168.1.175:8443/js/app.js')
            ->assertJsonPath('url', 'https://192.168.1.175:8443/forgot-password')
            ->assertJsonPath('route', 'https://192.168.1.175:8443/login')
            ->assertJsonPath('ip', '192.168.1.80');

        // A subsequent direct request must not inherit the proxy's scheme.
        $this->getJson('http://127.0.0.1:8002/_test/proxy-urls')->assertOk()
            ->assertJsonPath('secure', false)
            ->assertJsonPath('css', 'http://127.0.0.1:8002/css/app.css');
    }

    public function test_login_html_uses_https_for_actual_stylesheets_and_links(): void
    {
        $this->bootProxyEnvironment('local');
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1', 'SERVER_PORT' => 8002])
            ->get('http://127.0.0.1:8002/login', $this->caddyHeaders())->assertOk()
            ->assertSee('https://192.168.1.175:8443/css/app.css', false)
            ->assertSee('https://192.168.1.175:8443/css/auth.css', false)
            ->assertSee('https://192.168.1.175:8443/forgot-password', false)
            ->assertDontSee('http://192.168.1.175:8443/', false);
    }

    public function test_direct_development_http_keeps_its_scheme_and_port(): void
    {
        $this->bootProxyEnvironment('local');
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1', 'SERVER_PORT' => 8002])
            ->getJson('http://127.0.0.1:8002/_test/proxy-urls')->assertOk()
            ->assertJsonPath('secure', false)
            ->assertJsonPath('css', 'http://127.0.0.1:8002/css/app.css')
            ->assertJsonPath('js', 'http://127.0.0.1:8002/js/app.js')
            ->assertJsonPath('route', 'http://127.0.0.1:8002/login');
    }

    public function test_untrusted_client_cannot_spoof_https_or_host(): void
    {
        $this->bootProxyEnvironment('local');
        $headers = $this->caddyHeaders();
        $headers['Host'] = '127.0.0.1:8002';
        $headers['X-Forwarded-Port'] = '8443';
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.80', 'SERVER_PORT' => 8002])
            ->getJson('http://127.0.0.1:8002/_test/proxy-urls', $headers)->assertOk()
            ->assertJsonPath('secure', false)
            ->assertJsonPath('css', 'http://127.0.0.1:8002/css/app.css')
            ->assertJsonPath('ip', '192.168.1.80');
    }

    public function test_production_has_no_implicit_loopback_trust(): void
    {
        $this->bootProxyEnvironment('production');
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1', 'SERVER_PORT' => 8002])
            ->getJson('http://127.0.0.1:8002/_test/proxy-urls', $this->caddyHeaders())
            ->assertOk()->assertJsonPath('secure', false)
            ->assertJsonPath('css', 'http://127.0.0.1:8002/css/app.css');
    }

    public function test_explicit_proxy_configuration_overrides_the_local_default(): void
    {
        $this->bootProxyEnvironment('local', '192.0.2.10');
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1', 'SERVER_PORT' => 8002])
            ->getJson('http://127.0.0.1:8002/_test/proxy-urls', $this->caddyHeaders())
            ->assertOk()->assertJsonPath('secure', false);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10', 'SERVER_PORT' => 8002])
            ->getJson('http://127.0.0.1:8002/_test/proxy-urls', $this->caddyHeaders())
            ->assertOk()->assertJsonPath('secure', true)
            ->assertJsonPath('css', 'https://192.168.1.175:8443/css/app.css');
    }
}
