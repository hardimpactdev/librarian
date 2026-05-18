<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Docs;

use InvalidArgumentException;

final readonly class DocsConfig
{
    public function __construct(
        public string $path,
    ) {}

    public static function fromConfig(): self
    {
        $path = rtrim(str_replace('\\', '/', (string) config('librarian.path')), '/');

        if ($path === '') {
            throw new InvalidArgumentException('The librarian.path config value must not be empty.');
        }

        return new self($path);
    }
}
