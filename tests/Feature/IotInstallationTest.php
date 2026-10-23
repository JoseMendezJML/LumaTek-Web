<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Services\SubscriptionContractService;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class IotInstallationTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase;

    private ?string $firmwareDirectory = null;

    protected function tearDown(): void
    {
        if ($this->firmwareDirectory) {
            // Only these two known fixture files in this test's private directory.
            foreach (['reviewed.ino', 'reviewed.bin'] as $name) {
                $path = $this->firmwareDirectory.DIRECTORY_SEPARATOR.$name;
                if (is_file($path)) {
                    unlink($path);
                }
            }
            rmdir($this->firmwareDirectory);
        }
        parent::tearDown();
    }

    private function approvedRelease(): void
    {
        $this->firmwareDirectory = storage_path('framework/testing/iot-guide-'.Str::uuid());
        mkdir($this->firmwareDirectory, 0777, true);
        // Synthetic test fixtures only, never a deployable firmware distribution.
        file_put_contents($this->firmwareDirectory.'/reviewed.ino', '// synthetic test artifact without network credentials');
        file_put_contents($this->firmwareDirectory.'/reviewed.bin', "\xE9synthetic-test-only");
        config(['iot_installation.firmware_root' => $this->firmwareDirectory, 'iot_installation.releases.reviewed' => [
            'model' => 'esp32_devkit_v1', 'label' => 'ESP32 DevKit V1', 'version' => 'test-fixture', 'date' => '2026-10-23',
            'status' => 'published', 'approved' => true, 'reviewed_by' => 'test reviewer', 'approved_at' => '2026-10-23',
            'secrets_reviewed' => true, 'instructions' => 'Synthetic test only',
            'artifacts' => ['ino' => ['filename' => 'reviewed.ino', 'sha256' => hash_file('sha256', $this->firmwareDirectory.'/reviewed.ino')],
                'bin' => ['filename' => 'reviewed.bin', 'sha256' => hash_file('sha256', $this->firmwareDirectory.'/reviewed.bin')]],
        ]]);
    }

    public function test_admin_navigation_guide_and_disabled_distribution(): void
    {
        $this->actingAs($this->user($this->company()));
        $this->get('/zones')->assertOk()->assertSee(route('iot.installation'));
        $response = $this->get('/iot/installation')->assertOk()->assertSee('GPIO4')->assertSee('GPIO34')
            ->assertSee('DHT22')->assertSee('Sin versión distribuible')->assertSee('Instalar por USB')->assertSee('monitor serial');
        foreach (range(1, 6) as $step) {
            $response->assertSee('href="#step-'.$step.'"', false)->assertSee('id="step-'.$step.'"', false);
        }
        $response->assertDontSee('esp-web-install-button', false)->assertDontSee('<script src="https://', false);
        $this->get('/iot/firmware/esp32-devkit-planned/ino')->assertNotFound();
        $this->get('/iot/firmware/esp32-devkit-planned/bin')->assertNotFound();
    }

    public function test_guests_employees_inactive_users_and_suspended_companies_cannot_access(): void
    {
        $this->get('/iot/installation')->assertRedirect(route('login'));
        $this->get('/iot/firmware/reviewed/ino')->assertRedirect(route('login'));
        $company = $this->company();
        $this->actingAs($this->user($company, 'employee'));
        $this->get('/zones')->assertOk()->assertDontSee(route('iot.installation'));
        foreach (['/iot/installation', '/iot/firmware/reviewed/ino'] as $uri) {
            $this->getJson($uri)->assertForbidden();
        }
        $user = $this->user($company);
        $user->update(['status' => 'inactive']);
        $this->actingAs($user)->getJson('/iot/installation')->assertForbidden();
        $company->update(['status' => 'inactive']);
        $this->actingAs($this->user($company))->getJson('/iot/installation')->assertForbidden();
        $this->actingAs($this->user(null, 'superadmin'))->getJson('/iot/installation')->assertForbidden();
    }

    public function test_approved_private_files_are_downloadable_only_to_authorized_admins(): void
    {
        $this->approvedRelease();
        $company = $this->company();
        $this->actingAs($this->user($company));
        $this->get('/iot/installation')->assertOk()->assertSee(route('iot.firmware.download', ['release' => 'reviewed', 'format' => 'ino']));
        foreach (['ino', 'bin'] as $format) {
            $response = $this->get('/iot/firmware/reviewed/'.$format)->assertOk()->assertDownload('reviewed.'.$format)->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        $this->actingAs($this->user($company, 'employee'))->getJson('/iot/firmware/reviewed/ino')->assertForbidden();
        $this->actingAs($this->user($this->company()))->get('/iot/firmware/reviewed/ino')->assertOk(); // Generic public release, protected access.
        config(['iot_installation.releases.reviewed.company_id' => $company->id]);
        $this->get('/iot/firmware/reviewed/ino')->assertNotFound();
        $this->get('/iot/installation')->assertOk()->assertDontSee('test-fixture');
    }

    public function test_missing_changed_unreviewed_and_withdrawn_artifacts_are_never_served(): void
    {
        $this->approvedRelease();
        $this->actingAs($this->user($this->company()));
        foreach (['approved', 'secrets_reviewed'] as $flag) {
            config(['iot_installation.releases.reviewed.'.$flag => false]);
            $this->get('/iot/firmware/reviewed/ino')->assertNotFound();
            config(['iot_installation.releases.reviewed.'.$flag => true]);
        }
        foreach (['reviewed_by', 'approved_at', 'version', 'date', 'instructions'] as $field) {
            $original = config('iot_installation.releases.reviewed.'.$field);
            config(['iot_installation.releases.reviewed.'.$field => null]);
            $this->get('/iot/firmware/reviewed/ino')->assertNotFound();
            config(['iot_installation.releases.reviewed.'.$field => $original]);
        }
        config(['iot_installation.releases.reviewed.status' => 'withdrawn']);
        $this->get('/iot/firmware/reviewed/bin')->assertNotFound();
        config(['iot_installation.releases.reviewed.status' => 'published']);
        file_put_contents($this->firmwareDirectory.'/reviewed.ino', '// changed after approval');
        $this->get('/iot/firmware/reviewed/ino')->assertNotFound();
        unlink($this->firmwareDirectory.'/reviewed.bin');
        $this->get('/iot/firmware/reviewed/bin')->assertNotFound();
        config(['iot_installation.releases.reviewed.artifacts.ino.filename' => '../reviewed.ino']);
        $this->get('/iot/firmware/reviewed/ino')->assertNotFound();
        $this->get('/iot/firmware/reviewed/txt')->assertNotFound();
    }

    public function test_free_pro_and_restricted_accounts_follow_existing_plan_policy(): void
    {
        $company = $this->company();
        $this->actingAs($this->user($company))->get('/iot/installation')->assertOk();
        app(SubscriptionContractService::class)->create($company, Plan::where('slug', 'pro')->firstOrFail(), 'monthly', now()->toDateTimeString(), now()->addMonth()->toDateTimeString(), 'installation-pro');
        $this->get('/iot/installation')->assertOk();
        $freeCompany = $this->company();
        $user = $this->user($freeCompany);
        $user->update(['is_company_owner' => false]);
        Plan::where('slug', 'free')->update(['max_users' => 0]);
        $this->actingAs($user)->getJson('/iot/installation')->assertForbidden();
        $this->getJson('/iot/firmware/reviewed/ino')->assertForbidden();
    }
}
