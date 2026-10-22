<?php

namespace Tests\Feature;

use App\Console\Commands\CreateSuperadmin;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class CreateSuperadminCommandTest extends TestCase
{
    use UsesInMemoryDatabase;

    // Credencial ficticia usada exclusivamente por las pruebas en memoria.
    private const PASSWORD = 'Example-Platform-123!';

    public function test_missing_role_fails_without_creating_roles_or_users(): void
    {
        Role::where('name', 'superadmin')->delete();
        $this->artisan('platform:create-superadmin')
            ->expectsOutput(__('platform.superadmin.role_missing'))->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseMissing('roles', ['name' => 'superadmin']);
    }

    public static function invalidIdentity(): array
    {
        return [
            'empty name' => ['', 'operator@example.test'],
            'long name' => [str_repeat('a', 121), 'operator@example.test'],
            'empty email' => ['Operator', ''],
            'invalid email' => ['Operator', 'invalid'],
            'long email' => ['Operator', str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.test'],
        ];
    }

    #[DataProvider('invalidIdentity')]
    public function test_invalid_identity_does_not_create_an_account(string $name, string $email): void
    {
        $this->answers($name, $email)->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public static function invalidPasswords(): array
    {
        return [
            'under privileged minimum' => ['Correcta-123!', 'Correcta-123!'],
            'no uppercase' => ['example-platform-123!', 'example-platform-123!'],
            'no lowercase' => ['EXAMPLE-PLATFORM-123!', 'EXAMPLE-PLATFORM-123!'],
            'no numbers' => ['Example-Platform-Only!', 'Example-Platform-Only!'],
            'no symbols' => ['ExamplePlatform12345', 'ExamplePlatform12345'],
            'different confirmation' => [self::PASSWORD, 'Different-Platform-123!'],
        ];
    }

    #[DataProvider('invalidPasswords')]
    public function test_privileged_password_policy_is_preserved(string $password, string $confirmation): void
    {
        $this->answers(password: $password, confirmation: $confirmation)->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_name_and_email_are_normalized_and_no_notification_or_business_data_is_created(): void
    {
        Notification::fake();
        $this->answers('  Operador  ', '  Operator@Example.Test  ')
            ->expectsConfirmation(__('platform.superadmin.confirm', ['email' => 'operator@example.test']), 'yes')
            ->expectsOutput(__('platform.superadmin.created'))->assertSuccessful();

        $user = User::where('email', 'operator@example.test')->firstOrFail();
        $this->assertSame('Operador', $user->name);
        $this->assertNull($user->company_id);
        $this->assertNull($user->google_id);
        $this->assertSame('superadmin', $user->role->name);
        $this->assertSame('active', $user->status);
        $this->assertFalse($user->is_company_owner);
        $this->assertFalse($user->plan_restricted);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertNotSame(self::PASSWORD, $user->password);
        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('subscriptions', 0);
        Notification::assertNothingSent();
    }

    public function test_existing_email_is_rejected_case_insensitively_without_promoting_or_modifying_the_user(): void
    {
        $user = $this->companyUser('Operator@Example.Test');
        $before = $user->fresh()->getRawOriginal();
        $this->answers()->expectsOutput(__('platform.superadmin.email_exists'))->assertFailed();
        $this->assertSame($before, $user->fresh()->getRawOriginal());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_password_prompts_are_hidden_without_fallback_and_verification_defaults_to_no(): void
    {
        [$status, $output] = $this->runInteractively(confirmed: false);
        $this->assertSame(CreateSuperadmin::FAILURE, $status);
        $this->assertStringContainsString(__('platform.superadmin.cancelled'), $output);
        $this->assertStringNotContainsString(self::PASSWORD, $output);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_superadmin_created_while_prompting_blocks_the_pending_creation(): void
    {
        [$status, $output] = $this->runInteractively(function (): void {
            User::factory()->create([
                'company_id' => null,
                'role_id' => Role::where('name', 'superadmin')->value('id'),
                'is_company_owner' => false,
                'email' => 'another-operator@example.test',
            ]);
        });
        $this->assertSame(CreateSuperadmin::FAILURE, $status);
        $this->assertStringContainsString(__('platform.superadmin.already_exists'), $output);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['email' => 'operator@example.test']);
    }

    public function test_email_registered_while_prompting_is_rechecked_without_elevation(): void
    {
        $user = null;
        $before = null;
        [$status, $output] = $this->runInteractively(function () use (&$user, &$before): void {
            $user = $this->companyUser('Operator@Example.Test');
            $before = $user->fresh()->getRawOriginal();
        });
        $this->assertSame(CreateSuperadmin::FAILURE, $status);
        $this->assertStringContainsString(__('platform.superadmin.email_exists'), $output);
        $this->assertSame($before, $user->fresh()->getRawOriginal());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_role_removed_while_prompting_is_not_recreated(): void
    {
        [$status, $output] = $this->runInteractively(function (): void {
            Role::where('name', 'superadmin')->delete();
        });
        $this->assertSame(CreateSuperadmin::FAILURE, $status);
        $this->assertStringContainsString(__('platform.superadmin.role_missing'), $output);
        $this->assertDatabaseMissing('roles', ['name' => 'superadmin']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_failed_persistence_rolls_back_and_does_not_print_or_log_sql_credentials(): void
    {
        Log::spy();
        $hash = null;
        User::created(function (User $user) use (&$hash): void {
            $hash = $user->password;
            throw new QueryException('sqlite', 'insert into users (email, password) values (?, ?)', [
                $user->email, $user->password,
            ], new RuntimeException('Simulated write failure '.self::PASSWORD));
        });

        [$status, $output] = $this->runInteractively();
        $this->assertSame(CreateSuperadmin::FAILURE, $status);
        $this->assertNotNull($hash);
        $this->assertStringContainsString(__('platform.superadmin.persistence_failed'), $output);
        $this->assertStringNotContainsString(self::PASSWORD, $output);
        $this->assertStringNotContainsString($hash, $output);
        $this->assertStringNotContainsString('insert into users', $output);
        $this->assertStringNotContainsString('Simulated write failure', $output);
        $this->assertDatabaseCount('users', 0);
        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'] as $level) {
            Log::shouldNotHaveReceived($level);
        }
    }

    private function answers(string $name = 'Operator', string $email = 'operator@example.test', string $password = self::PASSWORD, string $confirmation = self::PASSWORD)
    {
        return $this->artisan('platform:create-superadmin')
            ->expectsQuestion(__('platform.superadmin.name'), $name)
            ->expectsQuestion(__('platform.superadmin.email'), $email)
            ->expectsQuestion(__('platform.superadmin.password'), $password)
            ->expectsQuestion(__('platform.superadmin.password_confirmation'), $confirmation);
    }

    private function companyUser(string $email): User
    {
        $company = Company::create(['name' => 'Empresa', 'email' => 'company@example.test', 'status' => 'active']);

        return User::factory()->create([
            'company_id' => $company->id,
            'role_id' => Role::where('name', 'employee')->value('id'),
            'email' => $email, 'status' => 'inactive', 'google_id' => 'existing-google-account',
        ]);
    }

    /** Simula cambios durante los prompts; no sustituye una prueba concurrente MySQL. */
    private function runInteractively(?Closure $beforeConfirmation = null, bool $confirmed = true): array
    {
        $input = new ArrayInput([]);
        $buffer = new BufferedOutput;
        $output = Mockery::mock(OutputStyle::class.'[askQuestion]', [$input, $buffer]);
        foreach ([
            'name' => 'Operator', 'email' => 'operator@example.test',
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ] as $field => $answer) {
            $output->shouldReceive('askQuestion')->once()->ordered()
                ->with(Mockery::on(function (Question $question) use ($field): bool {
                    $this->assertSame(__('platform.superadmin.'.$field), $question->getQuestion());
                    if (str_starts_with($field, 'password')) {
                        $this->assertTrue($question->isHidden());
                        $this->assertFalse($question->isHiddenFallback());
                    }

                    return true;
                }))->andReturn($answer);
        }
        $output->shouldReceive('askQuestion')->once()->ordered()
            ->with(Mockery::on(function (ConfirmationQuestion $question): bool {
                $this->assertSame(__('platform.superadmin.confirm', ['email' => 'operator@example.test']), $question->getQuestion());
                $this->assertFalse($question->getDefault());

                return true;
            }))->andReturnUsing(function () use ($beforeConfirmation, $confirmed): bool {
                $beforeConfirmation?->__invoke();

                return $confirmed;
            });

        $command = new CreateSuperadmin;
        $command->setLaravel($this->app);

        return [$command->run($input, $output), $buffer->fetch()];
    }
}
