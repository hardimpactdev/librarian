<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Docs;

use InvalidArgumentException;

final readonly class DocsFilesystem
{
    public function __construct(
        private DocsConfig $config,
    ) {}

    public function docsPath(string $path = ''): string
    {
        if ($path === '') {
            return $this->config->path;
        }

        return "{$this->config->path}/".ltrim($path, '/');
    }

    public function relativePath(string $absolutePath): string
    {
        if ($absolutePath === $this->config->path) {
            return '';
        }

        $docsPrefix = "{$this->config->path}/";

        if (! str_starts_with($absolutePath, $docsPrefix)) {
            throw new InvalidArgumentException(
                "Path [{$absolutePath}] is not inside the docs root [{$this->config->path}].",
            );
        }

        return substr($absolutePath, strlen($docsPrefix));
    }
}
