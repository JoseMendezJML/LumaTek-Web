<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * La conexión SQLite permite configurar desde el entorno la espera ante
 * bloqueos y el modo de diario (útil en desarrollo con escrituras
 * concurrentes, p. ej. lecturas IoT mientras se navega el panel).
 */
class SqliteDatabaseConfigTest extends TestCase
{
    private const KEYS = ['DB_BUSY_TIMEOUT', 'DB_JOURNAL_MODE', 'DB_SYNCHRONOUS', 'DB_TRANSACTION_MODE'];

    protected function tearDown(): void
    {
        foreach (self::KEYS as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        parent::tearDown();
    }

    private function sqliteConfig(array $env): array
    {
        foreach ($env as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return require __DIR__.'/../../config/database.php';
    }

    public function test_sqlite_options_are_read_from_the_environment(): void
    {
        $sqlite = $this->sqliteConfig([
            'DB_BUSY_TIMEOUT' => '5000',
            'DB_JOURNAL_MODE' => 'wal',
            'DB_SYNCHRONOUS' => 'normal',
            'DB_TRANSACTION_MODE' => 'IMMEDIATE',
        ])['connections']['sqlite'];

        $this->assertSame('5000', (string) $sqlite['busy_timeout']);
        $this->assertSame('wal', $sqlite['journal_mode']);
        $this->assertSame('normal', $sqlite['synchronous']);
        $this->assertSame('IMMEDIATE', $sqlite['transaction_mode']);
    }

    public function test_sqlite_options_default_to_null_when_not_configured(): void
    {
        $sqlite = $this->sqliteConfig([])['connections']['sqlite'];

        $this->assertNull($sqlite['busy_timeout']);
        $this->assertNull($sqlite['journal_mode']);
        $this->assertNull($sqlite['synchronous']);
        $this->assertSame('DEFERRED', $sqlite['transaction_mode']);
    }
}
