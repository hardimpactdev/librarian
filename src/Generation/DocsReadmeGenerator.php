<?php

declare(strict_types=1);

namespace HardImpact\Librarian\Generation;

use HardImpact\Librarian\Domains\DomainRepository;

final readonly class DocsReadmeGenerator
{
    public function __construct(
        private DomainRepository $domains,
    ) {}

    public function render(): string
    {
        $lines = [
            '# Documentation',
            '',
            '1. [Mission](mission.md)',
            '2. [Architecture](architecture.md)',
            '3. [Tech Stack](tech-stack.md)',
            '4. [Concepts](concepts.md)',
            '',
            '## Domains',
            '',
        ];

        foreach (array_values($this->domains->all()) as $index => $domain) {
            $number = $index + 1;

            $lines[] = "{$number}. [{$domain->title()}](domains/{$domain->directoryName}/{$domain->landingPageName()})";
        }

        return implode("\n", $lines)."\n";
    }
}
