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
            return $this->root();
        }

        return $this->root().'/'.ltrim(str_replace('\\', '/', $path), '/');
    }

    public function relativePath(string $absolutePath): string
    {
        $root = $this->root();
        $absolutePath = rtrim(str_replace('\\', '/', $absolutePath), '/');

        if ($absolutePath === $root) {
            return '';
        }

        $docsPrefix = "{$root}/";

        if (! str_starts_with($absolutePath, $docsPrefix)) {
            throw new InvalidArgumentException(
                "Path [{$absolutePath}] is not inside the docs root [{$root}].",
            );
        }

        return substr($absolutePath, strlen($docsPrefix));
    }

    private function root(): string
    {
        return rtrim(str_replace('\\', '/', $this->config->path), '/');
    }
}
