<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting\Rules;

use FilesystemIterator;
use HardImpact\Librarian\Docs\DocsFilesystem;
use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\FindingSeverity;
use HardImpact\Librarian\Linting\GroupedRule;
use HardImpact\Librarian\Linting\Rule;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final readonly class NoScaffoldPromptTextRule implements GroupedRule
{
    private const string RULE = 'librarian.scaffold_text';

    public function __construct(
        private DocsFilesystem $filesystem,
    ) {}

    public function group(): string
    {
        return 'prose';
    }

    public function check(): array
    {
        $docsPath = $this->filesystem->docsPath();

        if (! is_dir($docsPath)) {
            return [];
        }

        $findings = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docsPath, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'md') {
                continue;
            }

            $relativePath = $this->filesystem->relativePath($file->getPathname());

            if (in_array($relativePath, ['README.md', 'concepts.md'], true)) {
                continue;
            }

            foreach (preg_split('/\R/', file_get_contents($file->getPathname()) ?: '') ?: [] as $index => $line) {
                $trimmed = trim($line);

                if ($trimmed === '') {
                    continue;
                }

                if (! str_starts_with($trimmed, 'Describe ') && ! preg_match('/\b(?:TODO|TBD)\b/', $trimmed)) {
                    continue;
                }

                $findings[] = new Finding(
                    path: 'docs/'.$relativePath,
                    line: $index + 1,
                    severity: FindingSeverity::Error,
                    rule: self::RULE,
                    message: 'Replace scaffold prompt text and placeholders in completed docs.',
                );
            }
        }

        return $findings;
    }
}
