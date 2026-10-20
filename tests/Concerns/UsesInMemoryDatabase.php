<?php

namespace Tests\Concerns;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

trait UsesInMemoryDatabase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Fail before opening a connection or running any migration unless the
        // caller explicitly selected the isolated configuration in phpunit.xml.
        $this->assertTrue($this->app->environment('testing'));
        $this->assertFalse($this->app->configurationIsCached());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertContains(config('database.connections.sqlite.url'), [null, '']);

        foreach (array_keys(DB::getConnections()) as $connection) {
            DB::purge($connection);
        }

        // Remove other configured connections so an accidental explicit MySQL
        // connection cannot reach the development database from this suite.
        config([
            'database.default' => 'sqlite',
            'database.connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'url' => null,
                    'database' => ':memory:',
                    'prefix' => '',
                    'foreign_key_constraints' => true,
                ],
            ],
            'cache.default' => 'array',
            'session.driver' => 'array',
            'queue.default' => 'sync',
            'mail.default' => 'array',
            'logging.default' => 'null',
            'app.key' => 'base64:'.base64_encode(str_repeat('t', 32)),
            'jwt.secret' => 'lumatek-lum-61-in-memory-tests-only-secret-2026',
            'jwt.algo' => 'HS256',
        ]);

        $connection = DB::connection('sqlite');
        $this->assertSame('sqlite', $connection->getDriverName());
        $this->assertSame(':memory:', $connection->getDatabaseName());
        $databases = $connection->select('PRAGMA database_list');
        $this->assertNotEmpty($databases);

        foreach ($databases as $database) {
            $this->assertSame('', $database->file, 'SQLite must not use a database file.');
        }

        $this->artisan('migrate', [
            '--database' => 'sqlite',
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $this->seed(DatabaseSeeder::class);
        $this->withoutVite();
    }
}
