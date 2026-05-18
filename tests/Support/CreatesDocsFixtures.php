<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Tests\Support;

trait CreatesDocsFixtures
{
    protected function deletePath(string $path): void
    {
        $fullPath = $this->app->basePath($path);

        if (! file_exists($fullPath)) {
            return;
        }

        if (is_file($fullPath)) {
            unlink($fullPath);

            return;
        }

        foreach (scandir($fullPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->deletePath($path.DIRECTORY_SEPARATOR.$entry);
        }

        if (is_dir($fullPath)) {
            rmdir($fullPath);
        }
    }

    protected function writeFile(string $path, string $contents): void
    {
        $fullPath = $this->app->basePath($path);

        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }

        file_put_contents($fullPath, $contents);
    }

    protected function readFile(string $path): string
    {
        return file_get_contents($this->app->basePath($path)) ?: '';
    }
}
