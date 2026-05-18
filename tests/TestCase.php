<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Tests;

use HardImpact\Librarian\LibrarianServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LibrarianServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $this->app = $app;

        $app['config']->set('librarian.path', $this->docsPath());
    }

    protected function docsPath(): string
    {
        return $this->app->basePath('docs');
    }
}
