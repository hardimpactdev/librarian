<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Domains;

use HardImpact\Librarian\Docs\DocsFilesystem;
use RuntimeException;

final readonly class DomainScaffolder
{
    public function __construct(
        private DocsFilesystem $filesystem,
    ) {}

    public function scaffold(string $slug, int $order): void
    {
        $directory = $this->filesystem->docsPath("domains/{$order}_{$slug}");

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create domain directory [{$directory}].");
        }

        $contents = sprintf(
            "# %s\n\n## Purpose\n\n## Responsibilities\n\n## Boundaries\n",
            (string) str($slug)->replace('-', ' ')->title(),
        );

        $landingPage = "{$directory}/{$slug}.md";

        if (file_put_contents($landingPage, $contents) === false) {
            throw new RuntimeException("Unable to write domain landing page [{$landingPage}].");
        }
    }
}
