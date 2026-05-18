<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Linting\Rules;

use FilesystemIterator;
use HardImpact\Librarian\Docs\DocsFilesystem;
use HardImpact\Librarian\Linting\Finding;
use HardImpact\Librarian\Linting\FindingSeverity;
use HardImpact\Librarian\Linting\GroupedRule;
use HardImpact\Librarian\Linting\Rule;

final readonly class DomainDirectoriesRule implements GroupedRule
{
    private const string RULE = 'librarian.domain_directories';

    private const string DIRECTORY_PATTERN = '/^(?<order>[1-9][0-9]*)_(?<slug>[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)$/';

    public function __construct(
        private DocsFilesystem $filesystem,
    ) {}

    public function group(): string
    {
        return 'structure';
    }

    public function check(): array
    {
        $domainsPath = $this->filesystem->docsPath('domains');

        if (! is_dir($domainsPath)) {
            return [];
        }

        $findings = [];
        $validDirectories = [];

        foreach (new FilesystemIterator($domainsPath, FilesystemIterator::SKIP_DOTS) as $entry) {
            if (! $entry->isDir()) {
                continue;
            }

            $name = $entry->getFilename();

            if (! preg_match(self::DIRECTORY_PATTERN, $name, $matches)) {
                $findings[] = new Finding(
                    path: 'docs/domains/'.$name,
                    line: null,
                    severity: FindingSeverity::Error,
                    rule: self::RULE,
                    message: 'Domain directories must match <number>_<slug> with a lowercase kebab-case slug.',
                );

                continue;
            }

            $validDirectories[] = [
                'name' => $name,
                'order' => (int) $matches['order'],
            ];
        }

        usort($validDirectories, static fn (array $left, array $right): int => $left['order'] <=> $right['order']);

        foreach ($validDirectories as $index => $directory) {
            $expectedOrder = $index + 1;

            if ($directory['order'] === $expectedOrder) {
                continue;
            }

            $findings[] = new Finding(
                path: 'docs/domains/'.$directory['name'],
                line: null,
                severity: FindingSeverity::Error,
                rule: self::RULE,
                message: "Domain prefixes must be contiguous from 1; expected {$expectedOrder}.",
            );
        }

        return $findings;
    }
}
