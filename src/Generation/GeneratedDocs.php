<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Generation;

use HardImpact\Librarian\Docs\DocsFilesystem;
use RuntimeException;

final readonly class GeneratedDocs
{
    public function __construct(
        private DocsFilesystem $filesystem,
        private DocsReadmeGenerator $docsReadmeGenerator,
        private ConceptsIndexGenerator $conceptsIndexGenerator,
    ) {}

    public function write(): void
    {
        $this->writeFile('README.md', $this->docsReadmeGenerator->render());
        $this->writeFile('concepts.md', $this->conceptsIndexGenerator->render());
    }

    public function isCurrent(): bool
    {
        return $this->currentContents('README.md') === $this->docsReadmeGenerator->render()
            && $this->currentContents('concepts.md') === $this->conceptsIndexGenerator->render();
    }

    private function writeFile(string $path, string $contents): void
    {
        $absolutePath = $this->filesystem->docsPath($path);

        if (! is_dir(dirname($absolutePath)) && ! mkdir(dirname($absolutePath), 0777, true) && ! is_dir(dirname($absolutePath))) {
            throw new RuntimeException("Unable to create directory for generated docs file [{$absolutePath}].");
        }

        if (file_put_contents($absolutePath, $contents) === false) {
            throw new RuntimeException("Unable to write generated docs file [{$absolutePath}].");
        }
    }

    private function currentContents(string $path): ?string
    {
        $absolutePath = $this->filesystem->docsPath($path);

        if (! is_file($absolutePath)) {
            return null;
        }

        return file_get_contents($absolutePath) ?: '';
    }
}
