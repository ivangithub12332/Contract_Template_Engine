<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $this->forceSqliteTestingDatabase();

        $app = require Application::inferBasePath().'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);

        return $app;
    }

    private function forceSqliteTestingDatabase(): void
    {
        foreach ([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
