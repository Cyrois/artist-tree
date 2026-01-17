<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    /**
     * Creates the application.
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // Load the testing environment file
        if (file_exists(__DIR__.'/../.env.testing')) {
            $app->loadEnvironmentFrom('.env.testing');
        }

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }
}
