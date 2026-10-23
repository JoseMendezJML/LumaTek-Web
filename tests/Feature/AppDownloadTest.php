<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

/**
 * QR de la portada y descarga de la app móvil (/app).
 */
class AppDownloadTest extends TestCase
{
    use UsesInMemoryDatabase {
        setUp as setUpDatabase;
    }

    private string $apk;

    protected function setUp(): void
    {
        $this->setUpDatabase();

        $this->apk = sys_get_temp_dir().'/lumatek-test-'.uniqid().'.apk';
        config(['mobile_app.apk_path' => $this->apk]);
    }

    protected function tearDown(): void
    {
        File::delete($this->apk);

        parent::tearDown();
    }

    private const ANDROID = 'Mozilla/5.0 (Linux; Android 15; 23113RKC6G) AppleWebKit/537.36 Chrome/131 Mobile Safari/537.36';

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_5 like Mac OS X) AppleWebKit/605.1.15 Version/26.5 Mobile/15E148 Safari/604.1';

    public function test_landing_shows_the_qr_pointing_to_the_download_route(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('data-app-download-qr="'.route('app.download').'"', false);
        $response->assertSee('<svg', false);
        $response->assertSee('Descarga la app');
        $response->assertSee('href="'.route('app.download').'"', false);
    }

    public function test_android_downloads_the_apk(): void
    {
        File::put($this->apk, 'APK-DE-PRUEBA');

        $response = $this->withHeader('User-Agent', self::ANDROID)->get('/app');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.android.package-archive');
        $this->assertStringContainsString('LumaTek.apk', $response->headers->get('Content-Disposition'));
        $this->assertSame('APK-DE-PRUEBA', $response->baseResponse->getFile()->getContent());
    }

    public function test_iphone_gets_a_message_instead_of_the_apk(): void
    {
        File::put($this->apk, 'APK-DE-PRUEBA');

        $this->withHeader('User-Agent', self::IPHONE)->get('/app')
            ->assertOk()
            ->assertSee('Por ahora la app de LumaTek solo está disponible para Android')
            ->assertHeaderMissing('Content-Disposition');
    }

    public function test_without_a_published_apk_shows_coming_soon(): void
    {
        $this->withHeader('User-Agent', self::ANDROID)->get('/app')
            ->assertNotFound()
            ->assertSee('Próximamente');
    }
}
