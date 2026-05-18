<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Docs;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final readonly class MarkdownSnapshot
{
    public function __construct(
        private DocsFilesystem $filesystem,
    ) {}

    /**
     * @return array<string, string>
     */
    public function capture(): array
    {
        $docsPath = $this->filesystem->docsPath();

        if (! is_dir($docsPath)) {
            return [];
        }

        $snapshot = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($docsPath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            if (! $item->isFile() || $item->getExtension() !== 'md') {
                continue;
            }

            $contents = file_get_contents($item->getPathname());

            if ($contents === false) {
                throw new RuntimeException("Unable to read markdown file [{$item->getPathname()}].");
            }

            $snapshot[$this->filesystem->relativePath($item->getPathname())] = $contents;
        }

        return $snapshot;
    }

    /**
     * @param  array<string, string>  $snapshot
     */
    public function restore(array $snapshot): void
    {
        $docsPath = $this->filesystem->docsPath();

        if (is_dir($docsPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($docsPath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $item) {
                if (! $item->isFile() || $item->getExtension() !== 'md') {
                    continue;
                }

                $relativePath = $this->filesystem->relativePath($item->getPathname());

                if (array_key_exists($relativePath, $snapshot)) {
                    continue;
                }

                unlink($item->getPathname());
            }
        }

        foreach ($snapshot as $relativePath => $contents) {
            $absolutePath = $this->filesystem->docsPath($relativePath);

            if (! is_dir(dirname($absolutePath)) && ! mkdir(dirname($absolutePath), 0777, true) && ! is_dir(dirname($absolutePath))) {
                throw new RuntimeException("Unable to create directory for markdown file [{$absolutePath}].");
            }

            if (file_put_contents($absolutePath, $contents) === false) {
                throw new RuntimeException("Unable to restore markdown file [{$absolutePath}].");
            }
        }
    }
}
